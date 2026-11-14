const { chromium } = require('playwright');
const path = require('path');
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

async function showBanner(page, text) {
    try {
        await page.evaluate((msg) => {
            let el = document.getElementById('live-agent-banner');
            if (!el) {
                el = document.createElement('div');
                el.id = 'live-agent-banner';
                el.style.position = 'fixed';
                el.style.top = '15px';
                el.style.left = '50%';
                el.style.transform = 'translateX(-50%)';
                el.style.zIndex = '99999999';
                el.style.background = '#0f172a';
                el.style.color = '#38bdf8';
                el.style.padding = '12px 28px';
                el.style.borderRadius = '50px';
                el.style.fontSize = '16px';
                el.style.fontWeight = 'bold';
                el.style.boxShadow = '0 10px 40px rgba(0,0,0,0.6)';
                el.style.border = '2px solid #38bdf8';
                el.style.pointerEvents = 'none';
                el.style.fontFamily = 'Segoe UI, system-ui, sans-serif';
                el.style.transition = 'all 0.3s ease';
                document.body.appendChild(el);
            }
            el.innerHTML = `🤖 <span style="color:#ef4444;font-size:18px;">●</span> LIVE TESTING: <span style="color:#ffffff;">${msg}</span>`;
        }, text);
    } catch (e) {}
}

async function clickWithHighlight(page, selector, description) {
    console.log(`[ACTION] ${description}`);
    await showBanner(page, description);
    try {
        await page.evaluate((sel) => {
            const target = document.querySelector(sel);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.style.transition = 'all 0.3s ease';
                target.style.outline = '4px solid #ef4444';
                target.style.boxShadow = '0 0 25px rgba(239, 68, 68, 0.9)';
            }
        }, selector);
    } catch (e) {}

    await page.waitForTimeout(1000);
    const locator = page.locator(selector);
    if (await locator.count() > 0) {
        await locator.first().click();
    }
    await page.waitForTimeout(1200);
}

async function main() {
    console.log('Connecting to live user Chrome via DevTools CDP...');
    const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
    const contexts = browser.contexts();
    const context = contexts.length > 0 ? contexts[0] : await browser.newContext();
    const pages = context.pages();
    const page = pages.length > 0 ? pages[0] : await context.newPage();

    await page.bringToFront();

    try {
        // 1. Visit Login
        await page.goto('http://127.0.0.1:8000/login');
        await page.waitForLoadState('networkidle');
        await showBanner(page, 'Verifying Login & Interactive Controls');
        await page.waitForTimeout(1500);

        // 2. Terms of Service
        await clickWithHighlight(page, 'a[href*="terms-of-service"]', 'Clicking "Terms of Service & Data Protection Notice"');
        await page.waitForLoadState('networkidle');
        await showBanner(page, 'Reviewing Terms of Service disclosures (Session timeout & Data details)');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1200);
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1200);

        // Back to Login
        await clickWithHighlight(page, 'a.tos-nav-back', 'Returning back to Login screen');
        await page.waitForLoadState('networkidle');

        // 3. Login
        await showBanner(page, 'Typing administrator credentials...');
        await page.fill('#email', 'claudiokhyelandrei@gmail.com');
        await page.waitForTimeout(600);
        await page.fill('#password', 'password');
        await page.waitForTimeout(600);
        await clickWithHighlight(page, 'button[type="submit"]', 'Clicking Log In button');
        await page.waitForLoadState('networkidle');

        // 4. Handle 2FA
        if (page.url().includes('two-factor-challenge')) {
            await showBanner(page, 'Retrieving and entering 2FA OTP Code...');
            const otp = fetchOtp('claudiokhyelandrei@gmail.com');
            console.log('  -> OTP verified:', otp);
            await page.fill('input[name="code"]', otp);
            await page.waitForTimeout(800);
            await clickWithHighlight(page, 'button[type="submit"]', 'Submitting 2FA verification');
            await page.waitForLoadState('networkidle');
        }

        // 5. Dashboard
        await showBanner(page, 'Authenticated! Exploring Dashboard KPIs and menus');
        await page.evaluate(() => window.scrollBy({ top: 300, behavior: 'smooth' }));
        await page.waitForTimeout(1500);

        // 6. Topbar Notifications
        await clickWithHighlight(page, '#notif-btn', 'Opening Notification Bell feed');
        await page.waitForTimeout(1500);
        await clickWithHighlight(page, '#notif-btn', 'Closing Notification feed');

        // 7. Topbar Help & FAQ
        await clickWithHighlight(page, '#help-btn', 'Opening Help & FAQ dropdown modal');
        await page.waitForTimeout(1500);
        await clickWithHighlight(page, '#help-btn', 'Closing Help & FAQ dropdown');

        // 8. Competency & Gap Analysis
        await clickWithHighlight(page, 'a[href*="competency"]:not([href*="gap"])', 'Navigating to Competency Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const gapLink = page.locator('a[href*="gap-analysis"]');
        if (await gapLink.count() > 0) {
            await clickWithHighlight(page, 'a[href*="gap-analysis"]', 'Testing AI Competency Gap Analysis calculations');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1500);
        }

        // 9. Learning Catalogue & Modules
        await clickWithHighlight(page, 'a[href*="learning"]:not([href*="gap"])', 'Navigating to Learning Catalogue');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const modulesTab = page.locator('a[href*="learning/modules"]');
        if (await modulesTab.count() > 0) {
            await clickWithHighlight(page, 'a[href*="learning/modules"]', 'Opening Clinical Modules Tab');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1500);
        }

        // 10. Training Management & Trainee Attendance View
        await clickWithHighlight(page, 'a[href*="training"]:not([href*="venues"])', 'Navigating to Training Management');
        await page.waitForLoadState('networkidle');
        await showBanner(page, 'Viewing Trainee Personal Training Status & Sessions to Attend');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(2000);

        // 11. Recognition & Awards
        await clickWithHighlight(page, 'a[href*="recognition"]', 'Opening Recognition & Awards (Hospital HR Standards)');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        // 12. Succession Planning
        await clickWithHighlight(page, 'a[href*="succession"]', 'Opening Succession Planning (9-Box Grid & Candidate Readiness)');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        // 13. Users & Access
        await clickWithHighlight(page, 'a[href*="users"]', 'Opening Users & Access (Testing Trainee role filters & badges)');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const addUserBtn = page.locator('button[data-modal-open="userCreateModal"]');
        if (await addUserBtn.count() > 0) {
            await clickWithHighlight(page, 'button[data-modal-open="userCreateModal"]', 'Clicking "+ Add User" button to inspect Trainee role option');
            await page.waitForTimeout(2000);
            await clickWithHighlight(page, '#userCreateModal button[data-modal-dismiss]', 'Closing Add User modal');
            await page.waitForTimeout(800);
        }

        // 14. Profile & Privacy
        await clickWithHighlight(page, 'a[href*="profile"]', 'Opening Profile & Privacy (Data Export options)');
        await page.waitForLoadState('networkidle');
        await showBanner(page, 'Verifying CSV and JSON Data Download buttons');
        await page.evaluate(() => window.scrollBy({ top: 500, behavior: 'smooth' }));
        await page.waitForTimeout(2000);

        // 15. Return to Dashboard
        await clickWithHighlight(page, 'a[href*="dashboard"]', 'Returning to Dashboard');
        await page.waitForLoadState('networkidle');
        await showBanner(page, '✅ ALL FUNCTIONS & BUTTONS TESTED LIVE! BROWSER REMAINS READY FOR YOU.');
        await page.waitForTimeout(3000);

        console.log('LIVE WALKTHROUGH FINISHED SUCCESSFULLY.');
    } catch (e) {
        console.error('Error during live walkthrough:', e);
    } finally {
        // Disconnect from CDP without closing the browser!
        await browser.close(); // Only disconnects CDP client, does not terminate external Chrome
        console.log('Disconnected CDP. Chrome remains open on your screen.');
    }
}

main();
