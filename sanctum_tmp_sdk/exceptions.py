"""Exceptions for the Sanctum Temp Python SDK."""

from __future__ import annotations

from typing import Any, Optional


class SanctumTmpError(Exception):
    """Base error for sanctum_tmp_sdk."""

    def __init__(
        self,
        message: str,
        *,
        status_code: Optional[int] = None,
        response: Any = None,
        code: Optional[str] = None,
    ) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.response = response
        self.code = code


class APIError(SanctumTmpError):
    """Unexpected HTTP or transport failure."""


class ValidationError(SanctumTmpError):
    """Client or server validation failure (4xx)."""


class QrCapacityError(ValidationError):
    """Content will not fit in a QR — fall back to upload."""

    def __init__(
        self,
        message: str,
        *,
        status_code: int = 422,
        response: Any = None,
        encoded: Optional[str] = None,
        content_type: Optional[str] = None,
    ) -> None:
        super().__init__(
            message,
            status_code=status_code,
            response=response,
            code="qr_capacity",
        )
        self.encoded = encoded
        self.content_type = content_type
