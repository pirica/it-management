import asyncio
from playwright.async_api import async_playwright
import os

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context()

        # Get session ID from bypass_login
        import subprocess
        output = subprocess.check_output(['php', 'scripts/bypass_login.php', '--user', 'Admin', '--company', '1']).decode()
        sess_id = [line for line in output.split('\n') if 'Session ID:' in line][0].split(': ')[1].strip()

        await context.add_cookies([
            {'name': 'PHPSESSID', 'value': sess_id, 'domain': 'localhost', 'path': '/'}
        ])

        page = await context.new_page()

        # 1. Check List View
        await page.goto('http://localhost:8080/modules/todo_categories/index.php')
        await page.screenshot(path='todo_categories_list.png')
        print("Captured todo_categories_list.png")

        # 2. Check Create Form
        await page.goto('http://localhost:8080/modules/todo_categories/create.php')
        await page.screenshot(path='todo_categories_create.png')
        print("Captured todo_categories_create.png")

        # Check if Category from User is hidden
        is_hidden = await page.is_hidden('text="Category from User"')
        # Note: if it's a hidden input, the label shouldn't even be there because I added 'continue' in the loop
        label_exists = await page.query_selector('text="Category from User"')
        print(f"Label 'Category from User' exists in create form: {label_exists is not None}")

        # 3. Check View Page (if there's any record)
        # For now just screenshots are good to see labels

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())
