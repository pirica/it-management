"""
Human-flow scripts catalog filter screenshots + assertions.

Requires: pip install playwright && playwright install chromium
Local Apache: ITM_SCREENSHOT_BASE_URL=http://localhost/it-management

CLI:
  python scripts/verify_scripts_catalog_filter_screenshot.py
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
OUTPUT_DIR = os.path.join(REPO_ROOT, 'qa-reports', 'scripts_catalog_filter')

SHOTS = [
    ('00_catalog_default.png', 'default'),
    ('01_chip_info.png', 'chip_info'),
    ('02_search_json.png', 'search_json'),
    ('03_search_txt.png', 'search_txt'),
    ('04_search_md.png', 'search_md'),
    ('05_chip_md_alias.png', 'chip_md'),
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
    output = (result.stdout or '') + (result.stderr or '')
    if result.returncode != 0:
        raise RuntimeError(output or 'PHP failed')
    return output


def parse_verify_counts(output):
    """Read expected totals from verify_scripts_catalog_filter.php stdout."""
    patterns = {
        'total': r'Catalog rows parsed:\s*(\d+)',
        'info': r'Rows with Info tag:\s*(\d+)',
        'markdown': r'Rows with Markdown tag:\s*(\d+)',
        'json_hits': r'Simulated search \*\.json hits:\s*(\d+)',
        'txt_hits': r'Simulated search \*\.txt hits:\s*(\d+)',
        'md_hits': r'Simulated search \*\.md hits:\s*(\d+)',
    }
    counts = {}
    for key, pattern in patterns.items():
        match = re.search(pattern, output)
        if not match:
            raise RuntimeError(f'Could not parse {key} from verify_scripts_catalog_filter.php output')
        counts[key] = int(match.group(1))
    if 'PASS' not in output:
        raise RuntimeError('verify_scripts_catalog_filter.php did not PASS before screenshots')
    return counts


def prepare_admin_session():
    output = run_php(['scripts/bypass_login.php'])
    match = re.search(r'Session ID:\s*(\S+)', output)
    if not match:
        raise RuntimeError('bypass_login.php did not return a session id')
    return match.group(1)


def catalog_url(base_url):
    return f'{base_url}/scripts/scripts.php'


def wait_catalog_ready(page):
    page.wait_for_selector('#scripts-catalog-filter', timeout=30000)
    page.wait_for_selector('#scripts-tag-filter-bar .scripts-tag-chip', timeout=30000)
    page.wait_for_selector('.scripts-catalog tbody tr', timeout=30000)
    page.wait_for_function(
        """() => {
            const bar = document.getElementById('scripts-tag-filter-bar');
            return bar && bar.querySelectorAll('.scripts-tag-chip').length > 2;
        }""",
        timeout=30000,
    )


def visible_row_count(page):
    return page.evaluate(
        """() => document.querySelectorAll(
            '.scripts-catalog tbody tr:not(.scripts-catalog-hidden)'
        ).length"""
    )


def filter_count_text(page):
    el = page.locator('#scripts-catalog-filter-count')
    if el.count() == 0:
        return ''
    return el.inner_text().strip()


def reset_filters(page):
    page.locator('#scripts-catalog-filter').fill('')
    all_chip = page.locator('#scripts-tag-filter-bar .scripts-tag-chip[data-tag=""]').first
    all_chip.click()
    time.sleep(0.25)


def click_tag_chip(page, tag):
    chip = page.locator(f'#scripts-tag-filter-bar .scripts-tag-chip[data-tag="{tag}"]').first
    chip.wait_for(state='visible', timeout=10000)
    chip.click()
    time.sleep(0.35)


def set_search(page, query):
    page.locator('#scripts-catalog-filter').fill(query)
    time.sleep(0.35)


def assert_visible_count(page, expected, label):
    actual = visible_row_count(page)
    if actual != expected:
        raise AssertionError(
            f'{label}: expected {expected} visible catalog rows, got {actual} '
            f'(counter: {filter_count_text(page)!r})'
        )


def assert_tags_column_label(page):
    page.wait_for_function(
        """() => {
            const cell = document.querySelector('.scripts-catalog tbody tr:not(.scripts-catalog-hidden) .scripts-tags-cell');
            if (!cell) return false;
            const before = window.getComputedStyle(cell, '::before').content;
            return before && before.replace(/"/g, '').toLowerCase().indexOf('tags') !== -1;
        }""",
        timeout=10000,
    )


def assert_first_visible_has_tag_kind(page, kind):
    page.wait_for_function(
        f"""() => {{
            const row = document.querySelector('.scripts-catalog tbody tr:not(.scripts-catalog-hidden)');
            if (!row) return false;
            return row.querySelector('.scripts-badge-tag[data-tag-kind="{kind}"]') !== null;
        }}""",
        timeout=10000,
    )


def screenshot_catalog_region(page, path):
    wrap = page.locator('.scripts-wrap').first
    wrap.screenshot(path=path)


def capture_default(page, base_url, path, counts):
    page.goto(catalog_url(base_url), wait_until='domcontentloaded', timeout=60000)
    if 'login.php' in page.url:
        raise RuntimeError('Redirected to login.php — session cookie not accepted')
    wait_catalog_ready(page)
    reset_filters(page)
    assert_visible_count(page, counts['total'], 'default view')
    assert_tags_column_label(page)
    screenshot_catalog_region(page, path)


def capture_chip_info(page, base_url, path, counts):
    reset_filters(page)
    click_tag_chip(page, 'Info')
    assert_visible_count(page, counts['info'], 'Info chip')
    assert_first_visible_has_tag_kind(page, 'info')
    screenshot_catalog_region(page, path)


def capture_search_json(page, base_url, path, counts):
    reset_filters(page)
    set_search(page, '*.json')
    assert_visible_count(page, counts['json_hits'], 'search *.json')
    assert_first_visible_has_tag_kind(page, 'info')
    screenshot_catalog_region(page, path)


def capture_search_txt(page, base_url, path, counts):
    reset_filters(page)
    set_search(page, '*.txt')
    assert_visible_count(page, counts['txt_hits'], 'search *.txt')
    assert_first_visible_has_tag_kind(page, 'info')
    screenshot_catalog_region(page, path)


def capture_search_md(page, base_url, path, counts):
    reset_filters(page)
    set_search(page, '*.md')
    assert_visible_count(page, counts['md_hits'], 'search *.md')
    assert_first_visible_has_tag_kind(page, 'markdown')
    screenshot_catalog_region(page, path)


def capture_chip_md(page, base_url, path, counts):
    reset_filters(page)
    click_tag_chip(page, '*.md')
    assert_visible_count(page, counts['md_hits'], '*.md chip')
    assert_first_visible_has_tag_kind(page, 'markdown')
    screenshot_catalog_region(page, path)


CAPTURES = {
    'default': capture_default,
    'chip_info': capture_chip_info,
    'search_json': capture_search_json,
    'search_txt': capture_search_txt,
    'search_md': capture_search_md,
    'chip_md': capture_chip_md,
}


def main():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management').rstrip('/')
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    verify_out = run_php(['scripts/verify_scripts_catalog_filter.php'])
    print(verify_out.strip())
    counts = parse_verify_counts(verify_out)

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
            CAPTURES[capture_key](page, base_url, output_path, counts)
            saved.append(output_path)
            print(f'[PASS] {capture_key} -> {output_path}')

        browser.close()

    print(f'\n{len(saved)} screenshots verified in {OUTPUT_DIR}')
    print('Counts used:', counts)


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'Error: {exc}', file=sys.stderr)
        sys.exit(1)
