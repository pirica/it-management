import asyncio
from playwright.async_api import async_playwright

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context()

        await context.add_cookies([{
            'name': 'PHPSESSID',
            'value': 'gojjv3ibsje5ls0imaevq5shj3',
            'domain': 'localhost',
            'path': '/'
        }])

        page = await context.new_page()
        await page.goto("http://localhost:8000/modules/todo/index.php")

        # Click on Deadline button
        await page.click("#deadlineBtn")
        await page.wait_for_selector("#deadlineDropdown.show")

        # Select "Today"
        await page.click("text=Today")

        # Add a task
        await page.fill("#quickAddInput", "Verification Task Final")
        await page.click("button:has-text('Add')")

        # Wait for reload
        await page.wait_for_load_state("networkidle")
        await page.screenshot(path="todo_final_verification.png")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())
