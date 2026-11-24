"""Локальный буфер на SQLite: данные не теряются, пока нет сети."""
from __future__ import annotations

import sqlite3
from datetime import datetime, timedelta, timezone
from typing import Iterable

from session_builder import Session

ISO = "%Y-%m-%dT%H:%M:%SZ"


class Buffer:
    def __init__(self, path: str = ":memory:"):
        self.conn = sqlite3.connect(path)
        self.conn.execute(
            """CREATE TABLE IF NOT EXISTS sessions (
                   id INTEGER PRIMARY KEY AUTOINCREMENT,
                   app_name TEXT NOT NULL,
                   window_title TEXT NOT NULL DEFAULT '',
                   started_at TEXT NOT NULL,
                   duration_sec INTEGER NOT NULL,
                   is_idle INTEGER NOT NULL DEFAULT 0,
                   sent INTEGER NOT NULL DEFAULT 0
               )"""
        )
        # та же «личность» записи, что и на сервере: время начала + приложение
        self.conn.execute("CREATE UNIQUE INDEX IF NOT EXISTS ux_sessions ON sessions (started_at, app_name)")
        self.conn.commit()

    def add(self, sessions: Iterable[Session]) -> int:
        rows = [
            (s.app_name, s.window_title, s.started_at.strftime(ISO), s.duration_sec, int(s.is_idle))
            for s in sessions
        ]
        if not rows:
            return 0
        cur = self.conn.executemany(
            "INSERT OR IGNORE INTO sessions (app_name, window_title, started_at, duration_sec, is_idle) VALUES (?,?,?,?,?)",
            rows,
        )
        self.conn.commit()
        return cur.rowcount

    def pending(self, limit: int = 200) -> list[dict]:
        cur = self.conn.execute(
            "SELECT id, app_name, window_title, started_at, duration_sec, is_idle "
            "FROM sessions WHERE sent = 0 ORDER BY started_at LIMIT ?",
            (limit,),
        )
        return [
            {"id": r[0], "app_name": r[1], "window_title": r[2], "started_at": r[3],
             "duration_sec": r[4], "is_idle": bool(r[5])}
            for r in cur.fetchall()
        ]

    def pending_count(self) -> int:
        return self.conn.execute("SELECT COUNT(*) FROM sessions WHERE sent = 0").fetchone()[0]

    def mark_sent(self, ids: list[int]) -> None:
        if not ids:
            return
        marks = ",".join("?" * len(ids))
        self.conn.execute(f"UPDATE sessions SET sent = 1 WHERE id IN ({marks})", ids)
        self.conn.commit()

    def purge_sent(self, days: int = 7) -> None:
        cutoff = (datetime.now(timezone.utc) - timedelta(days=days)).strftime(ISO)
        self.conn.execute("DELETE FROM sessions WHERE sent = 1 AND started_at < ?", (cutoff,))
        self.conn.commit()

    def close(self) -> None:
        self.conn.close()
