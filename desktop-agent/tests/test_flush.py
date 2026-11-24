import os
import sys
import unittest
from datetime import datetime, timedelta, timezone

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import agent  # noqa: E402
from session_builder import Session  # noqa: E402
from storage import Buffer  # noqa: E402
from sync_client import AuthError, RejectedError, TransientError  # noqa: E402

T0 = datetime(2026, 11, 15, 10, 0, 0, tzinfo=timezone.utc)


class FakeClient:
    """Ведёт себя по сценарию: список ответов/исключений на каждый send()."""

    def __init__(self, script):
        self.script = list(script)
        self.sent = []

    def send(self, rows):
        self.sent.append([r["app_name"] for r in rows])
        step = self.script.pop(0) if self.script else {"accepted": len(rows), "duplicates": 0}
        if isinstance(step, Exception):
            raise step
        return step


def filled(n=3):
    buf = Buffer(":memory:")
    buf.add([Session(f"app{i}", "", T0 + timedelta(minutes=i), 60, False) for i in range(n)])
    return buf


class FlushTest(unittest.TestCase):
    def test_empty_buffer_is_ok(self):
        self.assertEqual(agent.flush_buffer(FakeClient([]), Buffer(":memory:"), 10), "ok")

    def test_success_marks_everything_sent(self):
        buf = filled(3)
        self.assertEqual(agent.flush_buffer(FakeClient([]), buf, 10), "ok")
        self.assertEqual(buf.pending_count(), 0)

    def test_batches_respect_batch_size(self):
        buf = filled(5)
        client = FakeClient([])
        agent.flush_buffer(client, buf, 2)
        self.assertEqual([len(b) for b in client.sent], [2, 2, 1])

    def test_network_failure_keeps_data(self):
        buf = filled(3)
        self.assertEqual(agent.flush_buffer(FakeClient([TransientError("нет сети")]), buf, 10), "retry")
        self.assertEqual(buf.pending_count(), 3)

    def test_auth_failure_is_fatal_and_keeps_data(self):
        buf = filled(3)
        self.assertEqual(agent.flush_buffer(FakeClient([AuthError()]), buf, 10), "fatal")
        self.assertEqual(buf.pending_count(), 3)

    def test_rejected_batch_is_split_and_only_bad_row_dropped(self):
        buf = filled(3)
        # пачка отклонена → по одной: app0 ok, app1 отклонён, app2 ok
        script = [RejectedError("пачка"), {"accepted": 1}, RejectedError("app1"), {"accepted": 1}]
        client = FakeClient(script)

        self.assertEqual(agent.flush_buffer(client, buf, 10), "ok")
        self.assertEqual(buf.pending_count(), 0)
        self.assertEqual(client.sent[0], ["app0", "app1", "app2"])
        self.assertEqual(client.sent[1:], [["app0"], ["app1"], ["app2"]])


if __name__ == "__main__":
    unittest.main()
