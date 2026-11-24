import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from sync_client import ApiClient, AuthError, RejectedError, TransientError  # noqa: E402


class FakeResponse:
    def __init__(self, status, body=None, text=""):
        self.status_code = status
        self._body = body or {}
        self.text = text

    def json(self):
        return self._body


class FakeSession:
    def __init__(self, response=None, error=None):
        self.headers = {}
        self.response = response
        self.error = error
        self.calls = []

    def post(self, url, json=None, timeout=None):
        self.calls.append((url, json, timeout))
        if self.error:
            raise self.error
        return self.response


ROW = {"id": 1, "app_name": "Code", "window_title": "", "started_at": "2026-11-15T10:00:00Z", "duration_sec": 60, "is_idle": False}


class ApiClientTest(unittest.TestCase):
    def client(self, **kw):
        session = FakeSession(**kw)
        return ApiClient("http://site.test/", "amd_token", timeout=7, session=session), session

    def test_sets_auth_headers(self):
        _, session = self.client(response=FakeResponse(200))
        self.assertEqual(session.headers["Authorization"], "Bearer amd_token")
        self.assertEqual(session.headers["Accept"], "application/json")

    def test_send_ok_returns_json_and_builds_payload(self):
        client, session = self.client(response=FakeResponse(200, {"accepted": 1, "duplicates": 0}))
        result = client.send([ROW, dict(ROW, window_title="main.php", is_idle=True)])

        self.assertEqual(result["accepted"], 1)
        url, payload, timeout = session.calls[0]
        self.assertEqual(url, "http://site.test/api/agent/activity")
        self.assertEqual(timeout, 7)
        self.assertNotIn("window_title", payload["items"][0])      # пустой заголовок не отправляется
        self.assertEqual(payload["items"][1]["window_title"], "main.php")
        self.assertTrue(payload["items"][1]["is_idle"])
        self.assertNotIn("id", payload["items"][0])                # локальный id наружу не уходит

    def test_heartbeat_url(self):
        client, session = self.client(response=FakeResponse(200, {"ok": True}))
        client.heartbeat()
        self.assertEqual(session.calls[0][0], "http://site.test/api/agent/heartbeat")

    def test_401_is_auth_error(self):
        client, _ = self.client(response=FakeResponse(401))
        with self.assertRaises(AuthError):
            client.send([ROW])

    def test_422_is_rejected(self):
        client, _ = self.client(response=FakeResponse(422, text="bad"))
        with self.assertRaises(RejectedError):
            client.send([ROW])

    def test_server_errors_are_transient(self):
        for status in (429, 500, 502, 503):
            client, _ = self.client(response=FakeResponse(status))
            with self.assertRaises(TransientError):
                client.send([ROW])

    def test_network_errors_are_transient(self):
        client, _ = self.client(error=ConnectionError("нет сети"))
        with self.assertRaises(TransientError):
            client.send([ROW])


if __name__ == "__main__":
    unittest.main()
