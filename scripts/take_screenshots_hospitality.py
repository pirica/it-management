"""Capture 1280x800 PNG screenshots for all Hospitality sidebar modules."""
import os
import re
import subprocess
import sys
import time

from playwright.sync_api import sync_playwright

HOSPITALITY_MODULES = [
    'hotel_bookings',
    'hotel_booking_hotels',
    'booking_rooms_types',
    'hotel_booking_rooms',
    'hotel_booking_amenities',
    'hotel_booking_special_rates',
    'hotel_booking_portal_rate_plans',
    'hotel_booking_room_utilities',
    'hotel_booking_housekeeping_statuses',
    'hotel_bookings_future',
    'hotel_bookings_present',
    'hotel_bookings_history',
    'hotel_booking_settings',
]


def php_bin():
    exe = os.environ.get('PHP_EXE', '').strip()
    if exe and os.path.isfile(exe):
        return exe
    return 'php'


def prepare_admin_session():
    result = subprocess.run(
        [php_bin(), 'scripts/bypass_login.php'],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError((result.stderr or result.stdout or 'bypass_login failed').strip())
    match = re.search(r'Session ID:\s*(\S+)', result.stdout or '')
    if not match:
        raise RuntimeError('bypass_login.php did not return a session id')
    return match.group(1)


def login_via_form(page, base_url):
    """Admin form login when bypass cookie path mismatches CLI vs Apache."""
    page.goto(f'{base_url}/login.php', wait_until='domcontentloaded', timeout=30000)
    page.fill('input[name="email"]', 'Admin')
    page.fill('input[name="password"]', 'Admin')
    page.click('button[type="submit"], input[type="submit"]')
    page.wait_for_load_state('domcontentloaded', timeout=30000)
    if 'login.php' in page.url:
        raise RuntimeError('Form login failed — still on login.php')


def take_screenshots():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management').rstrip('/')
    output_dir = os.environ.get(
        'ITM_HOSPITALITY_SCREENSHOT_DIR',
        os.path.join('qa-reports', 'hospitality-screenshots'),
    )
    os.makedirs(output_dir, exist_ok=True)

    use_form_login = os.environ.get('ITM_SCREENSHOT_FORM_LOGIN', '').strip().lower() in (
        '1', 'true', 'yes',
    )

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(viewport={'width': 1280, 'height': 800})
        page = context.new_page()

        if use_form_login:
            login_via_form(page, base_url)
        else:
            session_id = prepare_admin_session()
            context.add_cookies([
                {
                    'name': 'PHPSESSID',
                    'value': session_id,
                    'domain': 'localhost',
                    'path': '/',
                }
            ])
            page.goto(f'{base_url}/dashboard.php', wait_until='domcontentloaded', timeout=30000)
            if 'login.php' in page.url:
                print('Cookie session rejected — falling back to form login', file=sys.stderr)
                login_via_form(page, base_url)

        for module in HOSPITALITY_MODULES:
            url = f'{base_url}/modules/{module}/index.php'
            path = os.path.join(output_dir, f'{module}.png')
            print(f'Capturing {module} ...')
            page.goto(url, wait_until='domcontentloaded', timeout=60000)
            if 'login.php' in page.url:
                raise RuntimeError(f'Redirected to login for {module}')
            time.sleep(2)
            page.screenshot(path=path, full_page=False)
            print(f'Saved {path}')

        browser.close()

    print(f'Done — {len(HOSPITALITY_MODULES)} screenshots in {output_dir}')


if __name__ == '__main__':
    try:
        take_screenshots()
    except Exception as exc:
        print(f'Screenshot capture failed: {exc}', file=sys.stderr)
        sys.exit(1)
