"""Чтение config.ini."""
from __future__ import annotations

import configparser
import os
from dataclasses import dataclass, field


@dataclass
class Config:
    url: str = "http://localhost:8000"
    token: str = ""
    poll_interval_sec: int = 5
    idle_threshold_sec: int = 300
    min_session_sec: int = 3
    max_session_sec: int = 3600
    track_window_titles: bool = False
    ignore_apps: list[str] = field(default_factory=list)
    sync_interval_sec: int = 60
    batch_size: int = 200
    timeout_sec: int = 10


def load(path: str) -> Config:
    if not os.path.exists(path):
        raise FileNotFoundError(
            f"Не найден {path}. Скопируйте config.example.ini в config.ini и впишите токен устройства."
        )

    cp = configparser.ConfigParser()
    cp.read(path, encoding="utf-8")

    def get(section, key, default, cast=str):
        if not cp.has_option(section, key):
            return default
        raw = cp.get(section, key).strip()
        if cast is bool:
            return raw.lower() in ("1", "true", "yes", "on", "да")
        return cast(raw)

    ignore = get("tracking", "ignore_apps", "")
    return Config(
        url=get("server", "url", Config.url).rstrip("/"),
        token=get("server", "token", ""),
        poll_interval_sec=get("tracking", "poll_interval_sec", 5, int),
        idle_threshold_sec=get("tracking", "idle_threshold_sec", 300, int),
        min_session_sec=get("tracking", "min_session_sec", 3, int),
        max_session_sec=get("tracking", "max_session_sec", 3600, int),
        track_window_titles=get("tracking", "track_window_titles", False, bool),
        ignore_apps=[a.strip().lower() for a in ignore.split(",") if a.strip()],
        sync_interval_sec=get("sync", "interval_sec", 60, int),
        batch_size=get("sync", "batch_size", 200, int),
        timeout_sec=get("sync", "timeout_sec", 10, int),
    )
