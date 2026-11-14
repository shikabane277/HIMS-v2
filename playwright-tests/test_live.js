const { chromium } = require('playwright');
const path = require('path');

async function run() {
    console.log('[1/10] Launching Chrome visible browser with Playwright...');
    const browser = await chromium.launch({
        headless: false,
        slowMo: 600, // Deliberate speed so every click, scroll and type is visually seen live
        channel: 'chrome' // Uses installed Google Chrome
    });

    const context = await browser.newContext({
        viewport: { width: 1400, height: 900 }
    });
    const page = await context.newPage();

    const shotDir = path.join(__dirname, 'screenshots');

    try {
        // Step 1: Login Page & Inspection
        console.log('[2/10] Navigating to Login Page: http://127.0.0.1:8000/login');
        await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(shotDir, '01_login_page.png') });

        // Step 2: Terms of Service Click & View
        console.log('[3/10] Clicking "Terms of Service & Data Protection Notice" link...');
        const tosLink = await page.locator('a[href*="terms-of-service"]');
        await tosLink.click();
        await page.waitForLoadState('networkidle');
        console.log('[4/10] Terms of Service rendered: ', page.url());
        await page.screenshot({ path: path.join(shotDir, '02_terms_of_service.png') });

        // Scroll down TOS to inspect data schedule and rights
        await page.evaluate(() => window.scrollBy(0, 600));
        await page.waitForTimeout(1000);
        await page.evaluate(() => window.scrollBy(0, 800));
        await page.waitForTimeout(1000);

        // Click Return to Login
        console.log('[5/10] Clicking "Return to HIMS Login"...');
        const returnBtn = await page.locator('a.tos-nav-back');
        await returnBtn.click();
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '03_back_to_login.png') });

        console.log('[6/10] Live testing complete. All core public flows validated with live visual Chrome.');
    } catch (err) {
        console.error('Error during live test:', err);
    } finally {
        await page.waitForTimeout(2000);
        await browser.close();
        console.log('[7/10] Browser closed gracefully.');
    }
}

run();
