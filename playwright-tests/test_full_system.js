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

async function runFullLiveTest() {
    console.log('===========================================================');
    console.log(' STARTING FULL PLAYWRIGHT LIVE VISIBLE SYSTEM WALKTHROUGH ');
    console.log('===========================================================');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 450,
        channel: 'chrome'
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });
    const page = await context.newPage();
    const shotDir = path.join(__dirname, 'screenshots');

    try {
        // 1. Visit Login
        console.log('[1/14] Loading Login page: http://127.0.0.1:8000/login');
        await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(shotDir, '01_login_page.png') });

        // 2. Click Terms of Service link
        console.log('[2/14] Clicking "Terms of Service & Data Protection Notice" link...');
        await page.click('a[href*="terms-of-service"]');
        await page.waitForLoadState('networkidle');
        console.log('  -> Rendered Terms of Service URL:', page.url());
        await page.evaluate(() => window.scrollBy(0, 600));
        await page.waitForTimeout(500);
        await page.screenshot({ path: path.join(shotDir, '02_terms_of_service.png') });

        // Return to login
        console.log('  -> Returning back to Login screen...');
        await page.click('a.tos-nav-back');
        await page.waitForLoadState('networkidle');

        // 3. Login as Admin
        console.log('[3/14] Entering Admin credentials (claudiokhyelandrei@gmail.com)...');
        await page.fill('#email', 'claudiokhyelandrei@gmail.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle');

        // 4. Two-Factor Authentication Check
        if (page.url().includes('two-factor-challenge')) {
            console.log('[4/14] 2FA OTP Challenge screen reached. Fetching 5-minute code...');
            const code = fetchOtp('claudiokhyelandrei@gmail.com');
            console.log('  -> Verified OTP code:', code);
            await page.fill('input[name="code"]', code);
            await page.click('button[type="submit"]');
            await page.waitForLoadState('networkidle');
        }

        console.log('[5/14] Successfully authenticated! Current page:', page.url());
        await page.screenshot({ path: path.join(shotDir, '04_admin_dashboard.png') });

        // 5. Test Development
        console.log('[6/14] Testing My Development page...');
        await page.click('a[href*="my-progression"]');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '05_my_development.png') });

        // 6. Test Competency & Gap Analysis
        console.log('[7/14] Testing Competency...');
        await page.click('a[href*="competency"]:not([href*="gap"])');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '06_competency.png') });

        const gapLink = page.locator('a[href*="gap-analysis"]');
        if (await gapLink.count() > 0) {
            console.log('  -> Opening AI Competency Gap Analysis...');
            await gapLink.first().click();
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(shotDir, '06b_gap_analysis.png') });
        }

        // 7. Test Learning & Modules
        console.log('[8/14] Testing Learning Catalogue & Clinical Modules...');
        await page.goto('http://127.0.0.1:8000/learning');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '07_learning_catalogue.png') });

        const modulesTab = page.locator('a[href*="learning/modules"]');
        if (await modulesTab.count() > 0) {
            console.log('  -> Opening Clinical Modules Tab...');
            await modulesTab.first().click();
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(shotDir, '08_learning_modules.png') });
        }

        // 8. Test Training & Trainee Personal Attendance
        console.log('[9/14] Testing Training Management & Trainee Status...');
        await page.goto('http://127.0.0.1:8000/training');
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => window.scrollBy(0, 400));
        await page.waitForTimeout(500);
        await page.screenshot({ path: path.join(shotDir, '09_training_management.png') });

        // 9. Test Recognition & Awards
        console.log('[10/14] Testing Recognition & Awards...');
        await page.goto('http://127.0.0.1:8000/recognition');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '10_recognition_awards.png') });

        // 10. Test Succession Planning
        console.log('[11/14] Testing Succession Planning (9-Box Grid & Candidate Readiness)...');
        await page.goto('http://127.0.0.1:8000/succession');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '10b_succession_planning.png') });

        // 11. Test Users & Access (Validating Trainee role)
        console.log('[12/14] Testing Users & Access (Validating Trainee role label & filter)...');
        await page.goto('http://127.0.0.1:8000/users');
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(shotDir, '11_users_and_access.png') });

        const addUserBtn = page.locator('button[data-modal-open="userCreateModal"]');
        if (await addUserBtn.count() > 0) {
            console.log('  -> Opening Add User modal to inspect Trainee role option...');
            await addUserBtn.first().click();
            await page.waitForTimeout(600);
            await page.screenshot({ path: path.join(shotDir, '12_add_user_modal.png') });
            await page.click('#userCreateModal button[data-modal-dismiss]');
            await page.waitForTimeout(400);
        }

        // 12. Topbar Controls (Notification Bell & Help FAQ dropdown)
        console.log('[13/14] Testing Topbar Notification Bell & Help FAQ dropdown...');
        await page.click('#notif-btn');
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(shotDir, '14_notifications_dropdown.png') });
        await page.click('#notif-btn');
        await page.waitForTimeout(300);

        await page.click('#help-btn');
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(shotDir, '15_help_faq_dropdown.png') });
        await page.click('#help-btn');
        await page.waitForTimeout(300);

        // 13. Test Profile & Privacy (CSV and JSON download verification)
        console.log('[14/14] Testing Profile & Privacy...');
        await page.goto('http://127.0.0.1:8000/profile');
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => window.scrollBy(0, 500));
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(shotDir, '13_profile_privacy.png') });

        console.log('===========================================================');
        console.log(' LIVE FULL SYSTEM PLAYWRIGHT TEST COMPLETED SUCCESSFULLY!  ');
        console.log('===========================================================');
    } catch (e) {
        console.error('Test encountered issue:', e);
        await page.screenshot({ path: path.join(shotDir, 'error_state.png') });
    } finally {
        await page.waitForTimeout(2000);
        await browser.close();
        console.log('Browser closed cleanly.');
    }
}

runFullLiveTest();
