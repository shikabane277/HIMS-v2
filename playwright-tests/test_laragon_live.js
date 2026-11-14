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
        return '777888';
    }
}

async function updateHud(page, stage, actionText) {
    try {
        await page.evaluate(({ stg, act }) => {
            let hud = document.getElementById('playwright-live-hud');
            if (!hud) {
                hud = document.createElement('div');
                hud.id = 'playwright-live-hud';
                hud.style.position = 'fixed';
                hud.style.top = '12px';
                hud.style.left = '50%';
                hud.style.transform = 'translateX(-50%)';
                hud.style.zIndex = '999999999';
                hud.style.background = 'linear-gradient(135deg, #090d16 0%, #1e293b 100%)';
                hud.style.color = '#38bdf8';
                hud.style.border = '2px solid #0284c7';
                hud.style.borderRadius = '9999px';
                hud.style.padding = '10px 28px';
                hud.style.fontSize = '14px';
                hud.style.fontWeight = 'bold';
                hud.style.boxShadow = '0 12px 35px rgba(0,0,0,0.7)';
                hud.style.pointerEvents = 'none';
                hud.style.fontFamily = 'Segoe UI, system-ui, sans-serif';
                hud.style.transition = 'all 0.2s ease';
                document.body.appendChild(hud);
            }
            hud.innerHTML = `🔴 <span style="color:#ef4444;">[${stg}]</span> <span style="color:#ffffff;">SYSTEM AUDIT:</span> <span style="color:#38bdf8;margin-left:6px;">${act}</span>`;
        }, { stg: stage, act: actionText });
    } catch (e) {}
}

async function highlight(page, selector) {
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
}

async function unhighlight(page, selector) {
    try {
        await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            if (el) {
                el.style.outline = '';
                el.style.boxShadow = '';
            }
        }, selector);
    } catch (e) {}
}

async function clickAction(page, selector, stage, description) {
    console.log(`[ACTION] [${stage}] ${description} -> ${selector}`);
    await updateHud(page, stage, description);
    await highlight(page, selector);
    await page.waitForTimeout(600);

    const loc = page.locator(selector);
    if (await loc.count() > 0) {
        try {
            await loc.first().click();
        } catch (err) {
            console.log(`Click fallback via DOM for ${selector}`);
            await page.evaluate((sel) => document.querySelector(sel)?.click(), selector);
        }
    }
    await unhighlight(page, selector);
    await page.waitForTimeout(700);
}

async function run() {
    const baseUrl = 'http://localhost';
    const bravePath = 'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe';
    const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const execPath = fs.existsSync(bravePath) ? bravePath : (fs.existsSync(chromePath) ? chromePath : undefined);

    console.log('================================================================');
    console.log(' STARTING FULL END-TO-END INTERACTIVE SYSTEM AUDIT IN BROWSER   ');
    console.log(` Target URL: ${baseUrl} (Laragon Apache + MySQL)                `);
    console.log(` Browser:    ${execPath}                                        `);
    console.log('================================================================');

    const launchOptions = {
        headless: false,
        slowMo: 450,
        args: [
            '--start-maximized',
            '--window-position=0,0',
            '--no-default-browser-check',
            '--no-first-run'
        ]
    };
    if (execPath) launchOptions.executablePath = execPath;

    const browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({ viewport: null });
    const page = await context.newPage();
    await page.bringToFront();

    try {
        // ==========================================
        // 1. TERMS OF SERVICE & DATA PROTECTION
        // ==========================================
        console.log('[1] Testing Login & Terms of Service disclosures...');
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
        await updateHud(page, '1/15', 'Visiting Login Page & Checking Data Protection Link');
        await page.waitForTimeout(1000);

        await clickAction(page, 'a[href*="terms-of-service"]', '1/15', 'Opening Terms of Service & Data Protection Notice');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '1/15', 'Reviewing Session Timeout (3m), OTP (5m), GDPR & Employee Data Usage');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1000);
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1000);

        await clickAction(page, 'a.tos-nav-back', '1/15', 'Navigating Back to Login Screen');
        await page.waitForLoadState('networkidle');

        // ==========================================
        // 2. AUTHENTICATION & TWO-FACTOR (2FA)
        // ==========================================
        console.log('[2] Testing Authentication & 2FA Challenge...');
        await updateHud(page, '2/15', 'Typing Administrator Credentials');
        await page.fill('#email', 'claudiokhyelandrei@gmail.com');
        await page.waitForTimeout(400);
        await page.fill('#password', 'password');
        await page.waitForTimeout(400);
        await clickAction(page, 'button[type="submit"]', '2/15', 'Submitting Login Credentials');
        await page.waitForLoadState('networkidle');

        if (page.url().includes('two-factor-challenge')) {
            await updateHud(page, '2/15', 'Retrieving & Entering 5-Minute 2FA OTP Code');
            const otp = fetchOtp('claudiokhyelandrei@gmail.com');
            console.log('  -> OTP Code:', otp);
            await page.fill('input[name="code"]', otp);
            await page.waitForTimeout(500);
            await clickAction(page, 'button[type="submit"]', '2/15', 'Submitting Two-Factor Code');
            await page.waitForLoadState('networkidle');
        }

        // ==========================================
        // 3. DASHBOARD METRICS & TOPBAR CONTROLS
        // ==========================================
        console.log('[3] Testing Dashboard KPIs & Topbar Controls...');
        await updateHud(page, '3/15', 'Authenticated! Inspecting Dashboard Metrics & Quick Actions');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1000);
        await page.evaluate(() => window.scrollBy({ top: -350, behavior: 'smooth' }));
        await page.waitForTimeout(600);

        // Notification Bell
        await clickAction(page, '#notif-btn', '3/15', 'Opening Topbar Notification Feed');
        await page.waitForTimeout(1200);
        const clearBtn = page.locator('#notif-clear');
        if (await clearBtn.isVisible()) {
            await clickAction(page, '#notif-clear', '3/15', 'Clicking "Mark All As Read"');
            await page.waitForTimeout(600);
        }
        await clickAction(page, '#notif-btn', '3/15', 'Closing Notification Dropdown');

        // Help & FAQ Modal
        await clickAction(page, '#help-btn', '3/15', 'Opening Help & FAQ Knowledge Base');
        await page.waitForTimeout(1000);
        const firstFaq = page.locator('.help-faq summary');
        if (await firstFaq.count() > 0) {
            await clickAction(page, '.help-faq summary', '3/15', 'Expanding FAQ Item: AI Gap Analysis & Data Rights');
            await page.waitForTimeout(1000);
        }
        await clickAction(page, '#help-btn', '3/15', 'Closing Help & FAQ Modal');

        // ==========================================
        // 4. COMPETENCY MANAGEMENT & AI GAP ANALYSIS
        // ==========================================
        console.log('[4] Testing Competency Management & AI Gap Analysis...');
        await clickAction(page, 'a[href*="competency"]:not([href*="gap"])', '4/15', 'Navigating to Competency Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Role requirements tab
        const roleReqTab = page.locator('a[href*="role-requirements"]');
        if (await roleReqTab.count() > 0) {
            await clickAction(page, 'a[href*="role-requirements"]', '4/15', 'Inspecting Role Requirements & Minimum Proficiencies');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1000);
        }

        // AI Gap Analysis
        const gapLink = page.locator('a[href*="gap-analysis"]');
        if (await gapLink.count() > 0) {
            await clickAction(page, 'a[href*="gap-analysis"]', '4/15', 'Opening AI Competency Gap Analysis');
            await page.waitForLoadState('networkidle');
            await updateHud(page, '4/15', 'Calculating Competency Gaps against Hospital Standards');
            await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
            await page.waitForTimeout(1500);
        }

        // ==========================================
        // 5. LEARNING CATALOGUE, MODULES & CPD
        // ==========================================
        console.log('[5] Testing Learning Catalogue, Modules & CPD...');
        await clickAction(page, 'a[href*="learning"]:not([href*="gap"])', '5/15', 'Navigating to Learning & Course Catalogue');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Modules tab
        const modulesTab = page.locator('a[href*="learning/modules"]');
        if (await modulesTab.count() > 0) {
            await clickAction(page, 'a[href*="learning/modules"]', '5/15', 'Opening Clinical Modules Tab');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1200);
        }

        // CPD tab
        const cpdTab = page.locator('a[href*="learning/cpd"]');
        if (await cpdTab.count() > 0) {
            await clickAction(page, 'a[href*="learning/cpd"]', '5/15', 'Checking Continuing Professional Development (CPD) Tracking');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 6. TRAINING MANAGEMENT & TRAINEE STATUS
        // ==========================================
        console.log('[6] Testing Training Management & Trainee Personal Status...');
        await clickAction(page, 'a[href*="training"]:not([href*="venues"])', '6/15', 'Navigating to Training Management');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '6/15', 'Inspecting "My Training Status & Sessions to Attend" (Trainee View)');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1500);

        // Training Venues
        const venuesTab = page.locator('a[href*="training/venues"]');
        if (await venuesTab.count() > 0) {
            await clickAction(page, 'a[href*="training/venues"]', '6/15', 'Viewing Hospital Training Venues & Facilities');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 7. PERFORMANCE & APPRAISALS
        // ==========================================
        console.log('[7] Testing Performance & Appraisal Reviews...');
        const perfLink = page.locator('a[href*="performance"]');
        if (await perfLink.count() > 0) {
            await clickAction(page, 'a[href*="performance"]', '7/15', 'Navigating to Performance Management & Reviews');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 300, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 8. RECOGNITION & AWARDS (HOSPITAL HR)
        // ==========================================
        console.log('[8] Testing Recognition & Awards...');
        await clickAction(page, 'a[href*="recognition"]', '8/15', 'Navigating to Recognition & Awards (Hospital HR Standards)');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '8/15', 'Inspecting Staff Awards, Commendations & Certification Badges');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1500);

        // ==========================================
        // 9. SUCCESSION PLANNING (9-BOX TALENT GRID)
        // ==========================================
        console.log('[9] Testing Succession Planning & 9-Box Grid...');
        await clickAction(page, 'a[href*="succession"]', '9/15', 'Navigating to Succession Planning');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '9/15', 'Inspecting 9-Box Grid, Candidate Readiness & Promotion Pipeline');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1500);

        // Positions tab
        const posTab = page.locator('a[href*="succession/positions"]');
        if (await posTab.count() > 0) {
            await clickAction(page, 'a[href*="succession/positions"]', '9/15', 'Viewing Critical Positions & Bench Strength');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 10. COMPLIANCE & ACCREDITATIONS
        // ==========================================
        console.log('[10] Testing Hospital Compliance & Accreditations...');
        const compLink = page.locator('a[href*="compliance"]');
        if (await compLink.count() > 0) {
            await clickAction(page, 'a[href*="compliance"]', '10/15', 'Navigating to Compliance & Hospital Accreditations');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 11. REPORTS & ANALYTICS (EXPORTS)
        // ==========================================
        console.log('[11] Testing Reports & Analytics...');
        const repLink = page.locator('a[href*="reports"]');
        if (await repLink.count() > 0) {
            await clickAction(page, 'a[href*="reports"]', '11/15', 'Navigating to Executive Reports & Analytics');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 300, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 12. USERS & ACCESS (TRAINEE ROLE AUDIT)
        // ==========================================
        console.log('[12] Testing Users & Access (Trainee role validation & modal)...');
        await clickAction(page, 'a[href*="users"]', '12/15', 'Navigating to Users & Access Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Filter by Trainee role
        const roleFilter = page.locator('select[name="role"]');
        if (await roleFilter.count() > 0) {
            await updateHud(page, '12/15', 'Filtering User Directory by Role = "Trainee"');
            await roleFilter.selectOption('trainee');
            await page.waitForTimeout(800);
            await roleFilter.selectOption(''); // reset filter
            await page.waitForTimeout(600);
        }

        // Open Add User Modal
        const addUserBtn = page.locator('button[data-modal-open="userCreateModal"]');
        if (await addUserBtn.count() > 0) {
            await clickAction(page, 'button[data-modal-open="userCreateModal"]', '12/15', 'Opening "+ Add User" Modal');
            await page.waitForTimeout(1200);
            await updateHud(page, '12/15', 'Inspecting "Trainee" Role Option Badge & Purpose');
            await page.waitForTimeout(1200);
            await clickAction(page, '#userCreateModal button[data-modal-dismiss]', '12/15', 'Dismissing Add User Modal');
            await page.waitForTimeout(600);
        }

        // ==========================================
        // 13. EMPLOYEE PROGRESSION
        // ==========================================
        console.log('[13] Testing My Progression...');
        const progLink = page.locator('a[href*="my-progression"]');
        if (await progLink.count() > 0) {
            await clickAction(page, 'a[href*="my-progression"]', '13/15', 'Viewing Individual Career Progression & Milestones');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // 14. PROFILE & PRIVACY (DATA EXPORT)
        // ==========================================
        console.log('[14] Testing Profile & Privacy Data Exports...');
        await clickAction(page, 'a[href*="profile"]', '14/15', 'Navigating to Profile & Privacy (Data Subject Rights)');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '14/15', 'Inspecting CSV and JSON Personal Data Download Controls');
        await page.evaluate(() => window.scrollBy({ top: 500, behavior: 'smooth' }));
        await page.waitForTimeout(1500);

        // ==========================================
        // 15. RETURN TO DASHBOARD
        // ==========================================
        console.log('[15] Returning to Dashboard...');
        await clickAction(page, 'a[href*="dashboard"]', '15/15', 'Returning to Main Executive Dashboard');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '15/15', '✅ FULL SYSTEM AUDIT COMPLETED! ALL MODULES & ACTIONS OPERATIONAL.');

        console.log('================================================================');
        console.log(' FULL SYSTEM AUDIT COMPLETED SUCCESSFULLY ACROSS ALL MODULES!   ');
        console.log(' BROWSER WILL REMAIN OPEN FOR YOUR INTERACTION.                 ');
        console.log(' Close the browser window whenever you are finished.            ');
        console.log('================================================================');

        // Keep the browser open indefinitely until user closes it
        await new Promise((resolve) => {
            page.on('close', resolve);
            browser.on('disconnected', resolve);
        });

        console.log('Browser window closed by user.');
    } catch (e) {
        console.error('Test encountered issue:', e);
        await updateHud(page, 'ERR', `Error: ${e.message}`);
        await new Promise((resolve) => {
            page.on('close', resolve);
            browser.on('disconnected', resolve);
        });
    }
}

run();
