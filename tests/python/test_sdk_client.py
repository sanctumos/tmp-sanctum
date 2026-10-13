"""Unit tests for sanctum_tmp_sdk (mocked HTTP)."""

from __future__ import annotations

import io
import json
import unittest
from unittest import mock
from urllib.error import HTTPError

from sanctum_tmp_sdk import (
    APIError,
    QrCapacityError,
    SanctumTmpClient,
    ValidationError,
)


def _http_response(body: bytes, status: int = 200):
    resp = mock.MagicMock()
    resp.status = status
    resp.read.return_value = body
    resp.__enter__.return_value = resp
    resp.__exit__.return_value = False
    return resp


class SanctumTmpClientTests(unittest.TestCase):
    def setUp(self) -> None:
        self.client = SanctumTmpClient(base_url="https://tmp.test")

    @mock.patch("sanctum_tmp_sdk.client.urlopen")
    def test_health(self, urlopen: mock.MagicMock) -> None:
        urlopen.return_value = _http_response(b"# agent notes\n")
        out = self.client.health()
        self.assertEqual(out["status"], "ok")
        self.assertEqual(out["base_url"], "https://tmp.test")
        req = urlopen.call_args[0][0]
        self.assertTrue(str(req.full_url).endswith("/AGENTS.md"))

    @mock.patch("sanctum_tmp_sdk.client.urlopen")
    def test_upload_bytes(self, urlopen: mock.MagicMock) -> None:
        payload = {
            "status": "success",
            "data": {
                "url": "https://tmp.test/id/a.txt",
                "direct_url": "https://tmp.test/uploads/id/a.txt",
                "id": "id",
                "filename": "a.txt",
            },
        }
        urlopen.return_value = _http_response(json.dumps(payload).encode())
        out = self.client.upload_bytes(b"hi", filename="a.txt", mime="text/plain")
        self.assertEqual(out["data"]["direct_url"], "https://tmp.test/uploads/id/a.txt")
        req = urlopen.call_args[0][0]
        self.assertIn("/api/v1/upload.php", str(req.full_url))
        self.assertIn(b"a.txt", req.data)

    @mock.patch("sanctum_tmp_sdk.client.urlopen")
    def test_encode_success(self, urlopen: mock.MagicMock) -> None:
        payload = {
            "status": "success",
            "data": {
                "mode": "qr",
                "encoded": "https://example.com",
                "type": "url",
                "format": "svg",
                "data_uri": "data:image/svg+xml;base64,AA==",
                "persisted": False,
            },
        }
        urlopen.return_value = _http_response(json.dumps(payload).encode())
        out = self.client.encode("example.com")
        self.assertEqual(out["data"]["mode"], "qr")
        self.assertEqual(out["data"]["encoded"], "https://example.com")

    @mock.patch("sanctum_tmp_sdk.client.urlopen")
    def test_encode_capacity(self, urlopen: mock.MagicMock) -> None:
        err_body = json.dumps(
            {
                "status": "error",
                "message": "Content will not fit in a QR code",
                "code": "qr_capacity",
                "encoded": "x" * 100,
            }
        ).encode()
        urlopen.side_effect = HTTPError(
            "https://tmp.test/api/v1/encode.php",
            422,
            "Unprocessable",
            hdrs=None,
            fp=io.BytesIO(err_body),
        )
        with self.assertRaises(QrCapacityError) as ctx:
            self.client.encode("huge")
        self.assertEqual(ctx.exception.code, "qr_capacity")

    @mock.patch("sanctum_tmp_sdk.client.urlopen")
    def test_encode_or_upload_falls_back(self, urlopen: mock.MagicMock) -> None:
        encode_err = json.dumps(
            {"status": "error", "message": "too big", "code": "qr_capacity"}
        ).encode()
        upload_ok = json.dumps(
            {
                "status": "success",
                "data": {
                    "url": "https://tmp.test/id/share.txt",
                    "direct_url": "https://tmp.test/uploads/id/share.txt",
                    "id": "id",
                    "filename": "share.txt",
                },
            }
        ).encode()

        def side_effect(req, timeout=None):
            url = str(req.full_url)
            if "encode.php" in url:
                raise HTTPError(url, 422, "x", hdrs=None, fp=io.BytesIO(encode_err))
            return _http_response(upload_ok)

        urlopen.side_effect = side_effect
        out = self.client.encode_or_upload("payload")
        self.assertEqual(out["sanctum_tmp_mode"], "file")
        self.assertEqual(out["sanctum_tmp_fallback"], "qr_capacity")
        self.assertIn("direct_url", out["data"])

    @mock.patch("sanctum_tmp_sdk.client.urlopen")
    def test_validation_error(self, urlopen: mock.MagicMock) -> None:
        err_body = json.dumps(
            {"status": "error", "message": "Content is required", "code": "empty"}
        ).encode()
        urlopen.side_effect = HTTPError(
            "https://tmp.test/api/v1/encode.php",
            422,
            "x",
            hdrs=None,
            fp=io.BytesIO(err_body),
        )
        with self.assertRaises(ValidationError):
            self.client.encode("")


if __name__ == "__main__":
    unittest.main()
