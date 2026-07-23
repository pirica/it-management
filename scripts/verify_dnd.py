import asyncio
from playwright.async_api import async_playwright
import sys

async def main():
    if len(sys.argv) < 2:
        print("Usage: python verify_dnd.py <session_id>")
        return

    session_id = sys.argv[1]

    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context()

        # Set session cookie
        await context.add_cookies([{
            'name': 'PHPSESSID',
            'value': session_id,
            'domain': 'localhost',
            'path': '/'
        }])

        page = await context.new_page()

        print("Navigating to Create Private Contact...")
        # Use localhost:8080 if the server is running there
        url = 'http://localhost:8080/modules/private_contacts/create.php'
        await page.goto(url)

        # Check if target exists
        target = await page.query_selector('.itm-photo-upload-target')
        if target:
            print("Found .itm-photo-upload-target")
        else:
            print("ERROR: .itm-photo-upload-target NOT FOUND")
            # Log body for debug
            body = await page.content()
            print(f"Body snippet: {body[:500]}")

        # Check if helper script is loaded
        scripts = await page.eval_on_selector_all('script', 'nodes => nodes.map(n => n.src)')
        if any('itm-upload-helper.js' in s for s in scripts):
            print("Found itm-upload-helper.js script")
        else:
            print("ERROR: itm-upload-helper.js NOT FOUND")

        if target:
            # Simulate dragover to check CSS/JS
            print("Simulating dragover...")
            await page.evaluate('''() => {
                const target = document.querySelector(".itm-photo-upload-target");
                const event = new DragEvent("dragover", {
                    bubbles: true,
                    cancelable: true
                });
                target.dispatchEvent(event);
            }''')

            # Wait a bit for JS to process
            await asyncio.sleep(0.5)

            classes = await page.eval_on_selector('.itm-photo-upload-target', 'el => el.className')
            print(f"Target classes after dragover: {classes}")

            if 'is-dragover' in classes:
                print("SUCCESS: is-dragover class added on dragover")
            else:
                print("ERROR: is-dragover class NOT added")

        await page.screenshot(path='docs/readme/dnd_verification.png')
        print("Screenshot saved to docs/readme/dnd_verification.png")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())
