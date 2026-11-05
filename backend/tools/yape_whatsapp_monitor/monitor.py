from __future__ import annotations

import sys
import time
import webbrowser
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "yape_monitor"))

from yape_common import (  # noqa: E402
    env,
    extract_phone_from_text,
    is_image,
    load_processed,
    parsed_payload,
    post_evidence,
    save_processed,
)


ROOT = Path(__file__).resolve().parent
INBOX = Path(env("YAPE_WHATSAPP_INBOX_PATH", str(ROOT / "inbox"))).resolve()
STATE_FILE = Path(env("YAPE_WHATSAPP_STATE_FILE", str(ROOT / ".processed.json"))).resolve()
POLL_SECONDS = float(env("YAPE_WHATSAPP_POLL_SECONDS", "3"))


def open_whatsapp_web() -> None:
    if env("YAPE_OPEN_WHATSAPP_WEB", "1") != "1":
        return

    webbrowser.open("https://web.whatsapp.com/", new=2)


def wait_until_stable(path: Path) -> bool:
    last_size = -1

    for _ in range(10):
        if not path.exists():
            return False

        size = path.stat().st_size
        if size > 0 and size == last_size:
            return True

        last_size = size
        time.sleep(0.5)

    return path.exists() and path.stat().st_size > 0


def process_image(path: Path, processed: set[str]) -> None:
    fingerprint = f"{path.name}:{path.stat().st_size}:{int(path.stat().st_mtime)}"
    if fingerprint in processed:
        return

    if not wait_until_stable(path):
        return

    whatsapp_phone = extract_phone_from_text(path.stem) or extract_phone_from_text(str(path.parent.name))
    payload = parsed_payload(
        path,
        "client",
        {
            "whatsapp_phone": whatsapp_phone,
            "source_message_id": fingerprint,
            "source_chat_name": path.parent.name if path.parent != INBOX else None,
        },
    )

    response = post_evidence("client-evidences", payload, path)
    processed.add(fingerprint)
    save_processed(STATE_FILE, processed)
    print(f"[whatsapp] {path.name} -> {response}")


def scan_existing(processed: set[str]) -> None:
    for path in sorted(INBOX.rglob("*")):
        if is_image(path):
            try:
                process_image(path, processed)
            except Exception as exc:
                print(f"[whatsapp] error procesando {path}: {exc}")


def main() -> None:
    INBOX.mkdir(parents=True, exist_ok=True)
    processed = load_processed(STATE_FILE)

    print(f"[whatsapp] Carpeta monitoreada: {INBOX}")
    print("[whatsapp] Abriendo WhatsApp Web local. Mantén la sesión iniciada.")
    open_whatsapp_web()

    while True:
        scan_existing(processed)
        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    main()
