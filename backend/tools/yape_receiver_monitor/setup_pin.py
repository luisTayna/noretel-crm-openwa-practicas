from __future__ import annotations

import argparse
import ctypes
import getpass
import os
import re
import sys
from ctypes import wintypes


CRED_TYPE_GENERIC = 1
CRED_PERSIST_LOCAL_MACHINE = 2

DEFAULT_SERVICE = "noretel-yape-receiver"
DEFAULT_USERNAME = "default"


class CREDENTIALW(ctypes.Structure):
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


advapi32 = ctypes.WinDLL("Advapi32", use_last_error=True)
advapi32.CredWriteW.argtypes = [ctypes.POINTER(CREDENTIALW), wintypes.DWORD]
advapi32.CredWriteW.restype = wintypes.BOOL
advapi32.CredDeleteW.argtypes = [wintypes.LPCWSTR, wintypes.DWORD, wintypes.DWORD]
advapi32.CredDeleteW.restype = wintypes.BOOL


def env(name: str, default: str) -> str:
    value = os.getenv(name)
    return value if value not in (None, "") else default


def credential_target(service: str, username: str) -> str:
    safe_service = service.strip() or DEFAULT_SERVICE
    safe_username = username.strip() or DEFAULT_USERNAME
    return f"{safe_service}:{safe_username}"


def validate_pin(pin: str) -> str:
    pin = pin.strip()

    if not re.fullmatch(r"\d{4,8}", pin):
        raise ValueError("La clave debe tener solo números y entre 4 y 8 dígitos.")

    return pin


def prompt_pin() -> str:
    pin = validate_pin(getpass.getpass("Ingresa clave Yape: "))
    confirmation = validate_pin(getpass.getpass("Confirma clave Yape: "))

    if pin != confirmation:
        raise ValueError("Las claves no coinciden. No se guardó nada.")

    return pin


def write_credential(target: str, username: str, secret: str) -> None:
    blob = secret.encode("utf-16-le")
    blob_buffer = ctypes.create_string_buffer(blob)

    credential = CREDENTIALW()
    credential.Flags = 0
    credential.Type = CRED_TYPE_GENERIC
    credential.TargetName = target
    credential.Comment = "NoreTel Yape receiver PIN. No guardar este secreto en .env, Git ni logs."
    credential.CredentialBlobSize = len(blob)
    credential.CredentialBlob = ctypes.cast(blob_buffer, ctypes.POINTER(wintypes.BYTE))
    credential.Persist = CRED_PERSIST_LOCAL_MACHINE
    credential.AttributeCount = 0
    credential.Attributes = None
    credential.TargetAlias = None
    credential.UserName = username

    if not advapi32.CredWriteW(ctypes.byref(credential), 0):
        error = ctypes.get_last_error()
        raise ctypes.WinError(error)


def delete_credential(target: str) -> bool:
    if advapi32.CredDeleteW(target, CRED_TYPE_GENERIC, 0):
        return True

    error = ctypes.get_last_error()

    # ERROR_NOT_FOUND
    if error == 1168:
        return False

    raise ctypes.WinError(error)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Guarda o elimina la clave Yape en Windows Credential Manager sin escribirla en archivos.",
    )
    parser.add_argument(
        "--service",
        default=env("YAPE_PIN_CREDENTIAL_SERVICE", DEFAULT_SERVICE),
        help=f"Nombre del servicio en Credential Manager. Default: {DEFAULT_SERVICE}",
    )
    parser.add_argument(
        "--username",
        default=env("YAPE_PIN_CREDENTIAL_USERNAME", env("YAPE_DEVICE_SERIAL", DEFAULT_USERNAME)),
        help="Usuario/identificador del secreto. Recomendado: serial ADB del celular.",
    )
    parser.add_argument(
        "--delete",
        action="store_true",
        help="Elimina la clave guardada para este service/username.",
    )
    return parser.parse_args()


def main() -> int:
    if os.name != "nt":
        print("Este script usa Windows Credential Manager y solo funciona en Windows.", file=sys.stderr)
        return 1

    args = parse_args()
    target = credential_target(args.service, args.username)

    try:
        if args.delete:
            deleted = delete_credential(target)
            if deleted:
                print(f"Clave Yape eliminada de Windows Credential Manager: {target}")
            else:
                print(f"No había clave guardada para: {target}")
            return 0

        pin = prompt_pin()
        write_credential(target, args.username, pin)
        print("Clave Yape guardada en Windows Credential Manager.")
        print(f"Servicio: {args.service}")
        print(f"Usuario: {args.username}")
        print("IMPORTANTE: no la pongas en .env, README, Git, PowerShell history ni logs.")
        return 0
    except Exception as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
