"""HTTP-сервис AI-модуля: POST /analyze, GET /health."""
from __future__ import annotations

import hmac
import os

from flask import Flask, jsonify, request

from analyzer import analyze


def create_app(token: str | None = None) -> Flask:
    app = Flask(__name__)
    app.json.ensure_ascii = False  # русские тексты читаемы в ответе

    expected = token if token is not None else (os.environ.get("AI_ANALYZER_TOKEN") or None)

    @app.get("/health")
    def health():
        return jsonify({"status": "ok"})

    @app.post("/analyze")
    def analyze_route():
        if expected:
            header = request.headers.get("Authorization", "")
            if not hmac.compare_digest(header, f"Bearer {expected}"):
                return jsonify({"message": "Неверный токен."}), 401

        payload = request.get_json(silent=True)
        if not isinstance(payload, dict):
            return jsonify({"message": "Ожидается JSON-объект."}), 400

        try:
            return jsonify(analyze(payload))
        except ValueError as exc:
            return jsonify({"message": str(exc)}), 400

    return app


app = create_app()

if __name__ == "__main__":
    # Только локальный интерфейс: сервис вызывает Laravel с этого же компьютера
    app.run(host=os.environ.get("HOST", "127.0.0.1"), port=int(os.environ.get("PORT", "5000")))
