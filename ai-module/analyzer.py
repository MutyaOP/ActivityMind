"""
AI-анализатор продуктивности (Pandas + Scikit-learn).

Получает от сайта агрегаты по дням и возвращает до трёх советов.
Каждый совет — это результат конкретного статистического метода, а не шаблонная фраза:

  trend_up / trend_down   линейная регрессия (scikit-learn) по объёму фокуса за дни
  weekday_weak / strong   группировка по дням недели (pandas)
  unstable_rhythm         коэффициент вариации по активным дням
  habits_boost            сравнение фокуса в дни с высоким и низким выполнением привычек
  time_without_results    много времени и почти нет закрытых задач

Модуль не знает ничего о пользователе: только числа. Вся логика — в чистых функциях без побочных эффектов.
"""
from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression

COLUMNS = ["focus_sec", "tracked_sec", "tasks_done", "habits_done", "habits_total", "score"]
TYPES = {"focus", "break", "habit", "planning", "other"}

MAX_DAYS = 400
MIN_DAYS_FOR_TREND = 7
MIN_ACTIVE_DAYS = 5
MAX_INSIGHTS = 3

WEEKDAYS_DAT = ["понедельникам", "вторникам", "средам", "четвергам", "пятницам", "субботам", "воскресеньям"]
WEEKDAYS_NOM = ["понедельник", "вторник", "среда", "четверг", "пятница", "суббота", "воскресенье"]


# --------------------------------------------------------------------------- разбор входа

def parse_days(payload: dict) -> pd.DataFrame:
    """Превращает {"days": {дата: метрики}} в DataFrame с индексом-датой. ValueError при неверном формате."""
    if not isinstance(payload, dict):
        raise ValueError("Тело запроса должно быть JSON-объектом.")

    days = payload.get("days")
    if not isinstance(days, dict) or not days:
        raise ValueError("Поле 'days' должно быть непустым объектом {дата: метрики}.")
    if len(days) > MAX_DAYS:
        raise ValueError(f"Слишком много дней: максимум {MAX_DAYS}.")

    rows = []
    for key, metrics in days.items():
        try:
            date = pd.Timestamp(key)
        except (ValueError, TypeError):
            raise ValueError(f"Некорректная дата: {key!r}.") from None
        if not isinstance(metrics, dict):
            raise ValueError(f"Метрики за {key} должны быть объектом.")

        row = {"date": date}
        for col in COLUMNS:
            value = metrics.get(col, 0)
            if isinstance(value, bool) or not isinstance(value, (int, float)) or value != value or value < 0:
                raise ValueError(f"Некорректное значение {col} за {key}.")
            row[col] = float(value)
        rows.append(row)

    df = pd.DataFrame(rows).set_index("date").sort_index()

    # Последний день в запросе сайта — сегодняшний, он ещё не закончился и исказил бы статистику.
    if not payload.get("include_last_day", False):
        df = df.iloc[:-1]

    df["focus_h"] = df["focus_sec"] / 3600.0
    df["habit_ratio"] = (df["habits_done"] / df["habits_total"]).where(df["habits_total"] > 0)
    return df


# --------------------------------------------------------------------------- методы

def _fmt(x: float, digits: int = 1) -> str:
    return f"{x:.{digits}f}".replace(".", ",")


def _trend(df: pd.DataFrame) -> list[dict]:
    n = len(df)
    if n < MIN_DAYS_FOR_TREND:
        return []

    x = np.arange(n).reshape(-1, 1)
    y = df["focus_h"].to_numpy()
    model = LinearRegression().fit(x, y)
    slope = float(model.coef_[0])          # часов в день
    r2 = float(model.score(x, y))
    total_change = slope * (n - 1)         # изменение за весь период, ч
    mean = max(float(y.mean()), 0.25)

    # Тренд считается значимым, только если он заметен и модель объясняет данные
    if r2 < 0.25 or abs(total_change) < 0.5 or abs(total_change) / mean < 0.3:
        return []

    minutes = round(abs(slope) * 60)
    quality = f"линейная регрессия по {n} дням, R² = {_fmt(r2, 2)}"
    if slope > 0:
        return [{
            "key": "trend_up", "type": "focus", "priority": 30,
            "text": f"Фокус растёт примерно на {minutes} мин в день ({quality}). "
                    "Зафиксируйте, что помогает в эти дни — время, место, формат задач, — и повторяйте.",
        }]
    return [{
        "key": "trend_down", "type": "focus", "priority": 70,
        "text": f"Фокус снижается примерно на {minutes} мин в день ({quality}). "
                "Проверьте причины: перегрузка, сбитый режим или слишком крупные задачи. "
                "Вернуться в ритм проще, начав с одной сессии по 25–30 минут.",
    }]


def _weekdays(df: pd.DataFrame) -> list[dict]:
    overall = float(df["focus_h"].mean())
    if len(df) < 10 or overall < 0.5:
        return []

    g = df.groupby(df.index.dayofweek)["focus_h"].agg(["mean", "count"])
    g = g[g["count"] >= 2]
    if len(g) < 4:
        return []

    out = []
    weak, strong = int(g["mean"].idxmin()), int(g["mean"].idxmax())

    if g.loc[weak, "mean"] <= 0.6 * overall:
        pct = round((1 - g.loc[weak, "mean"] / overall) * 100)
        out.append({
            "key": f"weekday_weak_{weak}", "type": "planning", "priority": 60,
            "text": f"По {WEEKDAYS_DAT[weak]} ваш фокус в среднем на {pct}% ниже обычного. "
                    "Планируйте на эти дни меньше сложных задач, а важное переносите на сильные дни.",
        })

    if g.loc[strong, "mean"] >= 1.4 * overall and strong != weak:
        pct = round((g.loc[strong, "mean"] / overall - 1) * 100)
        out.append({
            "key": f"weekday_strong_{strong}", "type": "focus", "priority": 20,
            "text": f"Ваш лучший день — {WEEKDAYS_NOM[strong]}: фокус в среднем на {pct}% выше обычного. "
                    "Ставьте на него самые сложные задачи.",
        })
    return out


def _consistency(active: pd.DataFrame) -> list[dict]:
    if len(active) < 6:
        return []
    mean = float(active["focus_h"].mean())
    if mean < 0.5:
        return []
    cv = float(active["focus_h"].std(ddof=1)) / mean
    if cv < 0.8:
        return []
    return [{
        "key": "unstable_rhythm", "type": "planning", "priority": 50,
        "text": f"Объём работы по дням сильно скачет: от {_fmt(active['focus_h'].min())} до {_fmt(active['focus_h'].max())} ч. "
                "Стабильная ежедневная планка (например, 45 минут) даёт больше, чем редкие марафоны.",
    }]


def _habits_boost(df: pd.DataFrame) -> list[dict]:
    sub = df[df["habit_ratio"].notna()]
    if len(sub) < 8:
        return []
    high = sub[sub["habit_ratio"] >= 0.5]["focus_h"]
    low = sub[sub["habit_ratio"] < 0.5]["focus_h"]
    if len(high) < 3 or len(low) < 3:
        return []

    mh, ml = float(high.mean()), float(low.mean())
    if ml <= 0 or mh < ml * 1.25 or (mh - ml) < 0.5:
        return []

    pct = round((mh / ml - 1) * 100)
    return [{
        "key": "habits_boost", "type": "habit", "priority": 40,
        "text": f"В дни, когда вы выполняете больше половины привычек, фокус в среднем на {pct}% выше "
                f"({_fmt(mh)} ч против {_fmt(ml)} ч). Привычки работают как разгон — сохраняйте их даже в загруженные дни.",
    }]


def _time_without_results(active: pd.DataFrame) -> list[dict]:
    if len(active) < 7 or active["focus_h"].sum() < 10:
        return []
    if (active["tasks_done"] == 0).mean() < 0.7:
        return []
    return [{
        "key": "time_without_results", "type": "planning", "priority": 45,
        "text": "Вы много работаете, но почти не закрываете задачи. Разбейте крупные задачи на шаги по 30–60 минут "
                "и отмечайте каждый выполненный шаг — прогресс станет заметнее.",
    }]


# --------------------------------------------------------------------------- публичный интерфейс

def collect_insights(payload: dict) -> list[dict]:
    """Все найденные выводы (до отбора лучших), с приоритетами."""
    df = parse_days(payload)
    active = df[df["focus_h"] > 0]
    if len(active) < MIN_ACTIVE_DAYS:
        return []

    found = []
    for method in (_trend, _weekdays, _habits_boost):
        found += method(df)
    found += _consistency(active)
    found += _time_without_results(active)
    return found


def analyze(payload: dict) -> list[dict]:
    """До трёх самых важных советов в формате, который ждёт сайт: [{key, type, text}]."""
    insights = sorted(collect_insights(payload), key=lambda i: i["priority"], reverse=True)
    return [{"key": i["key"], "type": i["type"], "text": i["text"]} for i in insights[:MAX_INSIGHTS]]
