#!/usr/bin/env python3
"""Ephemeral php -S + Playwright screenshots of tmp home (mobile + desktop)."""
from __future__ import annotations

import socket
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PUBLIC = ROOT / "public"
OUT = ROOT / "build" / "design-smoke"
OUT.mkdir(parents=True, exist_ok=True)


def free_port() -> int:
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    port = s.getsockname()[1]
    s.close()
    return port


def main() -> int:
    port = free_port()
    php = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(PUBLIC)],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    try:
        time.sleep(0.4)
        from playwright.sync_api import sync_playwright

        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            for name, w, h in (("desktop", 1280, 800), ("mobile", 390, 844)):
                page = browser.new_page(viewport={"width": w, "height": h})
                page.goto(f"http://127.0.0.1:{port}/", wait_until="networkidle", timeout=30000)
                page.wait_for_selector("#qr-content", timeout=10000)
                path = OUT / f"home-{name}.png"
                page.screenshot(path=str(path), full_page=True)
                print("wrote", path)
            browser.close()
    finally:
        php.terminate()
        try:
            php.wait(timeout=3)
        except subprocess.TimeoutExpired:
            php.kill()
    return 0


if __name__ == "__main__":
    sys.exit(main())
