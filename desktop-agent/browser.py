"""
Браузер: распознавание вкладок и категорий прямо на компьютере.

Заголовок окна браузера — это название активной вкладки. По нему агент сам определяет категорию
(учёба, программирование, работа, отдых) и отправляет на сайт только её. Название вкладки уходит на сайт,
лишь если на ней провели непрерывно не меньше min_tab_sec (по умолчанию 5 минут), — короткие
переходы остаются на компьютере.
"""
from __future__ import annotations

import re

BROWSERS = ("chrome", "msedge", "firefox", "opera", "brave", "vivaldi", "browser", "yandex")

# порядок важен: первое совпадение побеждает
HINT_RULES = [
    ("study", ("classroom", "moodle", "coursera", "stepik", "udemy", "khan academy", "лекци", "универ", "wikipedia", "википеди")),
    ("programming", ("github", "gitlab", "stack overflow", "stackoverflow", "laravel", "php.net", "mdn web docs",
                     "developer.mozilla", "localhost", "127.0.0.1", "phpmyadmin", "python.org", "documentation", "документац")),
    ("work", ("gmail", "outlook", "google docs", "google документы", "google sheets", "google таблицы", "notion",
              "trello", "jira", "confluence", "figma", "chatgpt")),
    ("rest", ("youtube", "netflix", "twitch", "instagram", "tiktok", "vk.com", "вконтакте", "facebook", "reddit",
              "кинопоиск", "steam", "spotify", "twitter", " x.com", "pinterest")),
]

_SEPARATOR = re.compile(r"\s+[-—–|]\s+")
_MORE_PAGES = re.compile(r"\s+(and \d+ more pages?|и ещё \d+ стр\S*)$", re.IGNORECASE)
_BROWSER_NAMES = ("google chrome", "chrome", "mozilla firefox", "firefox", "microsoft edge", "microsoft​ edge",
                  "opera", "brave", "vivaldi", "яндекс браузер", "yandex browser", "яндекс")


def is_browser(app: str) -> bool:
    name = app.lower()
    return any(b in name for b in BROWSERS)


def clean_title(title: str) -> str:
    """«Laravel — Docs - Google Chrome» → «Laravel — Docs». Убирает название браузера и профиля в конце."""
    title = title.strip()
    parts = _SEPARATOR.split(title)
    if len(parts) > 1 and any(n in parts[-1].lower() for n in _BROWSER_NAMES):
        parts = parts[:-1]
        # Edge: «Страница - Личный - Microsoft Edge» — профиль из одного-двух слов перед названием браузера
        if len(parts) > 1 and len(parts[-1].split()) <= 2 and len(parts[-1]) <= 20 and parts[-1].lower() in _PROFILES:
            parts = parts[:-1]
        sep = _SEPARATOR.findall(title)
        title = parts[0] + "".join(s + p for s, p in zip(sep, parts[1:]))
    return _MORE_PAGES.sub("", title).strip()


_PROFILES = {"personal", "личный", "work", "рабочий", "работа", "profile 1", "профиль 1", "гость", "guest"}


def category_hint(title: str) -> str:
    """Категория вкладки по ключевым словам или «» — тогда сайт отнесёт браузер к «Прочему»."""
    text = " " + title.lower()
    for hint, needles in HINT_RULES:
        if any(n in text for n in needles):
            return hint
    return ""
