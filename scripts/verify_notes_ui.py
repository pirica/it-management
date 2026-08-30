import subprocess
import os
import re
import time
from playwright.sync_api import sync_playwright

def get_session_id():
    result = subprocess.run(['php', 'scripts/bypass_login.php'], capture_output=True, text=True)
    match = re.search(r'Session ID: ([a-z0-9]+)', result.stdout)
    return match.group(1) if match else None

def verify_notes():
    session_id = get_session_id()
    if not session_id:
        print("Failed to get session ID")
        return

    base_url = "http://localhost:8000"

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.add_cookies([{'name': 'PHPSESSID', 'value': session_id, 'domain': 'localhost', 'path': '/'}])
        page = context.new_page()
        page.set_viewport_size({"width": 1280, "height": 1000})

        # 1. Notes Keep View
        print("Verifying Keep View...")
        page.goto(f"{base_url}/modules/notes/index.php")
        time.sleep(2)
        page.screenshot(path="notes_keep_view.png")

        # 2. Table View
        print("Verifying Table View...")
        page.goto(f"{base_url}/modules/notes/list_all.php")
        time.sleep(2)
        page.screenshot(path="notes_table_view.png")

        # 3. Create Form
        print("Verifying Create Form...")
        page.goto(f"{base_url}/modules/notes/edit.php?id=4")
        time.sleep(2)
        page.screenshot(path="notes_create_form.png")

        browser.close()

if __name__ == "__main__":
    verify_notes()
