import asyncio
from playwright.async_api import async_playwright

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context()
        page = await context.new_page()

        # 1. Access bypass.php to set the session
        await page.goto("http://localhost:80/bypass.php")

        # 2. Go to the import module
        await page.goto("http://localhost:80/modules/import/index.php")

        # Take screenshot
        await page.screenshot(path="import_dashboard_xlsx.png", full_page=True)
        print("Screenshot saved to import_dashboard_xlsx.png")

        await browser.close()

asyncio.run(run())
