"""Управление работающим агентом: пауза/продолжение и остановка (из значка в трее или Ctrl+C)."""
from __future__ import annotations

import threading


class Control:
    def __init__(self) -> None:
        self._paused = threading.Event()
        self._stop = threading.Event()
        self._changed = threading.Event()  # будит цикл агента, чтобы он сразу сообщил сайту о паузе

    @property
    def paused(self) -> bool:
        return self._paused.is_set()

    @property
    def stopped(self) -> bool:
        return self._stop.is_set()

    def pause(self) -> None:
        self._paused.set()
        self._changed.set()

    def resume(self) -> None:
        self._paused.clear()
        self._changed.set()

    def toggle(self) -> None:
        self.resume() if self.paused else self.pause()

    def stop(self) -> None:
        self._stop.set()
        self._changed.set()

    def wait(self, seconds: float) -> bool:
        """Спит до seconds секунд; True — если за это время что-то изменилось (пауза, остановка)."""
        changed = self._changed.wait(seconds)
        self._changed.clear()
        return changed
