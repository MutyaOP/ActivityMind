import os
import sys
from datetime import date, timedelta

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


def make_payload(focus_hours, start=date(2026, 11, 2), tasks=None, habits=None, last_day_partial=True, **extra):
    """
    focus_hours — список часов по дням, начиная со start (по умолчанию понедельник).
    Последний элемент считается «сегодняшним» неполным днём (анализатор его отбрасывает).
    tasks / habits — списки того же размера: закрыто задач; доля выполненных привычек (0..1) из 4.
    """
    days = {}
    for i, h in enumerate(focus_hours):
        d = start + timedelta(days=i)
        done = 0
        total = 0
        if habits is not None:
            total = 4
            done = round(habits[i] * 4)
        days[d.isoformat()] = {
            "focus_sec": int(h * 3600),
            "tracked_sec": 0,
            "tasks_done": tasks[i] if tasks else 1,
            "habits_done": done,
            "habits_total": total,
            "score": 50,
        }
    payload = {"user_id": 1, "timezone": "Asia/Almaty", "days": days, "hours": [0] * 24}
    payload.update(extra)
    return payload
