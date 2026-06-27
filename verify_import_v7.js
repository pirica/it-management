const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // First, hit the bypass to set the session
  await page.goto('http://localhost:3000/bypass_v2.php');

  // Now go to the import module
  await page.goto('http://localhost:3000/modules/import/index.php');

  // Wait for the grid to appear to be sure we are there
  await page.waitForSelector('.import-grid');

  await page.screenshot({ path: '/home/jules/verification/import_dashboard_v7.png', fullPage: true });
  await browser.close();
})();
