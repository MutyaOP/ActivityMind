import os
import sys
import unittest
from datetime import datetime, timedelta, timezone

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from session_builder import SessionBuilder  # noqa: E402

T0 = datetime(2026, 11, 15, 10, 0, 0, tzinfo=timezone.utc)


def at(seconds: int) -> datetime:
    return T0 + timedelta(seconds=seconds)


class SessionBuilderTest(unittest.TestCase):
    def make(self, **kw):
        return SessionBuilder(min_session_sec=kw.get("min", 3), max_session_sec=kw.get("max", 3600), max_gap_sec=kw.get("gap", 30))

    def test_switching_app_closes_previous_session(self):
        b = self.make()
        self.assertEqual(b.feed(at(0), "Code", "", False), [])
        self.assertEqual(b.feed(at(5), "Code", "", False), [])
        out = b.feed(at(10), "chrome", "", False)

        self.assertEqual(len(out), 1)
        self.assertEqual(out[0].app_name, "Code")
        self.assertEqual(out[0].duration_sec, 10)
        self.assertEqual(out[0].started_at, T0)
        self.assertFalse(out[0].is_idle)

    def test_short_blips_are_dropped(self):
        b = self.make(min=3)
        b.feed(at(0), "Code", "", False)
        self.assertEqual(b.feed(at(2), "chrome", "", False), [])  # 2 с в Code < 3 с

    def test_title_change_starts_new_session(self):
        b = self.make()
        b.feed(at(0), "chrome", "Страница A", False)
        out = b.feed(at(10), "chrome", "Страница B", False)
        self.assertEqual([s.window_title for s in out], ["Страница A"])

    def test_idle_is_separate_session(self):
        b = self.make()
        b.feed(at(0), "Code", "", False)
        out = b.feed(at(10), "Простой", "", True)
        self.assertEqual(out[0].app_name, "Code")
        out = b.feed(at(40), "Code", "", False)
        self.assertTrue(out[0].is_idle)
        self.assertEqual(out[0].duration_sec, 30)

    def test_gap_closes_session_at_last_seen_not_now(self):
        b = self.make(gap=30)
        b.feed(at(0), "Code", "", False)
        b.feed(at(5), "Code", "", False)
        out = b.feed(at(3600), "Code", "", False)  # компьютер «спал» почти час

        self.assertEqual(len(out), 1)
        self.assertEqual(out[0].duration_sec, 5)  # а не 3600

    def test_long_session_is_split(self):
        b = self.make(max=60)
        outs = []
        for s in range(0, 130, 5):
            outs += b.feed(at(s), "Code", "", False)
        self.assertEqual([o.duration_sec for o in outs], [60, 60])

    def test_flush_returns_current_session(self):
        b = self.make()
        b.feed(at(0), "Code", "", False)
        b.feed(at(10), "Code", "", False)
        out = b.flush(at(20))
        self.assertEqual(out[0].duration_sec, 20)
        self.assertEqual(b.flush(at(30)), [])  # повторный flush ничего не даёт

    def test_flush_without_data(self):
        self.assertEqual(self.make().flush(at(0)), [])


if __name__ == "__main__":
    unittest.main()
