"""
AI-анализатор продуктивности (Pandas + Scikit-learn).

Получает от сайта агрегаты по дням и возвращает до трёх советов.
Каждый совет — это результат конкретного статистического метода, а не шаблонная фраза:

  trend_up / trend_down   линейная регрессия (scikit-learn) по объёму фокуса за дни
  weekday_weak / strong   группировка по дням недели (pandas)
  unstable_rhythm         коэффициент вариации по активным дням
  habits_boost            сравнение фокуса в дни с высоким и низким выполнением привычек
  time_without_results    много времени и почти нет закрытых задач
  peak_hours / late_work  распределение фокуса по часам суток (скользящее окно 3 ч)
  distraction             доля развлечений во времени за компьютером (данные Desktop Agent)
  frequent_switching      частота переключений между программами в час (данные Desktop Agent)
  day_clusters            кластеризация дней KMeans (scikit-learn), число кластеров — по силуэту

Модуль не знает ничего о пользователе: только числа. Вся логика — в чистых функциях без побочных эффектов.
"""
from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.linear_model import LinearRegression
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

# productive_sec / rest_sec / switches — данные Desktop Agent: время в рабочих/учебных и в развлекательных
# программах и число переключений между программами за день. Необязательны (по умолчанию 0).
COLUMNS = ["focus_sec", "tracked_sec", "tasks_done", "habits_done", "habits_total", "score",
           "productive_sec", "rest_sec", "switches"]
TYPES = {"focus", "break", "habit", "planning", "other"}

MAX_DAYS = 400
MIN_DAYS_FOR_TREND = 7
MIN_ACTIVE_DAYS = 5
MAX_INSIGHTS = 3
MIN_DAYS_FOR_CLUSTERS = 14
MIN_HOURS_FOR_PEAK = 10.0

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


def parse_hours(payload: dict) -> np.ndarray | None:
    """Часы фокуса по часам суток (24 числа). None, если сайт их не передал."""
    hours = payload.get("hours")
    if hours is None:
        return None
    if not isinstance(hours, list) or len(hours) != 24:
        raise ValueError("Поле 'hours' должно быть списком из 24 чисел.")
    for v in hours:
        if isinstance(v, bool) or not isinstance(v, (int, float)) or v != v or v < 0:
            raise ValueError("Поле 'hours' должно содержать неотрицательные числа.")
    return np.asarray(hours, dtype=float)


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

    g = df.groupby(df.index.day_of_week)["focus_h"].agg(["mean", "count"])
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


def _hours(hours: np.ndarray | None, days: int) -> list[dict]:
    if hours is None:
        return []
    total = float(hours.sum())
    if total < MIN_HOURS_FOR_PEAK:
        return []

    out = []
    windows = [float(hours[s:s + 3].sum()) for s in range(22)]
    start = int(np.argmax(windows))
    share = windows[start] / total
    # При равномерной работе с 9 до 19 на любые 3 часа приходится ~30%, поэтому пиком считаем от 40%
    if share >= 0.4:
        out.append({
            "key": "peak_hours", "type": "focus", "priority": 55,
            "text": f"Пик вашего фокуса — с {start:02d}:00 до {start + 3:02d}:00: на эти три часа приходится "
                    f"{round(share * 100)}% всего времени за {days} дн. Ставьте самые сложные задачи в это окно, "
                    "а почту и мелкие дела — на остальное время.",
        })

    late = float(hours[22:].sum() + hours[:5].sum())
    if late >= 3 and late / total >= 0.2:
        out.append({
            "key": "late_work", "type": "break", "priority": 65,
            "text": f"Около {round(late / total * 100)}% фокуса приходится на время с 22:00 до 05:00. "
                    "Поздняя работа сбивает сон и снижает фокус на следующий день — попробуйте переносить её на утро.",
        })
    return out


def _agent(df: pd.DataFrame) -> list[dict]:
    ag = df[df["tracked_sec"] > 0]
    tracked_h = float(ag["tracked_sec"].sum()) / 3600
    if len(ag) < MIN_ACTIVE_DAYS or tracked_h < 10:
        return []

    out = []
    rest_share = float(ag["rest_sec"].sum()) / 3600 / tracked_h
    if rest_share >= 0.3:
        per_day = float(ag["rest_sec"].mean()) / 3600
        out.append({
            "key": "distraction", "type": "break", "priority": 58,
            "text": f"По данным агента {round(rest_share * 100)}% времени за компьютером уходит на развлечения "
                    f"(видео, соцсети, игры) — в среднем {_fmt(per_day)} ч в день. "
                    "Закрывайте такие вкладки на время фокус-сессий и оставляйте их на перерывы.",
        })

    rate = float(ag["switches"].sum()) / tracked_h
    if rate >= 20:
        out.append({
            "key": "frequent_switching", "type": "focus", "priority": 52,
            "text": f"Вы переключаетесь между программами примерно {round(rate)} раз в час. Частые переключения "
                    "дробят внимание: на время сессии закройте лишние окна и отключите уведомления.",
        })

    productive_share = float(ag["productive_sec"].sum()) / 3600 / tracked_h
    if not out and productive_share >= 0.75:
        out.append({
            "key": "good_balance", "type": "other", "priority": 15,
            "text": f"Хороший баланс: {round(productive_share * 100)}% времени за компьютером — работа и учёба, "
                    "а развлечения занимают немного. Так держать.",
        })
    return out


# Признаки, которыми объясняется разница «самые продуктивные дни против остальных»
_CLUSTER_FEATURES = {
    "habit_ratio": lambda a, b: f"выполняете {round(a * 100)}% привычек против {round(b * 100)}%",
    "tasks_done": lambda a, b: f"закрываете в среднем {_fmt(a)} задачи против {_fmt(b)}",
    "rest_share": lambda a, b: f"тратите на развлечения {round(a * 100)}% времени за компьютером против {round(b * 100)}%",
    "switch_rate": lambda a, b: f"переключаетесь между программами {round(a)} раз в час против {round(b)}",
}


def day_features(df: pd.DataFrame) -> pd.DataFrame:
    """Признаки активных дней для кластеризации (только колонки, по которым есть данные и разброс)."""
    active = df[df["focus_h"] > 0]
    feats = pd.DataFrame(index=active.index)
    feats["focus_h"] = active["focus_h"]
    feats["tasks_done"] = active["tasks_done"]
    if active["habit_ratio"].notna().sum() >= len(active) / 2:
        feats["habit_ratio"] = active["habit_ratio"].fillna(active["habit_ratio"].mean())
    tracked = active["tracked_sec"]
    if (tracked > 0).sum() >= len(active) / 2:
        safe = tracked.where(tracked > 0)
        feats["rest_share"] = (active["rest_sec"] / safe).fillna(0.0)
        feats["switch_rate"] = (active["switches"] / (safe / 3600)).fillna(0.0)
    return feats.loc[:, feats.std(ddof=0) > 0]


def _day_clusters(df: pd.DataFrame) -> list[dict]:
    """
    KMeans делит активные дни на 2 или 3 типа (выбирается вариант с лучшим силуэтом).
    Затем кластер с наибольшим фокусом сравнивается с остальными днями: в совет попадают
    признаки с самой большой стандартизированной разницей.
    """
    feats = day_features(df)
    if len(feats) < MIN_DAYS_FOR_CLUSTERS or "focus_h" not in feats or feats.shape[1] < 2:
        return []

    x = StandardScaler().fit_transform(feats.to_numpy())
    best = None
    for k in (2, 3):
        labels = KMeans(n_clusters=k, n_init=10, random_state=0).fit_predict(x)
        if np.bincount(labels).min() < 3:
            continue
        sil = float(silhouette_score(x, labels))
        if best is None or sil > best[0]:
            best = (sil, k, labels)
    if best is None or best[0] < 0.2:
        return []

    sil, k, labels = best
    scaled = pd.DataFrame(x, index=feats.index, columns=feats.columns)
    top = int(feats.groupby(labels)["focus_h"].mean().idxmax())
    in_top = labels == top

    diffs = []
    for col, phrase in _CLUSTER_FEATURES.items():
        if col not in feats:
            continue
        d = float(scaled.loc[in_top, col].mean() - scaled.loc[~in_top, col].mean())
        if abs(d) >= 0.8:
            diffs.append((abs(d), phrase(float(feats.loc[in_top, col].mean()), float(feats.loc[~in_top, col].mean()))))
    if not diffs:
        return []

    reasons = " и ".join(p for _, p in sorted(diffs, reverse=True)[:2])
    top_h, other_h = float(feats.loc[in_top, "focus_h"].mean()), float(feats.loc[~in_top, "focus_h"].mean())
    kinds = {2: "два типа", 3: "три типа"}[k]
    return [{
        "key": "day_clusters", "type": "planning", "priority": 48,
        "text": f"Модель KMeans разделила ваши {len(feats)} активных дней на {kinds} (силуэт {_fmt(sil, 2)}). "
                f"В самых продуктивных днях ({int(in_top.sum())} дн., {_fmt(top_h)} ч фокуса против {_fmt(other_h)} ч) "
                f"вы {reasons}. Старайтесь повторять эти условия.",
    }]


# --------------------------------------------------------------------------- публичный интерфейс

def collect_insights(payload: dict) -> list[dict]:
    """Все найденные выводы (до отбора лучших), с приоритетами."""
    df = parse_days(payload)
    hours = parse_hours(payload)
    active = df[df["focus_h"] > 0]
    if len(active) < MIN_ACTIVE_DAYS:
        return []

    found = []
    for method in (_trend, _weekdays, _habits_boost):
        found += method(df)
    found += _consistency(active)
    found += _time_without_results(active)
    found += _hours(hours, len(df))
    found += _agent(df)
    found += _day_clusters(df)
    return found


def analyze(payload: dict) -> list[dict]:
    """До трёх самых важных советов в формате, который ждёт сайт: [{key, type, text}]."""
    insights = sorted(collect_insights(payload), key=lambda i: i["priority"], reverse=True)
    return [{"key": i["key"], "type": i["type"], "text": i["text"]} for i in insights[:MAX_INSIGHTS]]
