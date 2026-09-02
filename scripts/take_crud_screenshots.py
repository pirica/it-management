import os
import re
import subprocess
import sys
import time
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

def cookie_domain_for(base_url):
    return urlparse(base_url).hostname or 'localhost'

def prepare_admin_session():
    result = subprocess.run(
        ['php', 'scripts/bypass_login.php'],
        capture_output=True,
        text=True,
        check=False,
    )
    match = re.search(r'Session ID:\s*(\S+)', result.stdout or '')
    if not match:
        raise RuntimeError('bypass_login.php did not return a session id')

    session_dir = subprocess.check_output(
        ['php', '-r', 'echo ini_get("session.save_path");'],
        text=True,
    ).strip()
    session_file = os.path.join(session_dir, f'sess_{match.group(1)}')
    if os.path.isfile(session_file):
        subprocess.run(['sudo', 'chown', 'www-data:www-data', session_file], check=False)
        subprocess.run(['sudo', 'chmod', '664', session_file], check=False)

    return match.group(1)

def run_crud_screenshots():
    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost').rstrip('/')
    output_dir = 'docs/readme/'

    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    session_id = prepare_admin_session()

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(viewport={'width': 1280, 'height': 800})

        # Inject PHPSESSID cookie
        context.add_cookies([
            {
                'name': 'PHPSESSID',
                'value': session_id,
                'domain': cookie_domain_for(base_url),
                'path': '/',
            }
        ])

        page = context.new_page()

        # 1. Navigating to Workstation RAM
        print("Navigating to Workstation RAM...")
        page.goto(f"{base_url}/modules/workstation_ram/index.php", wait_until='domcontentloaded')
        time.sleep(1)
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_1_list.png'))
        print("Saved workstation_ram_1_list.png")

        # 2. Click Create ➕
        print("Clicking Create button...")
        page.locator('a:has-text("➕")').first.click(force=True)
        time.sleep(1)

        # Fill name
        print("Filling form...")
        page.fill("input[name='name']", "64 GB")
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_2_create_form.png'))
        print("Saved workstation_ram_2_create_form.png")

        # Click Save 💾
        print("Saving form...")
        page.locator('button:has-text("💾")').click(force=True)
        time.sleep(2)

        # Force navigation to index.php to ensure we are back
        page.goto(f"{base_url}/modules/workstation_ram/index.php", wait_until='domcontentloaded')
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_3_list_after_create.png'))
        print("Saved workstation_ram_3_list_after_create.png")

        # 3. Click View 🔎 of the new record (last record)
        print("Clicking View button...")
        page.locator('a:has-text("🔎")').last.click(force=True)
        time.sleep(1)
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_4_view.png'))
        print("Saved workstation_ram_4_view.png")

        # 4. Click Back 🔙
        print("Going back...")
        page.locator('a:has-text("🔙")').click(force=True)
        time.sleep(1)

        # 5. Click Edit ✏️ of the new record
        print("Clicking Edit button...")
        page.locator('a:has-text("✏️")').last.click(force=True)
        time.sleep(1)
        page.fill("input[name='name']", "128 GB")
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_5_edit_form.png'))
        print("Saved workstation_ram_5_edit_form.png")

        # Save modifications
        print("Saving edited form...")
        page.locator('button:has-text("💾")').click(force=True)
        time.sleep(2)

        # Force navigation back to index.php
        page.goto(f"{base_url}/modules/workstation_ram/index.php", wait_until='domcontentloaded')
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_6_list_after_edit.png'))
        print("Saved workstation_ram_6_list_after_edit.png")

        # 6. Delete the record
        print("Clicking Delete button...")
        # Playwright dialog handler for delete confirmation
        page.once("dialog", lambda dialog: dialog.accept())
        page.locator('button:has-text("🗑️")').last.click(force=True)
        time.sleep(2)

        # Force navigation back to index.php
        page.goto(f"{base_url}/modules/workstation_ram/index.php", wait_until='domcontentloaded')
        page.screenshot(path=os.path.join(output_dir, 'workstation_ram_7_list_after_delete.png'))
        print("Saved workstation_ram_7_list_after_delete.png")

        browser.close()

if __name__ == '__main__':
    try:
        run_crud_screenshots()
    except Exception as exc:
        print(f"Error during CRUD screenshots: {exc}", file=sys.stderr)
        sys.exit(1)
