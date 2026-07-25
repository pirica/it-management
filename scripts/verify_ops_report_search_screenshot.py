"""
Human-flow Ops Report search screenshots after seeding demo sample data.

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
OUTPUT_DIR = os.path.join(REPO_ROOT, 'qa-reports', 'ops_report_search')
KEYWORD = os.environ.get('ITM_OPS_SEARCH_DEMO_KEYWORD', 'DemoManager')

SHOTS = [
    ('01_all_dates_hits.png', 'all_dates_hits'),
    ('02_section_guest_experience.png', 'section_guest'),
    ('03_sort_sections_asc.png', 'sort_sections'),
    ('04_this_day_from_hit.png', 'this_day_hit'),
    ('05_search_bar_filled.png', 'search_bar'),
]


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


def url_for(base_url, **params):
    query = '&'.join(f'{key}={value}' for key, value in params.items())
    return f'{base_url}/modules/ops_report/index.php?{query}'


def wait_hits_table(page):
    page.wait_for_selector('.opr-cross-date-hits-table tbody tr', timeout=30000)


def screenshot_hits_card(page, path):
    card = page.locator('.card:has(.opr-cross-date-hits-table)').first
    card.screenshot(path=path)


def capture_all_dates_hits(page, base_url, path):
    page.goto(
        url_for(base_url, search=KEYWORD, search_scope='all'),
        wait_until='domcontentloaded',
        timeout=60000,
    )
    wait_hits_table(page)
    time.sleep(0.4)
    screenshot_hits_card(page, path)


def capture_section_guest(page, base_url, path):
    page.goto(
        url_for(
            base_url,
            search=KEYWORD,
            search_scope='all',
            search_section='guest_experience',
        ),
        wait_until='domcontentloaded',
        timeout=60000,
    )
    wait_hits_table(page)
    time.sleep(0.4)
    screenshot_hits_card(page, path)


def capture_sort_sections(page, base_url, path):
    page.goto(
        url_for(
            base_url,
            search=KEYWORD,
            search_scope='all',
            sort='sections',
            dir='ASC',
        ),
        wait_until='domcontentloaded',
        timeout=60000,
    )
    wait_hits_table(page)
    time.sleep(0.4)
    screenshot_hits_card(page, path)


def capture_this_day_from_hit(page, base_url, path):
    page.goto(
        url_for(base_url, search=KEYWORD, search_scope='all'),
        wait_until='domcontentloaded',
        timeout=60000,
    )
    wait_hits_table(page)
    first_hit = page.locator('.opr-cross-date-hits-table tbody tr a').first
    first_hit.click()
    page.wait_for_load_state('domcontentloaded', timeout=60000)
    page.wait_for_selector('#opr-report-root', timeout=30000)
    time.sleep(0.5)
    page.locator('.card:has(#moduleSearch)').first.screenshot(path=path)


def capture_search_bar(page, base_url, path):
    page.goto(
        url_for(base_url, search=KEYWORD, search_scope='all'),
        wait_until='domcontentloaded',
        timeout=60000,
    )
    wait_hits_table(page)
    time.sleep(0.3)
    page.locator('.card:has(#moduleSearch)').first.screenshot(path=path)


CAPTURES = {
    'all_dates_hits': capture_all_dates_hits,
    'section_guest': capture_section_guest,
    'sort_sections': capture_sort_sections,
    'this_day_hit': capture_this_day_from_hit,
    'search_bar': capture_search_bar,
}


def main():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management').rstrip('/')
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    seed_out = run_php(['scripts/seed_ops_report_search_demo.php', '--keyword=' + KEYWORD])
    print(seed_out.strip())

    session_id = prepare_admin_session()

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

        saved = []
        for filename, capture_key in SHOTS:
            output_path = os.path.join(OUTPUT_DIR, filename)
            CAPTURES[capture_key](page, base_url, output_path)
            saved.append(output_path)
            print(f'Saved screenshot: {output_path}')

        browser.close()

    print(f'\n{len(saved)} screenshots in {OUTPUT_DIR}')


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'Error: {exc}', file=sys.stderr)
        sys.exit(1)
