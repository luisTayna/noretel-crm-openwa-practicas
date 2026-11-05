from __future__ import annotations

import json
import os
import re
from datetime import datetime
from pathlib import Path
from typing import Any

import requests


IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


def env(name: str, default: str | None = None) -> str | None:
    value = os.getenv(name)
    return value if value not in (None, "") else default


def backend_url() -> str:
    return env("YAPE_BACKEND_URL", "http://127.0.0.1:8000").rstrip("/")


def monitor_token() -> str:
    token = env("YAPE_MONITOR_TOKEN")
    if not token:
        raise RuntimeError("Falta YAPE_MONITOR_TOKEN. Debe coincidir con el .env de Laravel.")
    return token


def is_image(path: Path) -> bool:
    return path.is_file() and path.suffix.lower() in IMAGE_EXTENSIONS


def normalize_phone(value: str | None) -> str | None:
    if not value:
        return None

    digits = re.sub(r"\D+", "", value)
    if not digits:
        return None

    if len(digits) == 9 and digits.startswith("9"):
        return f"51{digits}"

    return digits


def extract_phone_from_text(value: str) -> str | None:
    match = re.search(r"(?:\+?51)?\s*(9\d{8})", value)
    return normalize_phone(match.group(0)) if match else None


def parse_amount(text: str) -> float | None:
    # Normalize common OCR errors for the number 1 after S/ or S. or S
    # e.g., "S/]", "S/ I", "S/ l", "S/ |", "S/ !", "S/ /" -> "S/1"
    normalized = re.sub(r'S[/\.\s]\s*[:;\]I|l!/\\\\]+(?=\s|$|\n)', 'S/1', text, flags=re.IGNORECASE)
    # Also handle "S/ ]0.00" or similar
    normalized = re.sub(r'S[/\.\s]\s*[\]I|l!/\\\\]\s*([0-9])', r'S/1\1', normalized, flags=re.IGNORECASE)

    patterns = [
        r"(?:S\/|S\.|S\s|soles?)\s*([0-9]{1,5}(?:[.,][0-9]{1,2})?)",
        r"(?:monto|importe|total)\D{0,12}([0-9]{1,5}(?:[.,][0-9]{1,2})?)",
    ]

    for pattern in patterns:
        match = re.search(pattern, normalized, re.IGNORECASE)
        if match:
            return float(match.group(1).replace(",", "."))

    decimals = re.findall(r"\b([0-9]{1,5}[.,][0-9]{2})\b", normalized)
    if decimals:
        return float(decimals[0].replace(",", "."))

    return None


def parse_security_code(text: str) -> str | None:
    patterns = [
        r"c[oó]d\.?\s*de\s*seguridad\s*(?:es\s*)?:?\s*([0-9](?:\s*[0-9]){1,7})",
        r"c[oó]digo\s*de\s*seguridad\s*(?:es\s*)?:?\s*([0-9](?:\s*[0-9]){1,7})",
    ]

    compact = re.sub(r"\s+", " ", text)
    for pattern in patterns:
        match = re.search(pattern, compact, re.IGNORECASE)
        if match:
            digits = re.sub(r"\D+", "", match.group(1))
            return digits or None

    return None


def ocr_security_code_from_image(image_path: Path) -> str | None:
    """Best-effort OCR for Yape security-code boxes.

    Tesseract often reads the label "CÓDIGO DE SEGURIDAD" but misses the three
    digits because they are rendered inside separated light boxes. The full-page
    OCR text then has the label with no numeric suffix, so regex-only parsing is
    not enough. This fallback crops the right-middle area where Yape places the
    security-code boxes and runs a digit-only OCR pass.
    """

    try:
        from PIL import Image, ImageOps
        import pytesseract

        tesseract_cmd = env("TESSERACT_CMD")
        if tesseract_cmd:
            pytesseract.pytesseract.tesseract_cmd = tesseract_cmd

        image = Image.open(image_path)
        width, height = image.size

        # Multiple crops make the fallback tolerant to small screenshot/layout
        # variations while still avoiding operation-code/phone digits lower down.
        crop_boxes = [
            # Tight crops around the digit boxes first: less label noise, better
            # preservation of leading zeroes like "090".
            (0.68, 0.41, 0.95, 0.47),
            (0.70, 0.42, 0.94, 0.47),
            (0.65, 0.40, 0.96, 0.48),
            # Wider fallbacks for screenshots with slightly different scaling.
            (0.62, 0.38, 0.96, 0.50),
            (0.58, 0.36, 0.98, 0.54),
        ]

        for left, top, right, bottom in crop_boxes:
            crop = image.crop((
                int(width * left),
                int(height * top),
                int(width * right),
                int(height * bottom),
            ))
            crop = ImageOps.grayscale(crop)
            crop = ImageOps.autocontrast(crop)
            crop = crop.resize((crop.width * 4, crop.height * 4))

            variants = [
                crop,
                crop.point(lambda pixel: 255 if pixel > 170 else 0),
            ]
            configs = [
                "--psm 8 -c tessedit_char_whitelist=0123456789",
                "--psm 13 -c tessedit_char_whitelist=0123456789",
                "--psm 7 -c tessedit_char_whitelist=0123456789",
                "--psm 6 -c tessedit_char_whitelist=0123456789",
                "--psm 11 -c tessedit_char_whitelist=0123456789",
            ]
            languages = ["eng", env("YAPE_OCR_LANG", "spa+eng")]
            fallback_digits: list[str] = []

            for variant in variants:
                for language in filter(None, languages):
                    for config in configs:
                        raw = pytesseract.image_to_string(
                            variant,
                            lang=language,
                            config=config,
                        )
                        digits = re.sub(r"\D+", "", raw)
                        if len(digits) == 3:
                            return digits
                        if 4 <= len(digits) <= 8:
                            fallback_digits.append(digits)

            # Only use truncation as a last resort. Some PSM modes may merge a
            # nearby artifact into a 4-digit string (e.g. "4358") while other
            # modes read the correct 3-digit code ("458"). Prefer exact reads.
            if fallback_digits:
                return fallback_digits[0][:3]

    except Exception:
        return None

    return None


def without_security_code_sentences(text: str) -> str:
    result = re.sub(r"\s+", " ", text)
    patterns = [
        r"c[oó]d\.?\s*de\s*seguridad\s*(?:es\s*)?:?\s*[0-9](?:\s*[0-9]){1,7}",
        r"c[oó]digo\s*de\s*seguridad\s*(?:es\s*)?:?\s*[0-9](?:\s*[0-9]){1,7}",
    ]
    for pattern in patterns:
        result = re.sub(pattern, " ", result, flags=re.IGNORECASE)
    return result


def parse_operation_code(text: str) -> str | None:
    text_without_security = without_security_code_sentences(text)
    patterns = [
        r"(?:operaci[oó]n|op\.?|n[°ºro.]*\s*(?:de\s*)?operaci[oó]n|transacci[oó]n)\D{0,24}([0-9]{5,20})",
        r"\b([0-9]{8,20})\b",
    ]

    for pattern in patterns:
        match = re.search(pattern, text_without_security, re.IGNORECASE)
        if match:
            return re.sub(r"\D+", "", match.group(1))

    return None


def security_code_from_operation_code(operation_code: str | None) -> str | None:
    digits = re.sub(r"\D+", "", operation_code or "")
    if len(digits) < 3:
        return None
    return digits[-3:]


def parse_datetime(text: str) -> str | None:
    date_match = re.search(r"\b([0-3]?\d[\/\-][01]?\d(?:[\/\-]\d{2,4})?)\b", text)
    time_match = re.search(r"\b([0-2]?\d:[0-5]\d(?:\s*[ap]\.?m\.?)?)\b", text, re.IGNORECASE)

    raw_date = date_match.group(1) if date_match else datetime.now().strftime("%d/%m/%Y")
    raw_time = time_match.group(1) if time_match else None

    if not raw_time:
        return None

    candidates = [
        f"{raw_date} {raw_time}",
        f"{raw_date}/{datetime.now().year} {raw_time}" if raw_date.count("/") == 1 else "",
    ]

    formats = [
        "%d/%m/%Y %H:%M",
        "%d/%m/%y %H:%M",
        "%d-%m-%Y %H:%M",
        "%d-%m-%y %H:%M",
        "%d/%m/%Y %I:%M %p",
        "%d/%m/%y %I:%M %p",
    ]

    for candidate in filter(None, candidates):
        normalized = re.sub(r"\s+", " ", candidate.replace(".", "")).strip().upper()
        normalized = normalized.replace(" A M", " AM").replace(" P M", " PM")
        for fmt in formats:
            try:
                return datetime.strptime(normalized, fmt).isoformat(sep=" ")
            except ValueError:
                continue

    return None


def extract_receiver_name(text: str) -> str | None:
    match = re.search(r"(?:para|destinatario|receptor)\s*:?\s*([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s.]{2,60})", text, re.IGNORECASE)
    return match.group(1).strip() if match else None


def extract_sender_name(text: str) -> str | None:
    match = re.search(r"(?:de|remitente|te yape[oó])\s*:?\s*([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s.]{2,60})", text, re.IGNORECASE)
    return match.group(1).strip() if match else None


def ocr_image(image_path: Path) -> tuple[str, float]:
    text = ""
    confidence = 0.0

    try:
        import pytesseract

        tesseract_cmd = env("TESSERACT_CMD")
        if tesseract_cmd:
            pytesseract.pytesseract.tesseract_cmd = tesseract_cmd

        text = pytesseract.image_to_string(str(image_path), lang=env("YAPE_OCR_LANG", "spa+eng"))
        confidence = 0.90 if text.strip() else 0.0
    except Exception:
        text = ""

    if text.strip():
        return text, confidence

    try:
        import easyocr

        reader = easyocr.Reader(["es", "en"], gpu=env("YAPE_OCR_GPU", "0") == "1")
        results = reader.readtext(str(image_path), detail=1)
        if not results:
            return "", 0.0

        text = "\n".join(item[1] for item in results)
        confidence = sum(float(item[2]) for item in results) / len(results)
        return text, round(confidence, 2)
    except Exception:
        return "", 0.0


def parsed_payload(image_path: Path, evidence_type: str, extra: dict[str, Any] | None = None) -> dict[str, Any]:
    raw_text, confidence = ocr_image(image_path)
    operation_code = parse_operation_code(raw_text)
    security_code = parse_security_code(raw_text)
    if security_code is None:
        security_code = ocr_security_code_from_image(image_path)

    if evidence_type == "client":
        operation_suffix = security_code_from_operation_code(operation_code)
        if operation_suffix and security_code != operation_suffix:
            security_code = operation_suffix

    payload: dict[str, Any] = {
        "raw_text": raw_text,
        "amount": parse_amount(raw_text),
        "operation_code": operation_code,
        "security_code": security_code,
        "occurred_at": parse_datetime(raw_text),
        "confidence": confidence,
    }

    if evidence_type == "client":
        payload.update({
            "receiver_name": extract_receiver_name(raw_text),
            "receiver_phone": extract_phone_from_text(raw_text),
        })
    else:
        payload.update({
            "sender_name": extract_sender_name(raw_text),
            "sender_phone": extract_phone_from_text(raw_text),
        })

    if extra:
        payload.update({key: value for key, value in extra.items() if value not in (None, "")})

    return payload


def post_evidence(endpoint: str, payload: dict[str, Any], image_path: Path) -> dict[str, Any]:
    url = f"{backend_url()}/api/billing/yape/{endpoint}"
    headers = {"Authorization": f"Bearer {monitor_token()}"}

    with image_path.open("rb") as image_file:
        response = requests.post(
            url,
            headers=headers,
            data={key: "" if value is None else str(value) for key, value in payload.items()},
            files={"image": (image_path.name, image_file, "image/png")},
            timeout=int(env("YAPE_HTTP_TIMEOUT", "20")),
        )

    response.raise_for_status()
    return response.json()


def load_processed(path: Path) -> set[str]:
    if not path.exists():
        return set()

    try:
        return set(json.loads(path.read_text(encoding="utf-8")))
    except Exception:
        return set()


def save_processed(path: Path, values: set[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(sorted(values), ensure_ascii=False, indent=2), encoding="utf-8")
