"""
Capture Ops Report cross-date search hit list after seeding demo sample data.

Requires: pip install playwright && playwright install chromium
Local Apache: ITM_SCREENSHOT_BASE_URL=http://localhost/it-management

CLI:
  python scripts/verify_ops_report_search_screenshot.py
"""

import os
import re
import subprocess
import sys
import time
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

PHP_BIN = os.environ.get(
    'ITM_PHP_BIN',
    r'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe',
)
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT_PATH = os.path.join(REPO_ROOT, 'qa-reports', 'ops_report_search_hits_demo.png')
KEYWORD = os.environ.get('ITM_OPS_SEARCH_DEMO_KEYWORD', 'DemoManager')


def cookie_domain_for(base_url):
    return urlparse(base_url).hostname or 'localhost'


def run_php(args):
    cmd = [PHP_BIN] + args
    result = subprocess.run(
        cmd,
        cwd=REPO_ROOT,
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError((result.stdout or '') + (result.stderr or '') or 'PHP failed')
    return result.stdout or ''


def prepare_admin_session():
    output = run_php(['scripts/bypass_login.php'])
    match = re.search(r'Session ID:\s*(\S+)', output)
    if not match:
        raise RuntimeError('bypass_login.php did not return a session id')
    return match.group(1)


def main():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management').rstrip('/')
    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)

    seed_out = run_php(['scripts/seed_ops_report_search_demo.php', '--keyword=' + KEYWORD])
    print(seed_out.strip())

    session_id = prepare_admin_session()
    search_url = (
        f'{base_url}/modules/ops_report/index.php'
        f'?search={KEYWORD}&search_scope=all'
    )

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        context = browser.new_context(viewport={'width': 1280, 'height': 900})
        context.add_cookies([
            {
                'name': 'PHPSESSID',
                'value': session_id,
                'domain': cookie_domain_for(base_url),
                'path': '/',
            }
        ])
        page = context.new_page()
        page.goto(search_url, wait_until='domcontentloaded', timeout=60000)
        page.wait_for_selector('.opr-cross-date-hits li', timeout=30000)
        time.sleep(0.5)
        card = page.locator('.card:has(.opr-cross-date-hits)').first
        card.screenshot(path=OUTPUT_PATH)
        browser.close()

    print(f'Saved screenshot: {OUTPUT_PATH}')


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'Error: {exc}', file=sys.stderr)
        sys.exit(1)
