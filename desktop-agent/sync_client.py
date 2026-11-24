"""HTTP-клиент к Agent API сайта ActivityMind."""
from __future__ import annotations


class AuthError(Exception):
    """Токен неверный или отозван (401) — повторять бессмысленно."""


class RejectedError(Exception):
    """Сервер отклонил данные (422) — повтор той же пачки не поможет."""


class TransientError(Exception):
    """Сеть/сервер временно недоступны — попробуем позже."""


class ApiClient:
    def __init__(self, base_url: str, token: str, timeout: int = 10, session=None):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        if session is None:
            import requests

            session = requests.Session()
        self.session = session
        self.session.headers.update({
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
        })

    def heartbeat(self, state: dict | None = None) -> dict:
        """Проверка связи; state — пауза и текущая программа для страницы «Активность» на сайте."""
        return self._post("/api/agent/heartbeat", state or {})

    def send(self, rows: list[dict]) -> dict:
        items = []
        for r in rows:
            item = {
                "app_name": r["app_name"][:255],
                "started_at": r["started_at"],
                "duration_sec": int(r["duration_sec"]),
                "is_idle": bool(r["is_idle"]),
            }
            if r.get("window_title"):
                item["window_title"] = r["window_title"][:1000]
            if r.get("category_hint"):
                item["category_hint"] = r["category_hint"]
            items.append(item)
        return self._post("/api/agent/activity", {"items": items})

    def _post(self, path: str, payload: dict) -> dict:
        try:
            resp = self.session.post(self.base_url + path, json=payload, timeout=self.timeout)
        except Exception as exc:  # нет сети, таймаут, DNS...
            raise TransientError(str(exc)) from exc

        if resp.status_code == 200:
            return resp.json()
        if resp.status_code == 401:
            raise AuthError("Сервер не принял токен устройства (401).")
        if resp.status_code == 422:
            raise RejectedError(resp.text[:300])
        raise TransientError(f"HTTP {resp.status_code}")
