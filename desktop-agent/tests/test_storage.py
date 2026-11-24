import os
import sys
import unittest
from datetime import datetime, timedelta, timezone

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from session_builder import Session  # noqa: E402
from storage import Buffer  # noqa: E402

T0 = datetime(2026, 11, 15, 10, 0, 0, tzinfo=timezone.utc)


class BufferTest(unittest.TestCase):
    def setUp(self):
        self.buf = Buffer(":memory:")

    def test_add_and_pending_in_time_order(self):
        self.buf.add([
            Session("chrome", "", T0 + timedelta(minutes=10), 60, False),
            Session("Code", "main.php", T0, 600, False),
        ])
        rows = self.buf.pending()
        self.assertEqual([r["app_name"] for r in rows], ["Code", "chrome"])
        self.assertEqual(rows[0]["started_at"], "2026-11-15T10:00:00Z")
        self.assertEqual(rows[0]["duration_sec"], 600)
        self.assertFalse(rows[0]["is_idle"])

    def test_duplicates_are_ignored(self):
        s = Session("Code", "", T0, 60, False)
        self.buf.add([s])
        self.buf.add([s])
        self.assertEqual(self.buf.pending_count(), 1)

    def test_mark_sent_removes_from_pending(self):
        self.buf.add([Session("Code", "", T0, 60, False), Session("chrome", "", T0 + timedelta(minutes=5), 60, False)])
        first = self.buf.pending()[0]["id"]
        self.buf.mark_sent([first])
        self.assertEqual(self.buf.pending_count(), 1)

    def test_pending_respects_limit(self):
        self.buf.add([Session("A", "", T0 + timedelta(minutes=i), 60, False) for i in range(5)])
        self.assertEqual(len(self.buf.pending(limit=3)), 3)

    def test_purge_removes_only_old_sent(self):
        old = Session("Old", "", datetime.now(timezone.utc) - timedelta(days=30), 60, False)
        recent = Session("Recent", "", datetime.now(timezone.utc) - timedelta(hours=1), 60, False)
        self.buf.add([old, recent])
        self.buf.mark_sent([r["id"] for r in self.buf.pending()])
        self.buf.purge_sent(days=7)
        left = self.buf.conn.execute("SELECT app_name FROM sessions").fetchall()
        self.assertEqual(left, [("Recent",)])

    def test_add_empty_is_noop(self):
        self.assertEqual(self.buf.add([]), 0)


if __name__ == "__main__":
    unittest.main()
