import json
import unittest

from helpers import make_payload  # noqa: E402

from app import create_app  # noqa: E402

HOURS = [5.0, 4.8, 4.2, 4.0, 3.5, 3.1, 2.8, 2.2, 2.0, 1.5, 1.2, 1.0, 0.8, 3.0]


class AppTest(unittest.TestCase):
    def client(self, token=None):
        return create_app(token=token).test_client()

    def test_health(self):
        r = self.client().get("/health")
        self.assertEqual(r.status_code, 200)
        self.assertEqual(r.get_json(), {"status": "ok"})

    def test_analyze_returns_list_of_insights(self):
        r = self.client().post("/analyze", json=make_payload(HOURS))
        self.assertEqual(r.status_code, 200)
        data = r.get_json()
        self.assertIsInstance(data, list)           # сайт ждёт именно массив
        self.assertEqual(data[0]["key"], "trend_down")

    def test_russian_text_is_not_escaped(self):
        r = self.client().post("/analyze", json=make_payload(HOURS))
        self.assertIn("мин в день".encode("utf-8"), r.data)

    def test_not_enough_data_returns_empty_list(self):
        r = self.client().post("/analyze", json=make_payload([1, 2, 1]))
        self.assertEqual((r.status_code, r.get_json()), (200, []))

    def test_non_json_body_is_400(self):
        r = self.client().post("/analyze", data="не json", content_type="text/plain")
        self.assertEqual(r.status_code, 400)

    def test_json_array_is_400(self):
        r = self.client().post("/analyze", data=json.dumps([1, 2]), content_type="application/json")
        self.assertEqual(r.status_code, 400)

    def test_invalid_metrics_are_400_with_message(self):
        r = self.client().post("/analyze", json={"days": {"2026-11-02": {"focus_sec": -1}}})
        self.assertEqual(r.status_code, 400)
        self.assertIn("focus_sec", r.get_json()["message"])

    def test_token_required_when_configured(self):
        client = self.client(token="secret")
        payload = make_payload(HOURS)

        self.assertEqual(client.post("/analyze", json=payload).status_code, 401)
        self.assertEqual(client.post("/analyze", json=payload, headers={"Authorization": "Bearer wrong"}).status_code, 401)
        self.assertEqual(client.post("/analyze", json=payload, headers={"Authorization": "Bearer secret"}).status_code, 200)

    def test_health_is_open_even_with_token(self):
        self.assertEqual(self.client(token="secret").get("/health").status_code, 200)

    def test_no_token_configured_means_open(self):
        self.assertEqual(self.client().post("/analyze", json=make_payload(HOURS)).status_code, 200)


if __name__ == "__main__":
    unittest.main()
