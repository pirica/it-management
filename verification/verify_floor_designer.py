from playwright.sync_api import sync_playwright, expect
import time
import re

def run_verification():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1280, 'height': 900})
        page = context.new_page()
        
        print("Logging in...")
        page.goto("http://localhost:8080/login.php")
        page.fill('input[name="email"]', "Admin")
        page.fill('input[name="password"]', "Admin")
        page.click('button[type="submit"]')
        page.wait_for_selector('text=Logout', timeout=10000)

        print("Navigating to Floor Designer...")
        page.goto("http://localhost:8080/modules/floor_designer/index.php")
        time.sleep(1)
        
        print("Creating new floor plan...")
        page.click('a:has-text("New Floor Plan")')
        page.wait_for_selector('input[name="name"]')
        page.fill('input[name="name"]', "Test Floor PV")
        page.fill('input[name="sq_meters"]', "120")
        
        # Take screenshot of the form before submit
        page.screenshot(path="verification/create_form.png")
        
        page.click('button[type="submit"]')
        time.sleep(3)
        
        print(f"URL after create: {page.url}")
        if "edit.php" in page.url:
            print("In designer edit mode.")
            page.wait_for_selector('#floor-shape')
            page.screenshot(path="verification/designer_empty.png")
            
            print("Adding a network point...")
            page.click('text=Add Network Point')
            page.wait_for_selector('#point-modal', state="visible")
            page.fill('#modal-label', "PV-1")
            page.click('button:has-text("Save Changes")')
            
            time.sleep(3)
            page.screenshot(path="verification/designer_with_point.png")
            print("Verification successful.")
        else:
            print(f"Failed to redirect to edit.php. Current URL: {page.url}")
            page.screenshot(path="verification/error_state.png")
            
        browser.close()

if __name__ == "__main__":
    run_verification()
