"""Тесты новых методов: часы суток, данные Desktop Agent, кластеризация дней KMeans."""
import random
import unittest

from helpers import make_payload  # noqa: E402

import analyzer  # noqa: E402


def keys(payload):
    return [i["key"] for i in analyzer.collect_insights(payload)]


def hours_profile(**by_hour):
    h = [0.0] * 24
    for k, v in by_hour.items():
        h[int(k[1:])] = v
    return h


def with_agent(payload, productive, rest, switches):
    """Дописывает в каждый день данные агента (часы и число переключений)."""
    for i, day in enumerate(payload["days"].values()):
        day["productive_sec"] = int(productive[i] * 3600)
        day["rest_sec"] = int(rest[i] * 3600)
        day["tracked_sec"] = day["productive_sec"] + day["rest_sec"]
        day["switches"] = switches[i]
    return payload


class HoursTest(unittest.TestCase):
    STEADY = [3] * 15

    def test_peak_window_is_found(self):
        h = hours_profile(h9=4, h10=12, h11=11, h12=10, h14=4, h15=3, h16=3, h17=2)
        payload = make_payload(self.STEADY, hours=h)
        insight = next(i for i in analyzer.collect_insights(payload) if i["key"] == "peak_hours")
        self.assertIn("с 10:00 до 13:00", insight["text"])

    def test_no_peak_when_work_is_spread_evenly(self):
        h = hours_profile(**{f"h{x}": 4 for x in range(9, 19)})
        self.assertNotIn("peak_hours", keys(make_payload(self.STEADY, hours=h)))

    def test_too_little_data_gives_nothing(self):
        h = hours_profile(h10=3, h11=3)
        self.assertNotIn("peak_hours", keys(make_payload(self.STEADY, hours=h)))

    def test_late_work_is_detected(self):
        h = hours_profile(h10=6, h11=6, h14=6, h22=4, h23=5, h0=3)
        self.assertIn("late_work", keys(make_payload(self.STEADY, hours=h)))

    def test_hours_are_optional(self):
        payload = make_payload(self.STEADY)
        del payload["hours"]
        self.assertIsInstance(analyzer.analyze(payload), list)

    def test_invalid_hours_raise(self):
        for bad in ([1] * 23, "24", [-1] + [0] * 23, [True] * 24):
            with self.assertRaises(ValueError):
                analyzer.analyze(make_payload(self.STEADY, hours=bad))


class AgentTest(unittest.TestCase):
    N = 12

    def test_distraction_is_detected(self):
        p = with_agent(make_payload([2] * self.N), [3] * self.N, [2] * self.N, [40] * self.N)
        self.assertIn("distraction", keys(p))

    def test_frequent_switching_is_detected(self):
        p = with_agent(make_payload([2] * self.N), [4] * self.N, [0.5] * self.N, [150] * self.N)
        found = keys(p)
        self.assertIn("frequent_switching", found)
        self.assertNotIn("distraction", found)

    def test_good_balance_when_nothing_is_wrong(self):
        p = with_agent(make_payload([2] * self.N), [5] * self.N, [0.3] * self.N, [30] * self.N)
        self.assertEqual([k for k in keys(p) if k in {"distraction", "frequent_switching", "good_balance"}],
                         ["good_balance"])

    def test_no_agent_data_no_agent_insights(self):
        found = keys(make_payload([2] * self.N))
        self.assertFalse({"distraction", "frequent_switching", "good_balance"} & set(found))


class ClustersTest(unittest.TestCase):
    def build(self, n=28, seed=1):
        """Два явных типа дней: «хорошие» (много фокуса, привычки, мало развлечений) и «рассеянные»."""
        rnd = random.Random(seed)
        focus, habits, prod, rest, sw = [], [], [], [], []
        for i in range(n):
            good = i % 2 == 0
            focus.append(rnd.uniform(4, 5) if good else rnd.uniform(0.8, 1.5))
            habits.append(rnd.choice([0.75, 1.0]) if good else rnd.choice([0.0, 0.25]))
            prod.append(rnd.uniform(5, 6) if good else rnd.uniform(2, 3))
            rest.append(rnd.uniform(0.2, 0.5) if good else rnd.uniform(2, 3))
            sw.append(rnd.randint(30, 50) if good else rnd.randint(120, 160))
        payload = make_payload(focus, habits=habits)
        return with_agent(payload, prod, rest, sw)

    def test_productive_cluster_is_described(self):
        insight = next(i for i in analyzer.collect_insights(self.build()) if i["key"] == "day_clusters")
        self.assertIn("KMeans", insight["text"])
        self.assertIn("против", insight["text"])

    def test_result_is_deterministic(self):
        a = [i["text"] for i in analyzer.collect_insights(self.build())]
        b = [i["text"] for i in analyzer.collect_insights(self.build())]
        self.assertEqual(a, b)

    def test_needs_enough_days(self):
        self.assertNotIn("day_clusters", keys(self.build(n=10)))

    def test_uniform_days_give_no_cluster_insight(self):
        p = make_payload([2.0, 2.1, 1.9, 2.0] * 6, habits=[0.5] * 24)
        self.assertNotIn("day_clusters", keys(p))

    def test_features_skip_columns_without_data(self):
        df = analyzer.parse_days(make_payload([1, 2, 3, 4, 5]))
        cols = set(analyzer.day_features(df).columns)
        self.assertNotIn("rest_share", cols)
        self.assertNotIn("habit_ratio", cols)


class OutputTest(unittest.TestCase):
    def test_never_more_than_three_insights(self):
        p = ClustersTest().build()
        p["hours"] = hours_profile(h10=30, h11=30, h12=20, h22=20, h23=20)
        self.assertLessEqual(len(analyzer.analyze(p)), 3)


if __name__ == "__main__":
    unittest.main()
