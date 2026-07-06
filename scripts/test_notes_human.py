import os
import time
from playwright.sync_api import sync_playwright
import subprocess
import re

def get_session_id():
    result = subprocess.run(['php', 'scripts/bypass_login.php', '--user', 'Admin', '--company', '1'], capture_output=True, text=True)
    match = re.search(r'Session ID: ([a-z0-9,]+)', result.stdout)
    if not match:
        print(f"DEBUG: {result.stdout}")
    return match.group(1) if match else None

def test_notes():
    session_id = get_session_id()
    if not session_id:
        print("Failed to get session ID")
        return

    # Set session file permissions to be readable by the web server
    session_file = f"/var/lib/php/sessions/sess_{session_id}"
    subprocess.run(['sudo', 'chmod', '0644', session_file])

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.add_cookies([{'name': 'PHPSESSID', 'value': session_id, 'domain': 'localhost', 'path': '/'}])
        page = context.new_page()
        page.set_viewport_size({"width": 1280, "height": 800})

        base_url = "http://localhost:8080"
        
        # Start PHP server
        server_process = subprocess.Popen(['php', '-S', 'localhost:8080'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        time.sleep(2)

        try:
            # 1. Test Quick Add with Reminder
            print("Testing Quick Add...")
            page.goto(f"{base_url}/modules/notes/index.php")
            page.fill("#quickAddInput", "Human Test Note")
            
            # Open Reminder dropdown
            page.click("#quickReminderBtn")
            page.wait_for_selector("#quickReminderDropdown.show")
            
            # Select "Tomorrow"
            page.click("text=Tomorrow")
            
            # Check if button color changed to accent
            btn_style = page.evaluate("document.getElementById('quickReminderBtn').style.color")
            print(f"Reminder button color: {btn_style}")
            
            # Submit Quick Add
            page.keyboard.press("Enter")
            page.wait_for_load_state("networkidle")
            
            print("Note added via Quick Add")
            page.screenshot(path="quick_add_success.png")

            # 2. Test Edit Note
            print("Testing Edit Note...")
            page.click("text=Human Test Note") # View note
            page.wait_for_url(re.compile(r"view.php"))
            page.click("text=Edit")
            page.wait_for_url(re.compile(r"edit.php"))
            
            page.fill("textarea[name='content']", "This content was added by a human (AI).")
            page.click("button[type='submit']")
            page.wait_for_url(re.compile(r"index.php"))
            
            print("Note edited successfully")

            # 3. Test Delete Note
            print("Testing Delete Note...")
            page.click(".note-item:has-text('Human Test Note')")
            page.click("text=Edit")
            
            # Click delete button in form
            page.on("dialog", lambda dialog: dialog.accept())
            page.click("button[name='bulk_action'][value='single_delete']")
            page.wait_for_url(re.compile(r"index.php"))
            
            print("Note deleted successfully")
            
            # Take final screenshot of the index
            page.goto(f"{base_url}/modules/notes/index.php")
            page.screenshot(path="final_notes_index.png")

        finally:
            server_process.terminate()
            browser.close()

if __name__ == "__main__":
    test_notes()
