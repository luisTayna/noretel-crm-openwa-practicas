from __future__ import annotations

import ctypes
import json
import os
import re
import subprocess
import sys
import time
import unicodedata
from ctypes import wintypes
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any

import requests

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "yape_monitor"))

from yape_common import backend_url, env, monitor_token, parsed_payload, post_evidence  # noqa: E402


ROOT = Path(__file__).resolve().parent
SCREENSHOT_DIR = Path(env("YAPE_RECEIVER_SCREENSHOT_DIR", str(ROOT / "screenshots"))).resolve()
STATE_FILE = Path(env("YAPE_RECEIVER_STATE_FILE", str(ROOT / ".processed_receiver.json"))).resolve()
ADB_PATH = env("YAPE_ADB_PATH", "adb")
DEVICE_SERIAL = env("YAPE_DEVICE_SERIAL")
MODE = env("YAPE_RECEIVER_MODE", "adb_navigation")
INTERVAL_SECONDS = float(env("YAPE_RECEIVER_INTERVAL_SECONDS", "5"))
PENDING_LIMIT = int(env("YAPE_RECEIVER_PENDING_LIMIT", "20"))

YAPE_PACKAGE = env("YAPE_RECEIVER_APP_PACKAGE", "com.bcp.innovacxion.yapeapp")
OPEN_WAIT_SECONDS = float(env("YAPE_RECEIVER_OPEN_WAIT_SECONDS", "4"))
DETAIL_WAIT_SECONDS = float(env("YAPE_RECEIVER_DETAIL_WAIT_SECONDS", "2"))
BACK_WAIT_SECONDS = float(env("YAPE_RECEIVER_BACK_WAIT_SECONDS", "1"))

MOVEMENTS_TAB_X = int(env("YAPE_MOVEMENTS_TAB_X", "540"))
MOVEMENTS_TAB_Y = int(env("YAPE_MOVEMENTS_TAB_Y", "1800"))
MOVEMENTS_WAIT_SECONDS = float(env("YAPE_MOVEMENTS_WAIT_SECONDS", "2"))
MOVEMENT_TAP_X = int(env("YAPE_MOVEMENT_TAP_X", "540"))
LIST_TOP_Y = int(env("YAPE_MOVEMENTS_LIST_TOP_Y", "350"))
LIST_BOTTOM_Y = int(env("YAPE_MOVEMENTS_LIST_BOTTOM_Y", "1700"))
MIN_CANDIDATE_SCORE = int(env("YAPE_MOVEMENT_MIN_SCORE", "60"))
LOG_OCR_MAX_CHARS = int(env("YAPE_RECEIVER_LOG_OCR_MAX_CHARS", "2500"))

ENABLE_PIN_AUTOUNLOCK = env("YAPE_ENABLE_PIN_AUTOUNLOCK", "0") == "1"
PIN_CREDENTIAL_SERVICE = env("YAPE_PIN_CREDENTIAL_SERVICE", "noretel-yape-receiver")
PIN_CREDENTIAL_USERNAME = env("YAPE_PIN_CREDENTIAL_USERNAME", DEVICE_SERIAL or "default")
PIN_MAX_ATTEMPTS = int(env("YAPE_PIN_MAX_ATTEMPTS", "1"))
PIN_UNLOCK_WAIT_SECONDS = float(env("YAPE_PIN_UNLOCK_WAIT_SECONDS", "3"))
PIN_ENTRY_MODE = env("YAPE_PIN_ENTRY_MODE", "tap_keypad")
PIN_TAP_DELAY_SECONDS = float(env("YAPE_PIN_TAP_DELAY_SECONDS", "0.18"))
SECURE_BLACK_THRESHOLD = float(env("YAPE_SECURE_BLACK_THRESHOLD", "8"))
ALLOW_NAVIGATION_WITH_EMPTY_OCR = env("YAPE_ALLOW_NAVIGATION_WITH_EMPTY_OCR", "0") == "1"
PIN_SCREEN_KEYWORDS = [
    token.strip().lower()
    for token in str(env("YAPE_PIN_SCREEN_KEYWORDS", "clave,contraseña,ingresa tu clave,ingrese su clave,desbloquea")).split(",")
    if token.strip()
]

CRED_TYPE_GENERIC = 1


class CredentialW(ctypes.Structure):
    _fields_ = [
        ("Flags", wintypes.DWORD),
        ("Type", wintypes.DWORD),
        ("TargetName", wintypes.LPWSTR),
        ("Comment", wintypes.LPWSTR),
        ("LastWritten", wintypes.FILETIME),
        ("CredentialBlobSize", wintypes.DWORD),
        ("CredentialBlob", ctypes.POINTER(wintypes.BYTE)),
        ("Persist", wintypes.DWORD),
        ("AttributeCount", wintypes.DWORD),
        ("Attributes", wintypes.LPVOID),
        ("TargetAlias", wintypes.LPWSTR),
        ("UserName", wintypes.LPWSTR),
    ]


if os.name == "nt":
    advapi32 = ctypes.WinDLL("Advapi32", use_last_error=True)
    advapi32.CredReadW.argtypes = [
        wintypes.LPCWSTR,
        wintypes.DWORD,
        wintypes.DWORD,
        ctypes.POINTER(ctypes.POINTER(CredentialW)),
    ]
    advapi32.CredReadW.restype = wintypes.BOOL
    advapi32.CredFree.argtypes = [wintypes.LPVOID]
    advapi32.CredFree.restype = None
else:
    advapi32 = None


@dataclass
class OcrLine:
    text: str
    x: int
    y: int
    w: int
    h: int

    @property
    def center_y(self) -> int:
        return self.y + self.h // 2


def adb_command(*args: str) -> list[str]:
    command = [ADB_PATH]
    if DEVICE_SERIAL:
        command.extend(["-s", DEVICE_SERIAL])
    command.extend(args)
    return command


def run_adb(*args: str, check: bool = True, capture_output: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(
        adb_command(*args),
        check=check,
        stdout=subprocess.PIPE if capture_output else None,
        stderr=subprocess.PIPE if capture_output else None,
    )


def adb_shell(*args: str, check: bool = True, capture_output: bool = True) -> subprocess.CompletedProcess:
    return run_adb("shell", *args, check=check, capture_output=capture_output)


def screen_size() -> tuple[int, int]:
    result = adb_shell("wm", "size")
    output = result.stdout.decode(errors="ignore") if result.stdout else ""
    match = re.search(r"Physical size:\s*(\d+)x(\d+)", output)

    if not match:
        width = int(env("YAPE_SCREEN_WIDTH", "1080"))
        height = int(env("YAPE_SCREEN_HEIGHT", "2400"))
        return width, height

    return int(match.group(1)), int(match.group(2))


def tap(x: int, y: int) -> None:
    print(f"[receiver] Tocando coordenada x={x}, y={y}")
    adb_shell("input", "tap", str(x), str(y), capture_output=False)  # type: ignore[arg-type]


def back() -> None:
    adb_shell("input", "keyevent", "4", capture_output=False)  # type: ignore[arg-type]
    time.sleep(BACK_WAIT_SECONDS)


def wake_screen() -> None:
    adb_shell("input", "keyevent", "224", check=False, capture_output=False)  # type: ignore[arg-type]
    adb_shell("input", "keyevent", "82", check=False, capture_output=False)  # type: ignore[arg-type]


def open_yape() -> None:
    wake_screen()
    if YAPE_PACKAGE:
        adb_shell("monkey", "-p", YAPE_PACKAGE, "-c", "android.intent.category.LAUNCHER", "1", check=False)
    time.sleep(OPEN_WAIT_SECONDS)
    ensure_yape_unlocked()


def go_to_movements() -> None:
    tap(MOVEMENTS_TAB_X, MOVEMENTS_TAB_Y)
    time.sleep(MOVEMENTS_WAIT_SECONDS)


def capture_screen(prefix: str = "receiver") -> Path:
    SCREENSHOT_DIR.mkdir(parents=True, exist_ok=True)
    path = SCREENSHOT_DIR / f"{prefix}-{datetime.now().strftime('%Y%m%d-%H%M%S')}.png"

    result = run_adb("exec-out", "screencap", "-p")
    path.write_bytes(result.stdout)
    return path


def image_mean_brightness(image_path: Path) -> float | None:
    try:
        from PIL import Image

        image = Image.open(image_path).convert("L")
        histogram = image.histogram()
        pixels = sum(histogram)
        if pixels <= 0:
            return None

        return sum(level * count for level, count in enumerate(histogram)) / pixels
    except Exception:
        return None


def is_probably_secure_black_screen(image_path: Path) -> bool:
    brightness = image_mean_brightness(image_path)
    return brightness is not None and brightness <= SECURE_BLACK_THRESHOLD


def load_processed() -> set[str]:
    if not STATE_FILE.exists():
        return set()

    try:
        return set(json.loads(STATE_FILE.read_text(encoding="utf-8")))
    except Exception:
        return set()


def save_processed(values: set[str]) -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    STATE_FILE.write_text(json.dumps(sorted(values), ensure_ascii=False, indent=2), encoding="utf-8")


def get_pending_intents() -> list[dict[str, Any]]:
    url = f"{backend_url()}/api/billing/yape/pending-receiver-intents"
    print(f"[receiver] Consultando endpoint: GET {url}?limit={PENDING_LIMIT}")
    response = requests.get(
        url,
        headers={"Authorization": f"Bearer {monitor_token()}"},
        params={"limit": PENDING_LIMIT},
        timeout=int(env("YAPE_HTTP_TIMEOUT", "20")),
    )
    response.raise_for_status()
    payload = response.json()
    intents = payload.get("intents", []) if isinstance(payload, dict) else []
    print(f"[receiver] Intents esperando receptor: {len(intents)}")
    return intents


def compact_text(value: str, limit: int = LOG_OCR_MAX_CHARS) -> str:
    text = re.sub(r"\s+", " ", value).strip()
    if len(text) <= limit:
        return text
    return text[:limit] + "..."


def lines_text(lines: list[OcrLine]) -> str:
    return "\n".join(f"y={line.center_y}: {line.text}" for line in lines)


def normalize_text(value: str | None) -> str:
    if not value:
        return ""
    normalized = unicodedata.normalize("NFD", value)
    normalized = "".join(ch for ch in normalized if unicodedata.category(ch) != "Mn")
    normalized = re.sub(r"[^a-zA-Z0-9.,:/\s-]+", " ", normalized)
    return re.sub(r"\s+", " ", normalized).strip().lower()


def name_tokens(value: str | None) -> list[str]:
    ignored = {"de", "del", "la", "las", "los", "y", "sr", "sra"}
    return [token for token in normalize_text(value).split() if len(token) >= 3 and token not in ignored][:3]


def amount_values(text: str) -> list[float]:
    values: list[float] = []
    for raw in re.findall(r"(?:s\/?\s*)?([0-9]{1,5}(?:[.,][0-9]{2})?)", text, flags=re.IGNORECASE):
        try:
            values.append(round(float(raw.replace(",", ".")), 2))
        except ValueError:
            continue
    return values


def same_amount(text: str, expected: float) -> bool:
    expected = round(float(expected), 2)
    return any(value == expected for value in amount_values(text))


def intent_date_tokens(intent: dict[str, Any]) -> list[str]:
    raw = intent.get("client_occurred_at") or intent.get("created_at")
    if not raw:
        return []

    try:
        dt = datetime.fromisoformat(str(raw).replace("Z", "").replace(" ", "T"))
    except ValueError:
        return []

    return [
        dt.strftime("%d/%m/%Y"),
        dt.strftime("%d-%m-%Y"),
        dt.strftime("%d/%m/%y"),
        dt.strftime("%d-%m-%y"),
        dt.strftime("%H:%M"),
    ]


def ocr_lines(image_path: Path) -> list[OcrLine]:
    try:
        import pytesseract
        from PIL import Image

        tesseract_cmd = env("TESSERACT_CMD")
        if tesseract_cmd:
            pytesseract.pytesseract.tesseract_cmd = tesseract_cmd

        data = pytesseract.image_to_data(
            Image.open(image_path),
            lang=env("YAPE_OCR_LANG", "spa+eng"),
            output_type=pytesseract.Output.DICT,
        )
    except Exception:
        return []

    grouped: dict[tuple[int, int, int], list[int]] = {}
    for index, text in enumerate(data.get("text", [])):
        if not str(text).strip():
            continue

        key = (
            int(data["block_num"][index]),
            int(data["par_num"][index]),
            int(data["line_num"][index]),
        )
        grouped.setdefault(key, []).append(index)

    lines: list[OcrLine] = []
    for indexes in grouped.values():
        words = [str(data["text"][i]).strip() for i in indexes if str(data["text"][i]).strip()]
        if not words:
            continue

        left = min(int(data["left"][i]) for i in indexes)
        top = min(int(data["top"][i]) for i in indexes)
        right = max(int(data["left"][i]) + int(data["width"][i]) for i in indexes)
        bottom = max(int(data["top"][i]) + int(data["height"][i]) for i in indexes)
        lines.append(OcrLine(" ".join(words), left, top, right - left, bottom - top))

    return sorted(lines, key=lambda line: (line.y, line.x))


def credential_target() -> str:
    return f"{PIN_CREDENTIAL_SERVICE}:{PIN_CREDENTIAL_USERNAME}"


def read_pin_from_credential_manager() -> str:
    if os.name != "nt" or advapi32 is None:
        raise RuntimeError("El autounlock seguro del PIN solo está implementado para Windows Credential Manager.")

    credential_ptr = ctypes.POINTER(CredentialW)()
    target = credential_target()

    if not advapi32.CredReadW(target, CRED_TYPE_GENERIC, 0, ctypes.byref(credential_ptr)):
        error = ctypes.get_last_error()
        raise ctypes.WinError(error)

    try:
        credential = credential_ptr.contents
        blob = ctypes.string_at(credential.CredentialBlob, credential.CredentialBlobSize)
        pin = blob.decode("utf-16-le")
    finally:
        advapi32.CredFree(credential_ptr)

    if not re.fullmatch(r"\d{4,8}", pin):
        raise RuntimeError("El PIN guardado no tiene formato válido. Reejecuta setup_pin.py.")

    return pin


def is_pin_screen(lines: list[OcrLine]) -> bool:
    normalized = normalize_text(lines_text(lines))
    return any(keyword in normalized for keyword in PIN_SCREEN_KEYWORDS)


def keypad_coordinates() -> dict[str, tuple[int, int]]:
    width, height = screen_size()
    default_ratios = {
        "1": (0.25, 0.62),
        "2": (0.50, 0.62),
        "3": (0.75, 0.62),
        "4": (0.25, 0.70),
        "5": (0.50, 0.70),
        "6": (0.75, 0.70),
        "7": (0.25, 0.78),
        "8": (0.50, 0.78),
        "9": (0.75, 0.78),
        "0": (0.50, 0.86),
    }

    coordinates: dict[str, tuple[int, int]] = {}

    for digit, (ratio_x, ratio_y) in default_ratios.items():
        override = env(f"YAPE_PIN_KEY_{digit}")

        if override:
            match = re.fullmatch(r"\s*(\d+)\s*,\s*(\d+)\s*", str(override))
            if not match:
                raise RuntimeError(f"Coordenada inválida en YAPE_PIN_KEY_{digit}. Usa formato x,y.")

            coordinates[digit] = (int(match.group(1)), int(match.group(2)))
            continue

        coordinates[digit] = (round(width * ratio_x), round(height * ratio_y))

    return coordinates


def enter_pin_with_keypad_taps(pin: str) -> None:
    coordinates = keypad_coordinates()

    for index, digit in enumerate(pin, start=1):
        if digit not in coordinates:
            raise RuntimeError("PIN inválido: contiene un dígito no soportado.")

        x, y = coordinates[digit]
        print(f"[receiver] Tocando dígito PIN #{index} en coordenada segura configurada.")
        tap(x, y)
        time.sleep(PIN_TAP_DELAY_SECONDS)


def enter_pin_with_adb_text(pin: str) -> None:
    print("[receiver] Enviando PIN con adb input text (modo menos preciso que tap_keypad).")
    adb_shell("input", "text", pin, capture_output=False)  # type: ignore[arg-type]


def enter_yape_pin(pin: str) -> None:
    if PIN_ENTRY_MODE == "adb_text":
        enter_pin_with_adb_text(pin)
        return

    enter_pin_with_keypad_taps(pin)


def ensure_yape_unlocked() -> None:
    screenshot = capture_screen("pin-check")
    lines = ocr_lines(screenshot)
    secure_black = is_probably_secure_black_screen(screenshot)

    if not lines:
        if secure_black:
            print(
                f"[receiver] Captura negra detectada en Yape ({screenshot.name}). "
                "Probable FLAG_SECURE: OCR por screenshot no puede ver esta pantalla."
            )

            if ENABLE_PIN_AUTOUNLOCK:
                attempts = max(1, PIN_MAX_ATTEMPTS)
                for attempt in range(1, attempts + 1):
                    print(f"[receiver] Intentando autounlock por teclado calibrado en pantalla segura ({attempt}/{attempts}).")
                    pin = read_pin_from_credential_manager()
                    enter_yape_pin(pin)
                    time.sleep(PIN_UNLOCK_WAIT_SECONDS)

                return

            raise RuntimeError("Yape muestra pantalla segura negra y el autounlock está desactivado.")

        print(f"[receiver] No se pudo leer OCR para detectar PIN: {screenshot.name}")
        raise RuntimeError("No se pudo leer OCR para detectar si Yape está bloqueado. No se continuará a ciegas.")

    if not is_pin_screen(lines):
        return

    print(f"[receiver] Pantalla de clave Yape detectada: {screenshot.name}")

    if not ENABLE_PIN_AUTOUNLOCK:
        print("[receiver] Autounlock desactivado. Activa YAPE_ENABLE_PIN_AUTOUNLOCK=1 si aceptas el riesgo controlado.")
        raise RuntimeError("Yape requiere clave y el autounlock está desactivado.")

    attempts = max(1, PIN_MAX_ATTEMPTS)
    for attempt in range(1, attempts + 1):
        print(f"[receiver] Intentando autounlock seguro de Yape ({attempt}/{attempts}).")
        pin = read_pin_from_credential_manager()
        enter_yape_pin(pin)
        time.sleep(PIN_UNLOCK_WAIT_SECONDS)

        verification = capture_screen("pin-verify")
        verification_lines = ocr_lines(verification)

        if verification_lines and not is_pin_screen(verification_lines):
            print("[receiver] Yape desbloqueado correctamente.")
            return

        print(f"[receiver] Yape todavía parece pedir clave después del intento {attempt}: {verification.name}")

    raise RuntimeError("No se pudo desbloquear Yape. Pausando por seguridad para evitar intentos repetidos.")


def nearby_text(lines: list[OcrLine], y: int, radius: int = 95) -> str:
    return " ".join(line.text for line in lines if abs(line.center_y - y) <= radius)


def score_candidate(intent: dict[str, Any], text: str) -> int:
    normalized = normalize_text(text)
    score = 0

    if same_amount(normalized, float(intent["expected_amount"])):
        score += 60

    tokens = name_tokens(intent.get("customer_name"))
    if tokens:
        matched = sum(1 for token in tokens if token in normalized)
        score += min(matched * 15, 30)

    date_tokens = [normalize_text(token) for token in intent_date_tokens(intent)]
    if any(token and token in normalized for token in date_tokens):
        score += 10

    return score


def find_candidate_y(lines: list[OcrLine], intent: dict[str, Any]) -> tuple[int | None, int, str]:
    best_y: int | None = None
    best_score = 0
    best_text = ""

    for line in lines:
        if line.center_y < LIST_TOP_Y or line.center_y > LIST_BOTTOM_Y:
            continue

        context = nearby_text(lines, line.center_y)
        score = score_candidate(intent, context)

        if score > best_score:
            best_y = line.center_y
            best_score = score
            best_text = context

    return best_y, best_score, best_text


def try_intent(intent: dict[str, Any], lines: list[OcrLine], processed: set[str]) -> bool:
    y, score, context = find_candidate_y(lines, intent)
    if y is None or score < MIN_CANDIDATE_SCORE:
        print(f"[receiver] Sin candidato para intent {intent['intent_id']} S/ {intent['expected_amount']} score={score}")
        return False

    print(f"[receiver] Candidato intent {intent['intent_id']} y={y} score={score}: {context}")
    print(f"[receiver] Intent seleccionado: {json.dumps(intent, ensure_ascii=False)}")
    tap(MOVEMENT_TAP_X, y)
    time.sleep(DETAIL_WAIT_SECONDS)

    detail = capture_screen("receiver-detail")
    payload = parsed_payload(
        detail,
        "receiver",
        {
            "device_serial": DEVICE_SERIAL,
            "target_intent_id": intent.get("intent_id"),
            "target_amount": intent.get("expected_amount"),
            "target_customer_name": intent.get("customer_name"),
            "movement_list_context": context,
            "movement_match_score": score,
        },
    )

    operation_code = payload.get("operation_code")
    print(f"[receiver] OCR detalle ({detail.name}): {compact_text(str(payload.get('raw_text') or ''))}")
    print(f"[receiver] Payload detalle: {json.dumps(payload, ensure_ascii=False, default=str)}")

    if not operation_code:
        print(f"[receiver] Detalle sin operación legible para intent {intent['intent_id']}: {detail.name}")
        back()
        return False

    operation_key = str(operation_code)
    if operation_key in processed:
        print(f"[receiver] Operación ya procesada: {operation_key}")
        back()
        return False

    if payload.get("amount") is None:
        payload["amount"] = intent.get("expected_amount")

    response = post_evidence("receiver-events", payload, detail)
    processed.add(operation_key)
    save_processed(processed)
    print(f"[receiver] Respuesta Laravel receiver-events: {json.dumps(response, ensure_ascii=False)}")
    print(f"[receiver] {detail.name} -> {response}")
    back()
    return True


def passive_monitor() -> None:
    seen_operations: set[str] = set()
    print("[receiver] Monitor pasivo iniciado. Captura solo la pantalla actual.")

    while True:
        try:
            screenshot = capture_screen()
            payload = parsed_payload(screenshot, "receiver", {"device_serial": DEVICE_SERIAL})
            operation_code = payload.get("operation_code")

            if not operation_code:
                print(f"[receiver] Sin operación legible: {screenshot.name}")
                time.sleep(INTERVAL_SECONDS)
                continue

            if operation_code in seen_operations:
                time.sleep(INTERVAL_SECONDS)
                continue

            response = post_evidence("receiver-events", payload, screenshot)
            seen_operations.add(str(operation_code))
            print(f"[receiver] {screenshot.name} -> {response}")
        except Exception as exc:
            print(f"[receiver] error: {exc}")

        time.sleep(INTERVAL_SECONDS)


def navigation_monitor() -> None:
    processed = load_processed()
    print("[receiver] Monitor automático ADB iniciado.")
    print(f"[receiver] Modo configurado: {MODE}")
    print(f"[receiver] ADB: {ADB_PATH} | device: {DEVICE_SERIAL or 'default'} | package: {YAPE_PACKAGE}")

    while True:
        try:
            intents = get_pending_intents()
            if not intents:
                print("[receiver] No hay intents esperando receptor.")
                time.sleep(INTERVAL_SECONDS)
                continue

            open_yape()
            go_to_movements()
            list_screen = capture_screen("receiver-list")
            lines = ocr_lines(list_screen)

            if not lines:
                if is_probably_secure_black_screen(list_screen):
                    print(
                        f"[receiver] Captura de lista negra: {list_screen.name}. "
                        "Yape está bloqueando screenshots; el OCR receptor no puede operar sobre esta pantalla."
                    )
                    if not ALLOW_NAVIGATION_WITH_EMPTY_OCR:
                        raise RuntimeError(
                            "OCR de lista imposible por pantalla segura. "
                            "No se tocarán movimientos a ciegas."
                        )

                print(f"[receiver] OCR de lista vacío: {list_screen.name}")
                time.sleep(INTERVAL_SECONDS)
                continue

            print(f"[receiver] OCR lista ({list_screen.name}): {compact_text(lines_text(lines))}")

            for intent in intents:
                if try_intent(intent, lines, processed):
                    break

        except subprocess.CalledProcessError as exc:
            print(f"[receiver] ADB falló: {exc.stderr.decode(errors='ignore') if exc.stderr else exc}")
        except FileNotFoundError:
            print("[receiver] No se encontró adb. Configura YAPE_ADB_PATH o agrega adb al PATH.")
        except Exception as exc:
            print(f"[receiver] error: {exc}")

        time.sleep(INTERVAL_SECONDS)


def main() -> None:
    if MODE == "passive":
        passive_monitor()
        return

    if MODE != "adb_navigation":
        print(f"[receiver] Modo '{MODE}' no reconocido. Usando adb_navigation.")

    navigation_monitor()


if __name__ == "__main__":
    main()
