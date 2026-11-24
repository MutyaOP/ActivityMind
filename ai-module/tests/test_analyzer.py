import unittest

from helpers import make_payload  # noqa: E402

import analyzer  # noqa: E402


def keys(payload):
    return [i["key"] for i in analyzer.collect_insights(payload)]


class ParseTest(unittest.TestCase):
    def test_last_day_is_excluded_by_default(self):
        df = analyzer.parse_days(make_payload([2, 2, 2, 2]))
        self.assertEqual(len(df), 3)

    def test_last_day_can_be_included(self):
        df = analyzer.parse_days(make_payload([2, 2, 2, 2], include_last_day=True))
        self.assertEqual(len(df), 4)

    def test_days_are_sorted_by_date(self):
        payload = make_payload([1, 2, 3, 4])
        payload["days"] = dict(reversed(list(payload["days"].items())))
        df = analyzer.parse_days(payload)
        self.assertTrue(df.index.is_monotonic_increasing)

    def test_missing_metrics_default_to_zero(self):
        df = analyzer.parse_days({"days": {"2026-11-02": {"focus_sec": 3600}, "2026-11-03": {}}})
        self.assertEqual(df["tasks_done"].iloc[0], 0)

    def test_invalid_payloads_raise_value_error(self):
        bad = [
            None, [], {}, {"days": {}}, {"days": []},
            {"days": {"не дата": {"focus_sec": 1}}},
            {"days": {"2026-11-02": {"focus_sec": -5}}},
            {"days": {"2026-11-02": {"focus_sec": "много"}}},
            {"days": {"2026-11-02": {"focus_sec": True}}},
            {"days": {"2026-11-02": "строка"}},
        ]
        for payload in bad:
            with self.subTest(payload=payload):
                with self.assertRaises(ValueError):
                    analyzer.parse_days(payload)


class TrendTest(unittest.TestCase):
    def test_declining_trend(self):
        hours = [5.0, 4.8, 4.2, 4.0, 3.5, 3.1, 2.8, 2.2, 2.0, 1.5, 1.2, 1.0, 0.8, 3.0]
        found = {i["key"]: i for i in analyzer.collect_insights(make_payload(hours))}
        self.assertIn("trend_down", found)
        self.assertIn("мин в день", found["trend_down"]["text"])
        self.assertIn("R²", found["trend_down"]["text"])
        self.assertEqual(found["trend_down"]["type"], "focus")

    def test_growing_trend(self):
        hours = [0.5, 0.8, 1.0, 1.5, 1.8, 2.2, 2.6, 3.0, 3.5, 3.8, 4.2, 4.6, 5.0, 1.0]
        self.assertIn("trend_up", keys(make_payload(hours)))

    def test_flat_series_has_no_trend(self):
        hours = [2.0, 2.1, 1.9, 2.0, 2.2, 1.8, 2.0, 2.1, 1.9, 2.0, 2.0, 2.1, 1.9, 2.0]
        found = keys(make_payload(hours))
        self.assertNotIn("trend_up", found)
        self.assertNotIn("trend_down", found)

    def test_short_series_has_no_trend(self):
        self.assertNotIn("trend_up", keys(make_payload([1, 2, 3, 4, 5, 6, 1])))

    def test_noisy_series_without_direction_has_no_trend(self):
        hours = [3, 0.5, 3, 0.6, 3, 0.4, 3, 0.5, 3, 0.6, 3, 0.5, 3, 2]
        found = keys(make_payload(hours))
        self.assertNotIn("trend_down", found)
        self.assertNotIn("trend_up", found)

    def test_today_does_not_distort_results(self):
        flat = [2.0] * 13
        excluded = keys(make_payload(flat + [12.0]))                        # сегодняшний выброс отброшен
        included = keys(make_payload(flat + [12.0], include_last_day=True))  # а если учесть — картина меняется

        self.assertEqual(excluded, [])
        self.assertIn("unstable_rhythm", included)


class WeekdayTest(unittest.TestCase):
    def test_weak_weekday(self):
        # 3 недели с понедельника: по пятницам (индекс 4) почти ничего
        hours = [3.0 if (i % 7) != 4 else 0.3 for i in range(21)]
        self.assertIn("weekday_weak_4", keys(make_payload(hours)))

    def test_strong_weekday(self):
        hours = [2.0 if (i % 7) != 2 else 6.0 for i in range(21)]
        self.assertIn("weekday_strong_2", keys(make_payload(hours)))

    def test_text_uses_correct_plural_form(self):
        hours = [3.0 if (i % 7) != 4 else 0.3 for i in range(21)]
        weak = next(i for i in analyzer.collect_insights(make_payload(hours)) if i["key"] == "weekday_weak_4")
        self.assertIn("пятницам", weak["text"])

    def test_even_week_has_no_weekday_insights(self):
        found = keys(make_payload([2.0] * 21))
        self.assertFalse([k for k in found if k.startswith("weekday")])


class OtherRulesTest(unittest.TestCase):
    def test_unstable_rhythm(self):
        hours = [0.5, 6, 0.4, 5.5, 0.6, 6.2, 0.5, 5.8, 0.4, 6, 0.5, 5.6, 0.6, 1]
        self.assertIn("unstable_rhythm", keys(make_payload(hours)))

    def test_stable_rhythm_is_not_flagged(self):
        self.assertNotIn("unstable_rhythm", keys(make_payload([2.0, 2.2, 1.9, 2.1] * 3 + [2.0, 2.0])))

    def test_habits_boost(self):
        hours = [4.0, 1.5] * 6 + [2.0, 2.0]
        habits = [1.0, 0.0] * 6 + [0.5, 0.5]
        found = {i["key"]: i for i in analyzer.collect_insights(make_payload(hours, habits=habits))}
        self.assertIn("habits_boost", found)
        self.assertEqual(found["habits_boost"]["type"], "habit")

    def test_no_habit_data_means_no_habit_insight(self):
        self.assertNotIn("habits_boost", keys(make_payload([4.0, 1.5] * 7)))

    def test_time_without_results(self):
        hours = [2.0] * 14
        tasks = [0] * 14
        self.assertIn("time_without_results", keys(make_payload(hours, tasks=tasks)))

    def test_results_present_means_no_such_insight(self):
        self.assertNotIn("time_without_results", keys(make_payload([2.0] * 14, tasks=[2] * 14)))


class AnalyzeTest(unittest.TestCase):
    HOURS = [5.0, 4.8, 4.2, 4.0, 3.5, 3.1, 2.8, 2.2, 2.0, 1.5, 1.2, 1.0, 0.8, 3.0]

    def test_not_enough_active_days_returns_nothing(self):
        self.assertEqual(analyzer.analyze(make_payload([1, 0, 0, 2, 0, 0, 0, 1])), [])

    def test_output_shape_and_limit(self):
        result = analyzer.analyze(make_payload(self.HOURS))
        self.assertTrue(1 <= len(result) <= analyzer.MAX_INSIGHTS)
        for item in result:
            self.assertEqual(set(item), {"key", "type", "text"})
            self.assertIn(item["type"], analyzer.TYPES)
            self.assertTrue(item["text"])

    def test_sorted_by_priority(self):
        # падение фокуса (70) важнее сильного дня недели (20)
        result = analyzer.analyze(make_payload(self.HOURS))
        self.assertEqual(result[0]["key"], "trend_down")

    def test_is_deterministic(self):
        payload = make_payload(self.HOURS)
        self.assertEqual(analyzer.analyze(payload), analyzer.analyze(payload))

    def test_keys_are_stable_across_small_changes(self):
        # ключ нужен сайту, чтобы не создавать один и тот же совет повторно при изменении цифр в тексте
        a = [i["key"] for i in analyzer.analyze(make_payload(self.HOURS))]
        shifted = [h + 0.05 for h in self.HOURS]
        b = [i["key"] for i in analyzer.analyze(make_payload(shifted))]
        self.assertEqual(a, b)

    def test_keys_are_safe_identifiers(self):
        import re
        for i in analyzer.collect_insights(make_payload(self.HOURS)):
            self.assertRegex(i["key"], r"^[a-z0-9_]{1,40}$")


if __name__ == "__main__":
    unittest.main()
