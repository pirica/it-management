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

    base_url = os.environ.get('ITM_SCREENSHOT_BASE_URL', 'http://localhost/it-management')
    output_dir = "docs/readme/"

    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    modules = ["todo", "notes", "system_status"]
    only_system_status = os.environ.get('ITM_SCREENSHOT_ONLY', '').strip().lower() in ('system_status', '1', 'true', 'yes')
    if only_system_status:
        modules = ["system_status"]
    modules.sort()

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.add_cookies([{'name': 'PHPSESSID', 'value': session_id, 'domain': 'localhost', 'path': '/'}])
        page = context.new_page()
        page.set_viewport_size({"width": 1280, "height": 800})

        for module in modules:
            if module == 'system_status':
                screenshot_path = os.path.join(output_dir, "system_status.png")
                url = f"{base_url}/modules/{module}/index.php?tab=monitoring"
                print(f"Taking screenshot of {module} monitoring tab at {url}...")
                try:
                    page.goto(url, wait_until='networkidle', timeout=60000)
                    time.sleep(2)
                    page.screenshot(path=screenshot_path)
                    print(f"Saved {screenshot_path}")
                except Exception as e:
                    print(f"Failed to take screenshot of {module}: {e}")
                continue

            # Index
            screenshot_path = os.path.join(output_dir, f"{module}.png")
            url = f"{base_url}/modules/{module}/index.php"
            print(f"Taking screenshot of {module} index at {url}...")
            try:
                page.goto(url)
                time.sleep(3)
                page.screenshot(path=screenshot_path)
                print(f"Saved {screenshot_path}")
            except Exception as e:
                print(f"Failed to take screenshot of {module} index: {e}")

            # List All (Table View)
            if module == 'notes':
                table_screenshot_path = os.path.join(output_dir, f"{module}_table.png")
                table_url = f"{base_url}/modules/{module}/list_all.php"
                print(f"Taking screenshot of {module} table view at {table_url}...")
                try:
                    page.goto(table_url)
                    time.sleep(3)
                    page.screenshot(path=table_screenshot_path)
                    print(f"Saved {table_screenshot_path}")
                except Exception as e:
                    print(f"Failed to take screenshot of {module} table view: {e}")

            # Create
            create_screenshot_path = os.path.join(output_dir, f"{module}_create.png")
            if module == 'notes':
                create_url = f"{base_url}/modules/{module}/edit.php?id=4"
            else:
                create_url = f"{base_url}/modules/{module}/create.php"
            print(f"Taking screenshot of {module} create at {create_url}...")
            try:
                page.goto(create_url)
                time.sleep(3)
                page.screenshot(path=create_screenshot_path)
                print(f"Saved {create_screenshot_path}")
            except Exception as e:
                print(f"Failed to take screenshot of {module} create: {e}")

        browser.close()

if __name__ == "__main__":
    take_screenshots()
