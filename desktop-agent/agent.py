"""ActivityMind Desktop Agent: собирает активность Windows и отправляет её на сайт."""
from __future__ import annotations

import argparse
import logging
import os
import sys
from dataclasses import replace
from datetime import datetime, timedelta, timezone
from logging.handlers import RotatingFileHandler

import browser
import config as config_module
import simulate
import tracker
from control import Control
from instance_lock import InstanceLock
from session_builder import Session, SessionBuilder
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


def prepare(sessions: list[Session], cfg: config_module.Config) -> list[Session]:
    """
    Решает, что из сессии уйдёт на сайт.
    Браузер: категория вкладки определяется здесь, по заголовку; сам заголовок отправляется,
    только если на вкладке провели не меньше min_tab_sec. Остальные программы: заголовок —
    только при track_window_titles.
    """
    out = []
    for s in sessions:
        if s.is_idle:
            out.append(replace(s, window_title=""))
        elif browser.is_browser(s.app_name):
            keep = cfg.track_window_titles or (cfg.track_browser_tabs and s.duration_sec >= cfg.min_tab_sec)
            out.append(replace(s, window_title=browser.clean_title(s.window_title) if keep else "",
                               category_hint=browser.category_hint(s.window_title)))
        else:
            out.append(replace(s, window_title=s.window_title if cfg.track_window_titles else ""))
    return out


def key_title(app: str, title: str, cfg: config_module.Config) -> str:
    """Заголовок, по смене которого режутся сессии: у браузера — всегда (каждая вкладка отдельно)."""
    return title if (cfg.track_window_titles or browser.is_browser(app)) else ""


def agent_state(builder: SessionBuilder, control: Control) -> dict:
    current = None if control.paused else builder.current()
    return {
        "paused": control.paused,
        "current_app": current[0] if current else None,
        "current_since": current[1].strftime("%Y-%m-%dT%H:%M:%SZ") if current else None,
    }


def send_state(client: ApiClient, builder: SessionBuilder, control: Control) -> str:
    try:
        client.heartbeat(agent_state(builder, control))
    except AuthError:
        return "fatal"
    except (TransientError, RejectedError):
        return "retry"
    return "ok"


def run(cfg: config_module.Config, client: ApiClient, buffer: Buffer, control: Control | None = None,
        sampler=None, clock=utcnow, monotonic=None, max_ticks: int | None = None) -> int:
    """Основной цикл. sampler/clock/monotonic/max_ticks подменяются в тестах."""
    import time

    control = control or Control()
    sampler = sampler or (lambda: tracker.sample(cfg.idle_threshold_sec, cfg.ignore_apps))
    monotonic = monotonic or time.monotonic
    builder = SessionBuilder(cfg.min_session_sec, cfg.max_session_sec, max_gap_sec=cfg.poll_interval_sec * 6)
    next_sync = monotonic()  # сразу сообщаем сайту, что агент запущен
    backoff = cfg.sync_interval_sec
    was_paused = False
    ticks = 0

    log.info("Агент запущен. Вкладки браузера от %s мин: %s. Заголовки остальных окон: %s.",
             cfg.min_tab_sec // 60, "да" if cfg.track_browser_tabs else "нет",
             "да" if cfg.track_window_titles else "нет")
    code = 0
    try:
        while not control.stopped:
            now = clock()
            if control.paused:
                if not was_paused:
                    buffer.add(prepare(builder.flush(now), cfg))
                    log.info("Пауза: активность не записывается.")
                    was_paused = True
                    next_sync = monotonic()
            else:
                if was_paused:
                    log.info("Мониторинг продолжен.")
                    was_paused = False
                    next_sync = monotonic()
                app, title, idle, idle_sec = sampler()
                idle_since = now - timedelta(seconds=idle_sec) if idle else None
                buffer.add(prepare(builder.feed(now, app, key_title(app, title, cfg), idle, idle_since), cfg))

            if monotonic() >= next_sync:
                state = flush_buffer(client, buffer, cfg.batch_size)
                if state == "ok":
                    state = send_state(client, builder, control)
                if state == "fatal":
                    log.error("Токен недействителен. Выпустите новый на сайте («Устройства») и обновите config.ini.")
                    code = 2
                    break
                backoff = cfg.sync_interval_sec if state == "ok" else min(backoff * 2, 600)
                next_sync = monotonic() + backoff
                buffer.purge_sent()

            ticks += 1
            if max_ticks is not None and ticks >= max_ticks:
                break
            if control.wait(cfg.poll_interval_sec):
                next_sync = min(next_sync, monotonic())  # пауза/продолжение — сразу сообщить сайту
    except KeyboardInterrupt:
        log.info("Остановка по Ctrl+C...")
    finally:
        buffer.add(prepare(builder.flush(clock()), cfg))
        if code == 0:
            flush_buffer(client, buffer, cfg.batch_size)
            control.pause()  # сайт покажет, что учёт не идёт
            send_state(client, builder, control)
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
    parser.add_argument("--no-tray", action="store_true", help="не показывать значок в трее")
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
        sessions = prepare(simulate.history(args.simulate_days, track_titles=True), cfg)
        added = buffer.add(sessions)
        log.info("Сгенерировано сессий: %s (новых в буфере: %s)", len(sessions), added)
        state = flush_buffer(client, buffer, cfg.batch_size)
        return 0 if state == "ok" else 2

    lock = InstanceLock(os.path.join(BASE_DIR, "agent.lock"))
    if not lock.acquire():
        log.error("Агент уже запущен на этом компьютере. Вторая копия записала бы время дважды.")
        return 4

    control = Control()
    if not args.no_tray:
        import tray

        tray.start(control)
    try:
        return run(cfg, client, buffer, control)
    except NotImplementedError as exc:
        log.error("%s", exc)
        return 1
    finally:
        lock.release()


if __name__ == "__main__":
    sys.exit(main())
