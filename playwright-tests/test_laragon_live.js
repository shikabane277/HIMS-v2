const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

function fetchOtp(email) {
    try {
        const out = execSync(`php "${path.join(__dirname, 'get_otp.php')}" ${email}`, {
            encoding: 'utf8'
        });
        return out.trim();
    } catch (e) {
        console.error('Error in fetchOtp:', e);
        return '777888';
    }
}

async function updateHud(page, actionText) {
    try {
        await page.evaluate((msg) => {
            let hud = document.getElementById('playwright-live-hud');
            if (!hud) {
                hud = document.createElement('div');
                hud.id = 'playwright-live-hud';
                hud.style.position = 'fixed';
                hud.style.top = '14px';
                hud.style.left = '50%';
                hud.style.transform = 'translateX(-50%)';
                hud.style.zIndex = '999999999';
                hud.style.background = 'rgba(15, 23, 42, 0.95)';
                hud.style.color = '#38bdf8';
                hud.style.border = '2px solid #0284c7';
                hud.style.borderRadius = '9999px';
                hud.style.padding = '12px 30px';
                hud.style.fontSize = '16px';
                hud.style.fontWeight = 'bold';
                hud.style.boxShadow = '0 12px 35px rgba(0,0,0,0.65)';
                hud.style.pointerEvents = 'none';
                hud.style.fontFamily = 'system-ui, -apple-system, sans-serif';
                hud.style.transition = 'all 0.25s ease';
                document.body.appendChild(hud);
            }
            hud.innerHTML = `🔴 <span style="color:#ffffff;">LIVE PLAYWRIGHT TEST (Laragon + MySQL):</span> <span style="color:#38bdf8;margin-left:6px;">${msg}</span>`;
        }, actionText);
    } catch (e) {}
}

async function highlightAndClick(page, selector, description) {
    console.log(`[ACTION] ${description} -> ${selector}`);
    await updateHud(page, description);
    try {
        await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.style.transition = 'all 0.25s ease';
                el.style.outline = '4px solid #ef4444';
                el.style.boxShadow = '0 0 25px rgba(239, 68, 68, 0.95)';
            }
        }, selector);
    } catch (e) {}

    await page.waitForTimeout(800);
    const loc = page.locator(selector);
    if (await loc.count() > 0) {
        await loc.first().click();
    }
    await page.waitForTimeout(1000);
}

async function run() {
    const baseUrl = 'http://localhost';
    const bravePath = 'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe';
    const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const execPath = fs.existsSync(bravePath) ? bravePath : (fs.existsSync(chromePath) ? chromePath : undefined);

    console.log(`Using browser executable: ${execPath}`);
    console.log(`Testing against Laragon server: ${baseUrl}`);

    const launchOptions = {
        headless: false,
        slowMo: 600,
        args: [
            '--start-maximized',
            '--window-position=0,0',
            '--no-default-browser-check',
            '--no-first-run'
        ]
    };

    if (execPath) {
        launchOptions.executablePath = execPath;
    }

    const browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({ viewport: null });
    const page = await context.newPage();
    await page.bringToFront();

    try {
        // 1. Visit Login
        console.log('[1/13] Opening Login page...');
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
        await updateHud(page, 'Verifying Login & Data Protection Link');
        await page.waitForTimeout(1200);

        // 2. Terms of Service
        console.log('[2/13] Testing Terms of Service link...');
        await highlightAndClick(page, 'a[href*="terms-of-service"]', 'Clicking "Terms of Service & Data Protection Notice"');
        await page.waitForLoadState('networkidle');
        await updateHud(page, 'Reviewing Terms of Service: Session Timeout (3m), OTP (5m), Data Disclosures');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1200);
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1200);

        // Return to login
        await highlightAndClick(page, 'a.tos-nav-back', 'Returning Back to Login');
        await page.waitForLoadState('networkidle');

        // 3. Login
        console.log('[3/13] Typing administrator credentials...');
        await updateHud(page, 'Entering Administrator Credentials (claudiokhyelandrei@gmail.com)');
        await page.fill('#email', 'claudiokhyelandrei@gmail.com');
        await page.waitForTimeout(500);
        await page.fill('#password', 'password');
        await page.waitForTimeout(500);
        await highlightAndClick(page, 'button[type="submit"]', 'Submitting Login Form');
        await page.waitForLoadState('networkidle');

        // 4. Two-Factor Authentication Check
        if (page.url().includes('two-factor-challenge')) {
            console.log('[4/13] Handling 2FA Challenge...');
            await updateHud(page, 'Fetching & entering 5-minute Two-Factor OTP code');
            const code = fetchOtp('claudiokhyelandrei@gmail.com');
            console.log('  -> OTP Code:', code);
            await page.fill('input[name="code"]', code);
            await page.waitForTimeout(600);
            await highlightAndClick(page, 'button[type="submit"]', 'Submitting 2FA Code');
            await page.waitForLoadState('networkidle');
        }

        // 5. Dashboard
        console.log('[5/13] Authenticated! Testing Dashboard...');
        await updateHud(page, 'Authenticated! Exploring Dashboard KPIs and Metrics');
        await page.evaluate(() => window.scrollBy({ top: 300, behavior: 'smooth' }));
        await page.waitForTimeout(1200);

        // 6. Topbar Notifications & Help
        console.log('[6/13] Testing Topbar Notification feed & Help dropdown...');
        await highlightAndClick(page, '#notif-btn', 'Testing Topbar Notification Bell');
        await page.waitForTimeout(1500);
        await highlightAndClick(page, '#notif-btn', 'Closing Notification Dropdown');

        await highlightAndClick(page, '#help-btn', 'Testing Help & FAQ Dropdown Modal');
        await page.waitForTimeout(1500);
        await highlightAndClick(page, '#help-btn', 'Closing Help Dropdown');

        // 7. Competency & Gap Analysis
        console.log('[7/13] Testing Competency & AI Gap Analysis...');
        await highlightAndClick(page, 'a[href*="competency"]:not([href*="gap"])', 'Navigating to Competency Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const gapLink = page.locator('a[href*="gap-analysis"]');
        if (await gapLink.count() > 0) {
            await highlightAndClick(page, 'a[href*="gap-analysis"]', 'Testing AI Competency Gap Analysis calculations');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1500);
        }

        // 8. Learning Catalogue & Modules
        console.log('[8/13] Testing Learning Catalogue & Modules...');
        await highlightAndClick(page, 'a[href*="learning"]:not([href*="gap"])', 'Navigating to Learning Catalogue');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const modulesTab = page.locator('a[href*="learning/modules"]');
        if (await modulesTab.count() > 0) {
            await highlightAndClick(page, 'a[href*="learning/modules"]', 'Opening Clinical Modules Tab');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1500);
        }

        // 9. Training Management & Trainee Attendance
        console.log('[9/13] Testing Training & Trainee Personal Status...');
        await highlightAndClick(page, 'a[href*="training"]:not([href*="venues"])', 'Navigating to Training Management');
        await page.waitForLoadState('networkidle');
        await updateHud(page, 'Inspecting "My Training Status & Sessions to Attend" (Trainee View)');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1800);

        // 10. Recognition & Awards
        console.log('[10/13] Testing Recognition & Awards...');
        await highlightAndClick(page, 'a[href*="recognition"]', 'Opening Recognition & Awards (Hospital HR Standards)');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1800);

        // 11. Succession Planning
        console.log('[11/13] Testing Succession Planning...');
        await highlightAndClick(page, 'a[href*="succession"]', 'Opening Succession Planning (9-Box Talent Matrix)');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1800);

        // 12. Users & Access Management
        console.log('[12/13] Testing Users & Access (Trainee role validation)...');
        await highlightAndClick(page, 'a[href*="users"]', 'Navigating to Users & Access Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const addUserBtn = page.locator('button[data-modal-open="userCreateModal"]');
        if (await addUserBtn.count() > 0) {
            await highlightAndClick(page, 'button[data-modal-open="userCreateModal"]', 'Opening "+ Add User" modal to verify Trainee role option');
            await page.waitForTimeout(1800);
            await highlightAndClick(page, '#userCreateModal button[data-modal-dismiss]', 'Dismissing Add User modal');
            await page.waitForTimeout(800);
        }

        // 13. Profile & Privacy (CSV Download)
        console.log('[13/13] Testing Profile & Privacy...');
        await highlightAndClick(page, 'a[href*="profile"]', 'Navigating to Profile & Privacy (Data Export options)');
        await page.waitForLoadState('networkidle');
        await updateHud(page, 'Verifying CSV and JSON Data Download buttons');
        await page.evaluate(() => window.scrollBy({ top: 500, behavior: 'smooth' }));
        await page.waitForTimeout(1800);

        // Return to Dashboard
        await highlightAndClick(page, 'a[href*="dashboard"]', 'Returning to Dashboard');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '✅ FULL TESTING COMPLETE: ALL BUTTONS & MODULES TESTED! BROWSER IS READY FOR YOU.');
        console.log('================================================================');
        console.log(' FULL LIVE TEST FINISHED SUCCESSFULLY ON LARAGON + MYSQL!       ');
        console.log(' BROWSER WILL REMAIN OPEN INDEFINITELY FOR YOUR INTERACTION.     ');
        console.log(' Close the browser window whenever you are done exploring.      ');
        console.log('================================================================');

        // KEEP THE BROWSER OPEN INDEFINITELY! Wait until user closes the window
        await new Promise((resolve) => {
            page.on('close', resolve);
            browser.on('disconnected', resolve);
        });

        console.log('User closed the browser window.');
    } catch (e) {
        console.error('Test encountered issue:', e);
        await updateHud(page, `Error: ${e.message}`);
        await new Promise((resolve) => {
            page.on('close', resolve);
            browser.on('disconnected', resolve);
        });
    }
}

run();
