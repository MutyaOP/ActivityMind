import os
import sys
import unittest
from datetime import date

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import simulate  # noqa: E402


class SimulateTest(unittest.TestCase):
    DAY = date(2026, 11, 10)

    def test_is_deterministic(self):
        a = simulate.day_sessions(self.DAY)
        b = simulate.day_sessions(self.DAY)
        self.assertEqual(a, b)

    def test_sessions_do_not_overlap(self):
        sessions = simulate.day_sessions(self.DAY)
        for prev, nxt in zip(sessions, sessions[1:]):
            end_prev = prev.started_at.timestamp() + prev.duration_sec
            self.assertLessEqual(end_prev, nxt.started_at.timestamp())

    def test_working_day_in_local_time(self):
        sessions = simulate.day_sessions(self.DAY, tz_offset_hours=5)
        self.assertEqual(sessions[0].started_at.hour, 4)  # 09:xx местного = 04:xx UTC

    def test_titles_hidden_unless_enabled(self):
        self.assertTrue(all(s.window_title == "" for s in simulate.day_sessions(self.DAY, track_titles=False)))
        self.assertTrue(any(s.window_title for s in simulate.day_sessions(self.DAY, track_titles=True)))

    def test_history_covers_requested_days_and_is_sorted(self):
        h = simulate.history(3, today=date(2026, 11, 15))
        days = {s.started_at.date() for s in h}
        self.assertEqual(days, {date(2026, 11, 12), date(2026, 11, 13), date(2026, 11, 14)})
        self.assertEqual(h, sorted(h, key=lambda s: s.started_at))

    def test_contains_idle_and_active_sessions(self):
        h = simulate.history(14, today=date(2026, 11, 15))
        self.assertTrue(any(s.is_idle for s in h))
        self.assertTrue(any(not s.is_idle for s in h))


if __name__ == "__main__":
    unittest.main()
