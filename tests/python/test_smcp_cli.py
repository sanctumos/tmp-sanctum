"""SMCP tmp plugin smoke (describe + mocked encode)."""

from __future__ import annotations

import json
import unittest
from unittest import mock

from smcp_plugin.tmp import cli


class SmcpTmpCliTests(unittest.TestCase):
    def test_describe_lists_tools(self) -> None:
        parser = cli.build_parser()
        desc = cli.describe_tools(parser)
        self.assertEqual(desc["plugin"]["name"], "tmp")
        self.assertEqual(desc["auth"], "none")
        names = {t["name"] for t in desc["tools"]}
        self.assertIn("tmp__encode", names)
        self.assertIn("tmp__encode_or_upload", names)
        self.assertIn("tmp__upload_file", names)

    @mock.patch("smcp_plugin.tmp.cli.SanctumTmpClient")
    def test_encode_command(self, Client: mock.MagicMock) -> None:
        Client.return_value.encode.return_value = {
            "status": "success",
            "data": {"mode": "qr", "encoded": "hi"},
        }
        rc = cli.main(["--base-url", "https://tmp.test", "encode", "--content", "hi"])
        self.assertEqual(rc, 0)
        Client.return_value.encode.assert_called_once()


if __name__ == "__main__":
    unittest.main()
