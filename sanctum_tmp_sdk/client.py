"""
Sanctum Temp HTTP client — upload, QR encode, download.

Public API (no API key). Default base: https://tmp.sanctumos.org
"""

from __future__ import annotations

import json
import mimetypes
import os
from pathlib import Path
from typing import Any, BinaryIO, Dict, List, Optional, Sequence, Union, cast
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

from .exceptions import APIError, QrCapacityError, ValidationError

PathLike = Union[str, Path]
DEFAULT_BASE = "https://tmp.sanctumos.org"
DEFAULT_UA = "sanctum-tmp-sdk/0.1.0 (Python)"


class SanctumTmpClient:
    """
    Client for tmp.sanctumos.org (or a self-hosted Sanctum Temp).

    Example:
        >>> c = SanctumTmpClient()
        >>> r = c.upload_file("photo.png")
        >>> print(r["data"]["direct_url"])
        >>> qr = c.encode("example.com")
        >>> print(qr["data"]["data_uri"][:32])
    """

    def __init__(
        self,
        base_url: str = DEFAULT_BASE,
        *,
        user_agent: str = DEFAULT_UA,
        timeout: float = 120.0,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.user_agent = user_agent
        self.timeout = timeout

    # —— discovery ——

    def health(self) -> Dict[str, Any]:
        """Lightweight liveness: fetch agent discovery markdown."""
        text = self._request_text("GET", "/AGENTS.md")
        return {
            "status": "ok",
            "base_url": self.base_url,
            "agents_md_bytes": len(text.encode("utf-8")),
        }

    # —— upload ——

    def upload_url(self) -> str:
        return f"{self.base_url}/api/v1/upload.php"

    def encode_url(self) -> str:
        return f"{self.base_url}/api/v1/encode.php"

    def upload_file(
        self,
        path: PathLike,
        *,
        filename: Optional[str] = None,
        mime: Optional[str] = None,
        field: str = "file",
    ) -> Dict[str, Any]:
        """Upload one local file via multipart ``file`` (or custom field)."""
        p = Path(path)
        name = filename or p.name
        content_type = mime or mimetypes.guess_type(name)[0] or "application/octet-stream"
        with p.open("rb") as fh:
            return self.upload_bytes(fh.read(), filename=name, mime=content_type, field=field)

    def upload_bytes(
        self,
        data: bytes,
        *,
        filename: str,
        mime: str = "application/octet-stream",
        field: str = "file",
    ) -> Dict[str, Any]:
        """Upload raw bytes as a single multipart part."""
        body, content_type = _multipart([(field, filename, mime, data)])
        return self._request_json(
            "POST",
            "/api/v1/upload.php",
            body=body,
            headers={"Content-Type": content_type},
        )

    def upload_files(
        self,
        paths: Sequence[PathLike],
        *,
        field: str = "files[]",
    ) -> Dict[str, Any]:
        """Upload multiple local files via multipart ``files[]``."""
        parts: List[tuple[str, str, str, bytes]] = []
        for path in paths:
            p = Path(path)
            mime = mimetypes.guess_type(p.name)[0] or "application/octet-stream"
            parts.append((field, p.name, mime, p.read_bytes()))
        if not parts:
            raise ValidationError("No files to upload")
        body, content_type = _multipart(parts)
        return self._request_json(
            "POST",
            "/api/v1/upload.php",
            body=body,
            headers={"Content-Type": content_type},
        )

    def upload_text(
        self,
        text: str,
        *,
        filename: str = "share.txt",
        mime: str = "text/plain",
    ) -> Dict[str, Any]:
        """Host a UTF-8 text blob as a short-lived file."""
        return self.upload_bytes(
            text.encode("utf-8"),
            filename=filename,
            mime=mime,
        )

    # —— QR encode ——

    def encode(
        self,
        content: str,
        *,
        type: str = "auto",
        format: str = "svg",
        ecl: str = "M",
        scale: int = 5,
        persist: bool = False,
        logo_base64: Optional[str] = None,
        logo_size_pct: int = 22,
        filename: str = "payload.txt",
    ) -> Dict[str, Any]:
        """
        QR-first encode via ``POST /api/v1/encode.php``.

        Raises:
            QrCapacityError: content will not fit — caller should ``upload_text``.
            ValidationError: empty / invalid content.
            APIError: other failures.
        """
        payload: Dict[str, Any] = {
            "content": content,
            "type": type,
            "format": format,
            "ecl": ecl,
            "scale": scale,
            "persist": persist,
            "logo_size_pct": logo_size_pct,
            "filename": filename,
        }
        if logo_base64:
            payload["logo_base64"] = logo_base64
        return self._request_json(
            "POST",
            "/api/v1/encode.php",
            body=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json; charset=utf-8"},
        )

    def encode_or_upload(
        self,
        content: str,
        *,
        type: str = "auto",
        format: str = "svg",
        filename: str = "share.txt",
        prefer_qr: bool = True,
    ) -> Dict[str, Any]:
        """
        Match product default: try QR encode; on ``qr_capacity`` (or prefer_qr=False)
        host as a file instead.

        Returns either encode success JSON or upload success JSON.
        Adds ``sanctum_tmp_mode``: ``\"qr\"`` | ``\"file\"``.
        """
        if not prefer_qr:
            out = self.upload_text(content, filename=filename)
            out = dict(out)
            out["sanctum_tmp_mode"] = "file"
            return out
        try:
            out = self.encode(content, type=type, format=format, filename=filename)
            out = dict(out)
            out["sanctum_tmp_mode"] = "qr"
            return out
        except QrCapacityError:
            out = self.upload_text(content, filename=filename)
            out = dict(out)
            out["sanctum_tmp_mode"] = "file"
            out["sanctum_tmp_fallback"] = "qr_capacity"
            return out

    def qr_html_fragment(self, payload: Dict[str, Any]) -> str:
        """
        POST ``/api/v1/qr.php`` — HTML fragment with a QR of the JSON payload
        (website results helper). Most clients should render locally instead.
        """
        body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
        return self._request_text(
            "POST",
            "/api/v1/qr.php",
            body=body,
            headers={"Content-Type": "application/json; charset=utf-8"},
            expect_json=False,
        )

    # —— download ——

    def download(self, direct_url: str) -> bytes:
        """GET raw bytes from a ``direct_url`` (or any absolute URL on this host)."""
        if direct_url.startswith("/"):
            direct_url = self.base_url + direct_url
        req = Request(direct_url, headers={"User-Agent": self.user_agent}, method="GET")
        try:
            with urlopen(req, timeout=self.timeout) as resp:
                return cast(bytes, resp.read())
        except HTTPError as e:
            raise APIError(f"Download failed: HTTP {e.code}", status_code=e.code) from e
        except URLError as e:
            raise APIError(f"Download failed: {e.reason}") from e

    # —— transport ——

    def _request_json(
        self,
        method: str,
        path: str,
        *,
        body: Optional[bytes] = None,
        headers: Optional[Dict[str, str]] = None,
    ) -> Dict[str, Any]:
        text = self._request_text(method, path, body=body, headers=headers, expect_json=True)
        try:
            data = json.loads(text)
        except json.JSONDecodeError as e:
            raise APIError(f"Invalid JSON response: {text[:200]}") from e
        if not isinstance(data, dict):
            raise APIError("JSON response was not an object")
        return data

    def _request_text(
        self,
        method: str,
        path: str,
        *,
        body: Optional[bytes] = None,
        headers: Optional[Dict[str, str]] = None,
        expect_json: bool = False,
    ) -> str:
        url = path if path.startswith("http") else f"{self.base_url}{path}"
        hdrs = {"User-Agent": self.user_agent}
        if headers:
            hdrs.update(headers)
        req = Request(url, data=body, headers=hdrs, method=method)
        try:
            with urlopen(req, timeout=self.timeout) as resp:
                raw = resp.read()
                status = getattr(resp, "status", 200)
                text = raw.decode("utf-8", errors="replace")
        except HTTPError as e:
            raw = e.read() if e.fp else b""
            text = raw.decode("utf-8", errors="replace")
            status = e.code
            if expect_json or (text and text.lstrip().startswith("{")):
                try:
                    data = json.loads(text)
                except json.JSONDecodeError:
                    raise APIError(f"HTTP {status}: {text[:200]}", status_code=status) from e
                self._raise_for_api_error(status, data)
            raise APIError(f"HTTP {status}: {text[:200]}", status_code=status) from e
        except URLError as e:
            raise APIError(f"Request failed: {e.reason}") from e

        if expect_json and status >= 400:
            try:
                data = json.loads(text)
            except json.JSONDecodeError:
                raise APIError(f"HTTP {status}: {text[:200]}", status_code=status)
            self._raise_for_api_error(status, data)

        if expect_json:
            try:
                data = json.loads(text)
            except json.JSONDecodeError:
                return text
            if isinstance(data, dict) and data.get("status") == "error":
                self._raise_for_api_error(status, data)
        return text

    def _raise_for_api_error(self, status: int, data: Dict[str, Any]) -> None:
        message = str(data.get("message") or data.get("error") or f"API error: {status}")
        code = data.get("code")
        if code == "qr_capacity" or (status == 422 and "QR" in message):
            raise QrCapacityError(
                message,
                status_code=status,
                response=data,
                encoded=data.get("encoded"),
                content_type=data.get("type"),
            )
        if status in (400, 413, 422):
            raise ValidationError(message, status_code=status, response=data, code=code)
        raise APIError(message, status_code=status, response=data, code=code)


def _multipart(parts: Sequence[tuple[str, str, str, bytes]]) -> tuple[bytes, str]:
    boundary = f"----SanctumTmp{os.urandom(8).hex()}"
    chunks: List[bytes] = []
    for field, filename, mime, data in parts:
        chunks.append(f"--{boundary}\r\n".encode())
        chunks.append(
            (
                f'Content-Disposition: form-data; name="{field}"; filename="{filename}"\r\n'
                f"Content-Type: {mime}\r\n\r\n"
            ).encode()
        )
        chunks.append(data)
        chunks.append(b"\r\n")
    chunks.append(f"--{boundary}--\r\n".encode())
    return b"".join(chunks), f"multipart/form-data; boundary={boundary}"


def client_from_env() -> SanctumTmpClient:
    """Build a client from ``SANCTUM_TMP_BASE_URL`` (optional)."""
    base = os.environ.get("SANCTUM_TMP_BASE_URL", DEFAULT_BASE)
    ua = os.environ.get("SANCTUM_TMP_USER_AGENT", DEFAULT_UA)
    return SanctumTmpClient(base_url=base, user_agent=ua)
