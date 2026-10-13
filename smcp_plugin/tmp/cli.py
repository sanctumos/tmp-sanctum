#!/usr/bin/env python3
"""
Sanctum Temp SMCP plugin — CLI wrapping sanctum_tmp_sdk.SanctumTmpClient.

Env: SANCTUM_TMP_BASE_URL (default https://tmp.sanctumos.org)
     SANCTUM_TMP_USER_AGENT (optional)
No API key — public upload / encode API.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from sanctum_tmp_sdk import (  # noqa: E402
    APIError,
    QrCapacityError,
    SanctumTmpClient,
    ValidationError,
    __version__ as SDK_VERSION,
)

try:
    from smcp_plugin.tmp import __version__ as PLUGIN_VERSION
except ImportError:  # pragma: no cover
    PLUGIN_VERSION = "0.1.0"

PLUGIN = {
    "name": "tmp",
    "version": PLUGIN_VERSION,
    "description": (
        "Sanctum Temp (tmp.sanctumos.org) — ephemeral file upload and QR encode. "
        "Agents: use upload-file / upload-text and GET direct_url. "
        "Humans/text: encode or encode-or-upload (QR-first, file fallback). "
        "No API key."
    ),
    "sdk_version": SDK_VERSION,
}

BASE_URL = os.environ.get("SANCTUM_TMP_BASE_URL", "https://tmp.sanctumos.org").rstrip("/")

COMMAND_HELP: Dict[str, str] = {
    "health": "Liveness check (fetches /AGENTS.md).",
    "upload-file": "Upload a local file via multipart file → JSON with direct_url.",
    "upload-text": "Host UTF-8 text as a short-lived .txt via upload.php.",
    "encode": "QR-encode content via encode.php (raises on qr_capacity).",
    "encode-or-upload": "QR-first; on capacity (or --prefer-file) host as .txt.",
    "download": "GET a direct_url (or path) to --out file.",
    "tool-help": "Short routing cheat sheet for this plugin.",
}


def _error_response(error: str, error_type: str, **extra: Any) -> Dict[str, Any]:
    out: Dict[str, Any] = {"status": "error", "error": error, "error_type": error_type}
    out.update(extra)
    return out


def _success(**data: Any) -> Dict[str, Any]:
    out: Dict[str, Any] = {"status": "success"}
    out.update(data)
    return out


def _wrap(fn: Callable[[], Dict[str, Any]]) -> Dict[str, Any]:
    try:
        return fn()
    except QrCapacityError as e:
        return _error_response(
            str(e),
            "qr_capacity",
            code="qr_capacity",
            encoded=e.encoded,
            content_type=e.content_type,
            hint="Call upload-text or encode-or-upload to host as a file.",
        )
    except ValidationError as e:
        return _error_response(str(e), "validation_error", code=e.code)
    except APIError as e:
        return _error_response(str(e), "api_error", status_code=e.status_code)
    except FileNotFoundError as e:
        return _error_response(str(e), "not_found")
    except Exception as e:
        return _error_response(str(e), "unexpected_error")


def _arg_type_name(action: argparse.Action) -> str:
    if isinstance(action, argparse._StoreTrueAction):
        return "boolean"
    if action.type is int:
        return "integer"
    if action.type is float:
        return "number"
    return "string"


def _canonical_option_name(action: argparse.Action) -> str:
    for option in action.option_strings:
        if option.startswith("--") and "_" not in option:
            return option[2:]
    for option in action.option_strings:
        if option.startswith("--"):
            return option[2:].replace("_", "-")
    return action.dest.replace("_", "-")


def _describe_action(action: argparse.Action) -> Optional[Dict[str, Any]]:
    if action.dest in {"help", "base_url", "describe", "command"}:
        return None
    if action.help == argparse.SUPPRESS:
        return None
    description = action.help or ""
    default_value = None if action.default is argparse.SUPPRESS else action.default
    required = bool(getattr(action, "required", False))
    return {
        "name": _canonical_option_name(action),
        "type": _arg_type_name(action),
        "description": description,
        "required": required,
        "default": default_value,
    }


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description=PLUGIN["description"])
    p.add_argument("--base-url", default=BASE_URL, help="Temp site origin")
    p.add_argument("--describe", action="store_true", help="Emit SMCP tool schema JSON")
    sub = p.add_subparsers(dest="command")

    sub.add_parser("health", help=COMMAND_HELP["health"])

    g = sub.add_parser("upload-file", help=COMMAND_HELP["upload-file"])
    g.add_argument("--path", required=True, help="Local file path")
    g.add_argument("--filename", default="", help="Override multipart filename")
    g.add_argument("--mime", default="", help="Override Content-Type")

    g = sub.add_parser("upload-text", help=COMMAND_HELP["upload-text"])
    g.add_argument("--content", required=True, help="UTF-8 text to host")
    g.add_argument("--filename", default="share.txt", help="Stored filename")

    g = sub.add_parser("encode", help=COMMAND_HELP["encode"])
    g.add_argument("--content", required=True, help="Text / URL / email / tel")
    g.add_argument("--type", default="auto", help="auto|url|email|tel|text")
    g.add_argument("--format", default="svg", help="svg|png")
    g.add_argument("--ecl", default="M", help="L|M|Q|H")
    g.add_argument("--persist", action="store_true", help="Also store payload as a file")
    g.add_argument("--filename", default="payload.txt", help="Filename when --persist")

    g = sub.add_parser("encode-or-upload", help=COMMAND_HELP["encode-or-upload"])
    g.add_argument("--content", required=True, help="Text / URL / email / tel")
    g.add_argument("--type", default="auto", help="auto|url|email|tel|text")
    g.add_argument("--format", default="svg", help="svg|png")
    g.add_argument("--filename", default="share.txt", help="Filename if hosted as file")
    g.add_argument(
        "--prefer-file",
        action="store_true",
        help="Skip QR and upload as .txt immediately",
    )

    g = sub.add_parser("download", help=COMMAND_HELP["download"])
    g.add_argument("--url", required=True, help="direct_url (or absolute URL)")
    g.add_argument("--out", required=True, help="Local path to write bytes")

    sub.add_parser("tool-help", help=COMMAND_HELP["tool-help"])
    return p


def describe_tools(parser: argparse.ArgumentParser) -> Dict[str, Any]:
    sub = None
    for action in parser._actions:
        if isinstance(action, argparse._SubParsersAction):
            sub = action
            break
    tools: List[Dict[str, Any]] = []
    if sub:
        for name, sp in sorted(sub.choices.items()):
            params = []
            for a in sp._actions:
                d = _describe_action(a)
                if d:
                    params.append(d)
            tools.append(
                {
                    "name": f"tmp__{name.replace('-', '_')}",
                    "description": COMMAND_HELP.get(name, sp.description or name),
                    "parameters": params,
                }
            )
    return {
        "plugin": PLUGIN,
        "base_url_default": BASE_URL,
        "auth": "none",
        "tools": tools,
    }


def tool_help() -> Dict[str, Any]:
    return _success(
        routing={
            "agents_broca_venice": "upload-file or upload-text → use data.direct_url",
            "short_text_qr": "encode or encode-or-upload",
            "qr_too_large": "encode returns qr_capacity → upload-text / encode-or-upload",
            "do_not": "Do not scrape HTML / or view URLs for automation",
        },
        docs={
            "api": "docs/API.md",
            "sdk": "docs/SDK.md",
            "agents": "AGENTS.md",
        },
        commands=COMMAND_HELP,
    )


def run_command(args: argparse.Namespace) -> Dict[str, Any]:
    client = SanctumTmpClient(base_url=args.base_url)

    if args.command == "health":
        return _wrap(lambda: _success(result=client.health()))

    if args.command == "upload-file":
        def _up() -> Dict[str, Any]:
            kwargs: Dict[str, Any] = {}
            if args.filename:
                kwargs["filename"] = args.filename
            if args.mime:
                kwargs["mime"] = args.mime
            return _success(result=client.upload_file(args.path, **kwargs))

        return _wrap(_up)

    if args.command == "upload-text":
        return _wrap(
            lambda: _success(
                result=client.upload_text(args.content, filename=args.filename),
            )
        )

    if args.command == "encode":
        return _wrap(
            lambda: _success(
                result=client.encode(
                    args.content,
                    type=args.type,
                    format=args.format,
                    ecl=args.ecl,
                    persist=bool(args.persist),
                    filename=args.filename,
                ),
            )
        )

    if args.command == "encode-or-upload":
        return _wrap(
            lambda: _success(
                result=client.encode_or_upload(
                    args.content,
                    type=args.type,
                    format=args.format,
                    filename=args.filename,
                    prefer_qr=not bool(args.prefer_file),
                ),
            )
        )

    if args.command == "download":
        def _dl() -> Dict[str, Any]:
            data = client.download(args.url)
            out = Path(args.out)
            out.parent.mkdir(parents=True, exist_ok=True)
            out.write_bytes(data)
            return _success(bytes=len(data), path=str(out.resolve()))

        return _wrap(_dl)

    if args.command == "tool-help":
        return tool_help()

    return _error_response(f"Unknown command: {args.command}", "validation_error")


def main(argv: Optional[List[str]] = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    if args.describe:
        print(json.dumps(describe_tools(parser), indent=2))
        return 0
    if not args.command:
        parser.print_help()
        return 2
    result = run_command(args)
    print(json.dumps(result, indent=2, default=str))
    return 0 if result.get("status") == "success" else 1


if __name__ == "__main__":
    raise SystemExit(main())
