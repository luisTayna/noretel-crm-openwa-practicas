#!/usr/bin/env python3
"""Small internal HTTP OCR bridge for Yape screenshots.

It receives a multipart image, runs the existing parse_image.py script, and
returns the same JSON contract expected by Laravel.
"""

from __future__ import annotations

import cgi
import json
import os
import subprocess
import tempfile
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


HOST = os.getenv("YAPE_OCR_HOST", "127.0.0.1")
PORT = int(os.getenv("YAPE_OCR_PORT", "2786"))
PATH = os.getenv("YAPE_OCR_PATH", "/api/yape/ocr")
SECRET = os.getenv("YAPE_OCR_REMOTE_SECRET", "")
PYTHON = os.getenv("YAPE_OCR_PYTHON", "python3")
PARSER = os.getenv("YAPE_OCR_PARSER_PATH", "/opt/openwa/tools/yape_monitor/parse_image.py")
TIMEOUT = int(os.getenv("YAPE_OCR_TIMEOUT", "120"))
MAX_IMAGE_BYTES = int(os.getenv("YAPE_OCR_MAX_IMAGE_BYTES", str(8 * 1024 * 1024)))


def json_response(handler: BaseHTTPRequestHandler, status: HTTPStatus, payload: dict) -> None:
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    handler.send_response(status)
    handler.send_header("Content-Type", "application/json; charset=utf-8")
    handler.send_header("Content-Length", str(len(body)))
    handler.end_headers()
    handler.wfile.write(body)


class YapeOcrHandler(BaseHTTPRequestHandler):
    server_version = "NoreTelYapeOcr/1.0"

    def do_POST(self) -> None:  # noqa: N802 - stdlib handler API
        if self.path.rstrip("/") != PATH.rstrip("/"):
            json_response(self, HTTPStatus.NOT_FOUND, {"ok": False, "error": "not_found"})
            return

        if not SECRET:
            json_response(self, HTTPStatus.INTERNAL_SERVER_ERROR, {"ok": False, "error": "secret_not_configured"})
            return

        provided_secret = self.headers.get("X-Yape-Ocr-Secret", "")
        if provided_secret != SECRET:
            json_response(self, HTTPStatus.UNAUTHORIZED, {"ok": False, "error": "invalid_secret"})
            return

        content_length = int(self.headers.get("Content-Length", "0") or "0")
        if content_length <= 0 or content_length > MAX_IMAGE_BYTES:
            json_response(self, HTTPStatus.REQUEST_ENTITY_TOO_LARGE, {"ok": False, "error": "invalid_image_size"})
            return

        content_type = self.headers.get("Content-Type", "")
        if "multipart/form-data" not in content_type:
            json_response(self, HTTPStatus.BAD_REQUEST, {"ok": False, "error": "multipart_required"})
            return

        form = cgi.FieldStorage(
            fp=self.rfile,
            headers=self.headers,
            environ={
                "REQUEST_METHOD": "POST",
                "CONTENT_TYPE": content_type,
                "CONTENT_LENGTH": str(content_length),
            },
        )
        image = form["image"] if "image" in form else None
        mode = form.getfirst("mode", "client")

        if image is None or not getattr(image, "file", None):
            json_response(self, HTTPStatus.BAD_REQUEST, {"ok": False, "error": "image_required"})
            return

        parser_path = Path(PARSER)
        if not parser_path.is_file():
            json_response(self, HTTPStatus.INTERNAL_SERVER_ERROR, {"ok": False, "error": "parser_not_found"})
            return

        suffix = Path(getattr(image, "filename", "") or "capture.png").suffix or ".png"

        with tempfile.NamedTemporaryFile(prefix="yape-ocr-", suffix=suffix, delete=False) as tmp:
            image_bytes = image.file.read(MAX_IMAGE_BYTES + 1)
            if len(image_bytes) > MAX_IMAGE_BYTES:
                json_response(self, HTTPStatus.REQUEST_ENTITY_TOO_LARGE, {"ok": False, "error": "image_too_large"})
                return
            tmp.write(image_bytes)
            tmp_path = tmp.name

        try:
            completed = subprocess.run(
                [PYTHON, str(parser_path), mode, tmp_path],
                capture_output=True,
                text=True,
                timeout=TIMEOUT,
                check=False,
            )
            if completed.returncode != 0:
                json_response(
                    self,
                    HTTPStatus.OK,
                    {
                        "ok": False,
                        "error": "parser_failed",
                        "stderr": completed.stderr[-1000:],
                    },
                )
                return

            parsed = self.parse_last_json_line(completed.stdout)
            if not isinstance(parsed, dict):
                json_response(
                    self,
                    HTTPStatus.OK,
                    {
                        "ok": False,
                        "error": "invalid_parser_json",
                        "stdout": completed.stdout[-1000:],
                        "stderr": completed.stderr[-1000:],
                    },
                )
                return

            json_response(self, HTTPStatus.OK, parsed)
        except subprocess.TimeoutExpired:
            json_response(self, HTTPStatus.OK, {"ok": False, "error": "parser_timeout"})
        finally:
            try:
                os.unlink(tmp_path)
            except OSError:
                pass

    @staticmethod
    def parse_last_json_line(output: str) -> dict | None:
        for line in reversed(output.splitlines()):
            line = line.strip()
            if line.startswith("{") and line.endswith("}"):
                try:
                    return json.loads(line)
                except json.JSONDecodeError:
                    return None
        return None

    def log_message(self, fmt: str, *args) -> None:
        print("[%s] %s" % (self.log_date_time_string(), fmt % args), flush=True)


def main() -> None:
    server = ThreadingHTTPServer((HOST, PORT), YapeOcrHandler)
    print(f"Yape OCR server listening on http://{HOST}:{PORT}{PATH}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
