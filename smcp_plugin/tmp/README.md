# Sanctum Temp SMCP plugin

Wraps `sanctum_tmp_sdk.SanctumTmpClient` for agent / SMCP hosts.

```bash
# from repo root
export SANCTUM_TMP_BASE_URL=https://tmp.sanctumos.org   # optional
python3 smcp_plugin/tmp/cli.py --describe
python3 smcp_plugin/tmp/cli.py health
python3 smcp_plugin/tmp/cli.py upload-file --path ./photo.png
python3 smcp_plugin/tmp/cli.py encode --content 'hello'
python3 smcp_plugin/tmp/cli.py encode-or-upload --content 'example.com'
```

No API key — Temp endpoints are public. See [`docs/SDK.md`](../../docs/SDK.md).
