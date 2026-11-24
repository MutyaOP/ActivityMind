"""Демо-режим: правдоподобная история работы за N дней (без Windows). Детерминированна — повтор не создаёт дублей."""
from __future__ import annotations

import random
from datetime import date, datetime, timedelta, timezone

from session_builder import Session

# (приложение, заголовок, вес)
APPS = [
    ("Code", "main.php - ActivityMind", 5),
    ("chrome", "Laravel documentation", 3),
    ("WINWORD", "Диплом.docx", 3),
    ("WindowsTerminal", "php artisan serve", 2),
    ("chrome", "Google Classroom", 2),
    ("Telegram", "", 1),
    ("chrome", "YouTube", 1),
    ("Spotify", "", 1),
]


def day_sessions(day: date, track_titles: bool = False, tz_offset_hours: int = 5) -> list[Session]:
    rng = random.Random(day.toordinal())
    # рабочий день 09:00–18:00 по местному времени → переводим в UTC
    local_start = datetime(day.year, day.month, day.day, 9, rng.randint(0, 30), tzinfo=timezone.utc)
    start = local_start - timedelta(hours=tz_offset_hours)
    end = start + timedelta(hours=9)

    apps = [(a, t) for a, t, _ in APPS]
    weights = [w for _, _, w in APPS]

    sessions: list[Session] = []
    t = start
    while t < end:
        if rng.random() < 0.12:
            duration = rng.randint(5, 15) * 60
            sessions.append(Session("Простой", "", t, duration, True))
        else:
            app, title = rng.choices(apps, weights=weights)[0]
            duration = rng.randint(5, 40) * 60
            sessions.append(Session(app, title if track_titles else "", t, duration, False))
        t += timedelta(seconds=duration + rng.randint(0, 120))
    return sessions


def history(days: int, track_titles: bool = False, tz_offset_hours: int = 5, today: date | None = None) -> list[Session]:
    today = today or datetime.now(timezone.utc).date()
    out: list[Session] = []
    for back in range(days, 0, -1):
        out += day_sessions(today - timedelta(days=back), track_titles, tz_offset_hours)
    return sorted(out, key=lambda s: s.started_at)
