from __future__ import annotations

import json
import sys
from pathlib import Path

from yape_common import parsed_payload


def main() -> int:
    if len(sys.argv) < 3:
        print(json.dumps({"ok": False, "error": "Uso: parse_image.py <client|receiver> <image_path>"}))
        return 1

    evidence_type = sys.argv[1]
    image_path = Path(sys.argv[2]).resolve()

    if evidence_type not in {"client", "receiver"}:
        print(json.dumps({"ok": False, "error": "Tipo de evidencia inválido."}))
        return 1

    if not image_path.is_file():
        print(json.dumps({"ok": False, "error": "Imagen no encontrada."}))
        return 1

    payload = parsed_payload(image_path, evidence_type)
    payload["ok"] = True
    print(json.dumps(payload, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
