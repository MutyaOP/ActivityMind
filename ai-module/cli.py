"""Запуск анализа из файла без сервера:  python cli.py examples/sample_request.json"""
from __future__ import annotations

import json
import sys

from analyzer import analyze


def main(argv: list[str]) -> int:
    # Консоль Windows по умолчанию в cp1251: без этого падает на «R²» и других символах
    sys.stdout.reconfigure(encoding="utf-8")
    if len(argv) != 2:
        print("Использование: python cli.py запрос.json")
        return 1
    with open(argv[1], encoding="utf-8") as f:
        payload = json.load(f)
    try:
        insights = analyze(payload)
    except ValueError as exc:
        print(f"Ошибка: {exc}")
        return 2

    if not insights:
        print("Недостаточно данных для выводов (нужно минимум 5 активных дней).")
        return 0
    for item in insights:
        print(f"[{item['type']}] ({item['key']}) {item['text']}\n")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
