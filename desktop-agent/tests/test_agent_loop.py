import os
import sys
import tempfile
import unittest
from datetime import datetime, timedelta, timezone

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import agent  # noqa: E402
import browser  # noqa: E402
from config import Config  # noqa: E402
from control import Control  # noqa: E402
from instance_lock import InstanceLock  # noqa: E402
from session_builder import Session, SessionBuilder  # noqa: E402
from storage import Buffer  # noqa: E402

T0 = datetime(2026, 11, 25, 10, 0, 0, tzinfo=timezone.utc)


class IdleStartTest(unittest.TestCase):
    """Простой начинается с последнего ввода, а не с момента, когда истёк порог в 5 минут."""

    def test_break_minutes_are_not_counted_as_work(self):
        b = SessionBuilder(max_gap_sec=90)
        b.feed(T0, "Code", "", False)
        # последний ввод в 10:14, но пока порог в 300 с не истёк (до 10:19), окно ещё «работа»
        for m in range(1, 19):
            b.feed(T0 + timedelta(minutes=m), "Code", "", False)
        out = b.feed(T0 + timedelta(minutes=19), "Простой", "", True, idle_since=T0 + timedelta(minutes=14))

        self.assertEqual(out[0].duration_sec, 14 * 60)
        idle = b.flush(T0 + timedelta(minutes=30))[0]
        self.assertTrue(idle.is_idle)
        self.assertEqual(idle.started_at, T0 + timedelta(minutes=14))
        self.assertEqual(idle.duration_sec, 16 * 60)

    def test_idle_since_before_session_start_is_clamped(self):
        b = SessionBuilder()
        b.feed(T0, "Code", "", False)
        out = b.feed(T0 + timedelta(seconds=20), "Простой", "", True, idle_since=T0 - timedelta(minutes=10))
        self.assertEqual(out, [])  # сессия «съелась» до нуля и отброшена

    def test_current_session(self):
        b = SessionBuilder()
        self.assertIsNone(b.current())
        b.feed(T0, "Code", "", False)
        self.assertEqual(b.current(), ("Code", T0))
        b.feed(T0 + timedelta(seconds=10), "Простой", "", True, idle_since=T0 + timedelta(seconds=5))
        self.assertIsNone(b.current())


class BrowserTest(unittest.TestCase):
    def test_detects_browsers(self):
        self.assertTrue(browser.is_browser("chrome"))
        self.assertTrue(browser.is_browser("msedge"))
        self.assertFalse(browser.is_browser("Code"))

    def test_clean_title(self):
        self.assertEqual(browser.clean_title("Routing - Laravel 13.x - Google Chrome"), "Routing - Laravel 13.x")
        self.assertEqual(browser.clean_title("YouTube — Mozilla Firefox"), "YouTube")
        self.assertEqual(browser.clean_title("Почта и ещё 2 стр. - Личный - Microsoft​ Edge"), "Почта")
        self.assertEqual(browser.clean_title("Просто заголовок"), "Просто заголовок")

    def test_category_hint(self):
        self.assertEqual(browser.category_hint("Lo-fi - YouTube - Google Chrome"), "rest")
        self.assertEqual(browser.category_hint("Google Classroom"), "study")
        self.assertEqual(browser.category_hint("php - Stack Overflow"), "programming")
        self.assertEqual(browser.category_hint("Входящие - Gmail"), "work")
        self.assertEqual(browser.category_hint("Новая вкладка"), "")


class PrepareTest(unittest.TestCase):
    cfg = Config()

    def test_long_tab_keeps_clean_title_and_hint(self):
        s = agent.prepare([Session("chrome", "Lo-fi - YouTube - Google Chrome", T0, 600, False)], self.cfg)[0]
        self.assertEqual((s.window_title, s.category_hint), ("Lo-fi - YouTube", "rest"))

    def test_short_tab_sends_only_hint(self):
        s = agent.prepare([Session("chrome", "Lo-fi - YouTube - Google Chrome", T0, 120, False)], self.cfg)[0]
        self.assertEqual((s.window_title, s.category_hint), ("", "rest"))

    def test_tabs_can_be_disabled(self):
        cfg = Config(track_browser_tabs=False)
        s = agent.prepare([Session("chrome", "YouTube", T0, 600, False)], cfg)[0]
        self.assertEqual(s.window_title, "")

    def test_other_apps_titles_follow_privacy_setting(self):
        session = Session("WINWORD", "Диплом.docx", T0, 600, False)
        self.assertEqual(agent.prepare([session], self.cfg)[0].window_title, "")
        self.assertEqual(agent.prepare([session], Config(track_window_titles=True))[0].window_title, "Диплом.docx")

    def test_browser_tabs_split_sessions_even_without_titles(self):
        self.assertEqual(agent.key_title("chrome", "YouTube", self.cfg), "YouTube")
        self.assertEqual(agent.key_title("WINWORD", "Диплом.docx", self.cfg), "")


class FakeClient:
    def __init__(self):
        self.sent, self.states = [], []

    def send(self, rows):
        self.sent += rows
        return {"accepted": len(rows), "duplicates": 0}

    def heartbeat(self, state=None):
        self.states.append(state)
        return {"ok": True}


class RunLoopTest(unittest.TestCase):
    def setUp(self):
        self.t = T0
        self.cfg = Config(poll_interval_sec=0, sync_interval_sec=0)

    def clock(self):
        self.t += timedelta(seconds=10)
        return self.t

    def test_pause_stops_recording_and_reports_state(self):
        control, client, buf = Control(), FakeClient(), Buffer(":memory:")
        samples = iter([("Code", "", False, 0)] * 3 + [("Code", "", False, 0)] * 10)

        def sampler():
            item = next(samples)
            if self.t >= T0 + timedelta(seconds=40):
                control.pause()
            return item

        agent.run(self.cfg, client, buf, control, sampler=sampler, clock=self.clock, max_ticks=8)

        self.assertTrue(client.states[0]["current_app"] == "Code" or client.states[0]["current_app"] is None)
        paused_states = [s for s in client.states if s["paused"]]
        self.assertTrue(paused_states)
        self.assertIsNone(paused_states[0]["current_app"])
        total = sum(r["duration_sec"] for r in client.sent)
        self.assertLessEqual(total, 40)  # после паузы время не копится

    def test_stop_flushes_open_session(self):
        control, client, buf = Control(), FakeClient(), Buffer(":memory:")
        agent.run(self.cfg, client, buf, control, sampler=lambda: ("Code", "", False, 0), clock=self.clock, max_ticks=4)

        self.assertEqual([r["app_name"] for r in client.sent], ["Code"])
        self.assertTrue(client.states[-1]["paused"])  # на выходе сайт видит, что учёт не идёт

    def test_browser_hint_reaches_buffer(self):
        control, client, buf = Control(), FakeClient(), Buffer(":memory:")
        agent.run(self.cfg, client, buf, control, sampler=lambda: ("chrome", "YouTube - Google Chrome", False, 0),
                  clock=self.clock, max_ticks=3)
        self.assertEqual(client.sent[0]["category_hint"], "rest")
        self.assertEqual(client.sent[0]["window_title"], "")  # 30 секунд — меньше 5 минут


class InstanceLockTest(unittest.TestCase):
    def test_second_instance_is_refused(self):
        path = os.path.join(tempfile.mkdtemp(), "agent.lock")
        first, second = InstanceLock(path), InstanceLock(path)
        self.assertTrue(first.acquire())
        self.assertFalse(second.acquire())
        first.release()
        self.assertTrue(second.acquire())
        second.release()


class BufferMigrationTest(unittest.TestCase):
    def test_old_buffer_gets_hint_column(self):
        import sqlite3

        path = os.path.join(tempfile.mkdtemp(), "buffer.db")
        conn = sqlite3.connect(path)
        conn.execute("CREATE TABLE sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, app_name TEXT NOT NULL, "
                     "window_title TEXT NOT NULL DEFAULT '', started_at TEXT NOT NULL, duration_sec INTEGER NOT NULL, "
                     "is_idle INTEGER NOT NULL DEFAULT 0, sent INTEGER NOT NULL DEFAULT 0)")
        conn.execute("INSERT INTO sessions (app_name, started_at, duration_sec) VALUES ('Code', '2026-11-25T10:00:00Z', 60)")
        conn.commit()
        conn.close()

        buf = Buffer(path)
        self.assertEqual(buf.pending()[0]["category_hint"], "")
        buf.add([Session("chrome", "", T0, 60, False, "rest")])
        self.assertEqual(buf.pending()[1]["category_hint"], "rest")
        buf.close()


if __name__ == "__main__":
    unittest.main()
