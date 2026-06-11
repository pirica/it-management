import subprocess
import os
import re
import time
from playwright.sync_api import sync_playwright

def get_session_id():
    result = subprocess.run(['php', 'scripts/bypass_login.php'], capture_output=True, text=True)
    match = re.search(r'Session ID: ([a-z0-9]+)', result.stdout)
    return match.group(1) if match else None

def take_screenshots():
    session_id = get_session_id()
    base_url = "http://localhost:8000"

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.add_cookies([{'name': 'PHPSESSID', 'value': session_id, 'domain': 'localhost', 'path': '/'}])
        page = context.new_page()
        page.set_viewport_size({"width": 1280, "height": 800})

        # 1. Bookmark
        print("Creating Bookmark...")
        page.goto(f"{base_url}/modules/bookmarks/create.php")

        page.fill("input[name='title']", "Private Bookmark " + str(int(time.time())))
        page.fill("input[name='url']", "https://github.com")
        if page.is_checked("input[name='shared']"):
            page.uncheck("input[name='shared']")

        # Target the button inside form specifically
        page.click("form button.btn-primary")
        page.wait_for_load_state("networkidle")
        time.sleep(2)
        print("URL after bookmark creation:", page.url)
        page.screenshot(path="bookmark_created.png")

        # 3. Private Contacts
        print("Taking Private Contacts screenshot...")
        page.goto(f"{base_url}/modules/private_contacts/index.php")
        page.wait_for_load_state("networkidle")
        time.sleep(2)
        page.screenshot(path="docs/readme/private_contacts_index.png")

        page.goto(f"{base_url}/modules/private_contacts/view.php?id=1")
        page.wait_for_load_state("networkidle")
        time.sleep(2)
        page.screenshot(path="docs/readme/private_contacts_view.png")

        # 2. Password
        print("Creating Password...")
        page.goto(f"{base_url}/modules/passwords/index.php")
        page.wait_for_load_state("networkidle")

        # Open modal via JS directly
        page.evaluate("openEntryModal(0)")
        page.wait_for_selector("#entry-account", state="attached")

        page.evaluate('''() => {
            document.getElementById('entry-account').value = 'Secure Site ' + Date.now();
            document.getElementById('entry-login_name').value = 'admin_user';
            document.getElementById('entry-password').value = 'P@ssword123!';
        }''')

        # Click save inside the entry modal form
        page.click("#entryForm button[type='submit']")
        time.sleep(2)
        print("URL after password creation:", page.url)
        page.screenshot(path="password_created.png")

        browser.close()

if __name__ == "__main__":
    take_screenshots()
