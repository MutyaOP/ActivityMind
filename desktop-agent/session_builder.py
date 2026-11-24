"""Превращает поток «замеров» (приложение, окно, простой) в сессии активности."""
from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime


@dataclass(frozen=True)
class Session:
    app_name: str
    window_title: str
    started_at: datetime  # UTC
    duration_sec: int
    is_idle: bool


class SessionBuilder:
    """
    Правила:
    * смена (приложение, заголовок, простой) закрывает текущую сессию и открывает новую;
    * сессии короче min_session_sec отбрасываются (случайные переключения окон);
    * сессия дольше max_session_sec режется на части;
    * если между замерами прошло больше max_gap_sec (сон компьютера, пауза агента),
      сессия закрывается на последнем замере, а не растягивается на «пропавшее» время.
    """

    def __init__(self, min_session_sec: int = 3, max_session_sec: int = 3600, max_gap_sec: int = 30):
        self.min_session_sec = min_session_sec
        self.max_session_sec = max_session_sec
        self.max_gap_sec = max_gap_sec
        self._key: tuple[str, str, bool] | None = None
        self._start: datetime | None = None
        self._last_seen: datetime | None = None

    def feed(self, now: datetime, app: str, title: str, idle: bool) -> list[Session]:
        key = (app, title, bool(idle))
        out: list[Session] = []

        if self._key is None:
            self._open(key, now)
            return out

        if (now - self._last_seen).total_seconds() > self.max_gap_sec:
            out += self._close(self._last_seen)
            self._open(key, now)
            return out

        if key != self._key or (now - self._start).total_seconds() >= self.max_session_sec:
            out += self._close(now)
            self._open(key, now)
        else:
            self._last_seen = now

        return out

    def flush(self, now: datetime) -> list[Session]:
        """Закрывает текущую сессию (при остановке агента)."""
        if self._key is None:
            return []
        out = self._close(now)
        self._key = self._start = self._last_seen = None
        return out

    # ------------------------------------------------------------------

    def _open(self, key, now: datetime) -> None:
        self._key = key
        self._start = now
        self._last_seen = now

    def _close(self, end: datetime) -> list[Session]:
        duration = int(round((end - self._start).total_seconds()))
        if duration < self.min_session_sec:
            return []
        app, title, idle = self._key
        return [Session(app, title, self._start, duration, idle)]
