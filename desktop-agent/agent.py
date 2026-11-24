"""ActivityMind Desktop Agent: собирает активность Windows и отправляет её на сайт."""
from __future__ import annotations

import argparse
import logging
import os
import sys
import time
from datetime import datetime, timezone
from logging.handlers import RotatingFileHandler

import config as config_module
import simulate
import tracker
from session_builder import SessionBuilder
from storage import Buffer
from sync_client import ApiClient, AuthError, RejectedError, TransientError

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
log = logging.getLogger("agent")


def utcnow() -> datetime:
    return datetime.now(timezone.utc)


def flush_buffer(client: ApiClient, buffer: Buffer, batch_size: int) -> str:
    """
    Отправляет накопленное. Возвращает:
      'ok'    — всё отправлено (или отправлять нечего);
      'retry' — сеть/сервер недоступны, данные остались в буфере;
      'fatal' — токен недействителен, продолжать нет смысла.
    """
    while True:
        rows = buffer.pending(batch_size)
        if not rows:
            return "ok"
        ids = [r["id"] for r in rows]

        try:
            result = client.send(rows)
        except AuthError:
            return "fatal"
        except TransientError as exc:
            log.warning("Нет связи с сервером: %s (в буфере %s)", exc, buffer.pending_count())
            return "retry"
        except RejectedError as exc:
            log.error("Сервер отклонил пачку из %s записей: %s", len(rows), exc)
            if len(rows) == 1:
                buffer.mark_sent(ids)  # «битую» запись отбрасываем, чтобы не зациклиться
                continue
            outcome = _send_one_by_one(client, buffer, rows)
            if outcome != "ok":
                return outcome
            continue

        buffer.mark_sent(ids)
        log.info("Отправлено: %s, дубликатов: %s", result.get("accepted"), result.get("duplicates"))


def _send_one_by_one(client: ApiClient, buffer: Buffer, rows: list[dict]) -> str:
    for row in rows:
        try:
            client.send([row])
        except AuthError:
            return "fatal"
        except TransientError:
            return "retry"
        except RejectedError as exc:
            log.error("Запись отброшена (%s): %s", row["app_name"], exc)
        buffer.mark_sent([row["id"]])
    return "ok"


def run(cfg: config_module.Config, client: ApiClient, buffer: Buffer) -> int:
    builder = SessionBuilder(cfg.min_session_sec, cfg.max_session_sec, max_gap_sec=cfg.poll_interval_sec * 6)
    next_sync = time.monotonic() + cfg.sync_interval_sec
    backoff = cfg.sync_interval_sec

    log.info("Агент запущен. Заголовки окон: %s. Ctrl+C для остановки.", "включены" if cfg.track_window_titles else "выключены")
    code = 0
    try:
        while True:
            app, title, idle = tracker.sample(cfg.idle_threshold_sec, cfg.track_window_titles, cfg.ignore_apps)
            buffer.add(builder.feed(utcnow(), app, title, idle))

            if time.monotonic() >= next_sync:
                state = flush_buffer(client, buffer, cfg.batch_size)
                if state == "fatal":
                    log.error("Токен недействителен. Выпустите новый на сайте («Устройства») и обновите config.ini.")
                    code = 2
                    break
                backoff = cfg.sync_interval_sec if state == "ok" else min(backoff * 2, 600)
                next_sync = time.monotonic() + backoff
                buffer.purge_sent()

            time.sleep(cfg.poll_interval_sec)
    except KeyboardInterrupt:
        log.info("Остановка по Ctrl+C...")
    finally:
        buffer.add(builder.flush(utcnow()))
        if code == 0:
            flush_buffer(client, buffer, cfg.batch_size)
    return code


def setup_logging() -> None:
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(message)s")
    root = logging.getLogger()
    root.setLevel(logging.INFO)
    console = logging.StreamHandler()
    console.setFormatter(fmt)
    root.addHandler(console)
    try:
        file_handler = RotatingFileHandler(os.path.join(BASE_DIR, "agent.log"), maxBytes=500_000, backupCount=2, encoding="utf-8")
        file_handler.setFormatter(fmt)
        root.addHandler(file_handler)
    except OSError:
        pass


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="ActivityMind Desktop Agent")
    parser.add_argument("--config", default=os.path.join(BASE_DIR, "config.ini"), help="путь к config.ini")
    parser.add_argument("--check", action="store_true", help="проверить токен и связь с сайтом и выйти")
    parser.add_argument("--simulate-days", type=int, metavar="N", help="создать и отправить демо-историю за N дней и выйти")
    args = parser.parse_args(argv)

    setup_logging()
    try:
        cfg = config_module.load(args.config)
    except FileNotFoundError as exc:
        log.error("%s", exc)
        return 1

    if not cfg.token:
        log.error("В config.ini не указан token. Создайте устройство на сайте («Устройства»).")
        return 1

    client = ApiClient(cfg.url, cfg.token, timeout=cfg.timeout_sec)

    if args.check:
        try:
            info = client.heartbeat()
        except AuthError as exc:
            log.error("%s Проверьте токен.", exc)
            return 2
        except (TransientError, RejectedError) as exc:
            log.error("Сайт недоступен: %s", exc)
            return 3
        log.info("Связь есть. Устройство: %s. Время сервера: %s", info["device"]["name"], info["server_time"])
        return 0

    buffer = Buffer(os.path.join(BASE_DIR, "buffer.db"))

    if args.simulate_days:
        sessions = simulate.history(args.simulate_days, cfg.track_window_titles)
        added = buffer.add(sessions)
        log.info("Сгенерировано сессий: %s (новых в буфере: %s)", len(sessions), added)
        state = flush_buffer(client, buffer, cfg.batch_size)
        return 0 if state == "ok" else 2

    try:
        return run(cfg, client, buffer)
    except NotImplementedError as exc:
        log.error("%s", exc)
        return 1


if __name__ == "__main__":
    sys.exit(main())
