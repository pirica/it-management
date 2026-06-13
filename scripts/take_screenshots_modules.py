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
    if not session_id:
        print("Failed to get session ID")
        return

    base_url = "http://localhost:8000"
    output_dir = "docs/readme/"

    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    modules_dir = "modules"
    modules = ["bookmarks", "todo"]
    modules.sort()

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.add_cookies([{'name': 'PHPSESSID', 'value': session_id, 'domain': 'localhost', 'path': '/'}])
        page = context.new_page()
        page.set_viewport_size({"width": 1280, "height": 800})

        for module in modules:
            screenshot_path = os.path.join(output_dir, f"{module}.png")

            if os.path.exists(screenshot_path):
                print(f"Skipping {module}, screenshot already exists.")
                continue

            url = f"{base_url}/modules/{module}/index.php"
            print(f"Taking screenshot of {module} at {url}...")

            try:
                page.goto(url)
                # Wait 3 seconds as requested
                time.sleep(3)
                page.screenshot(path=screenshot_path)
                print(f"Saved {screenshot_path}")
            except Exception as e:
                print(f"Failed to take screenshot of {module}: {e}")

        browser.close()

if __name__ == "__main__":
    take_screenshots()
