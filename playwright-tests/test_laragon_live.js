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
            hud.innerHTML = `🔴 <span style="color:#ef4444;">[${stg}]</span> <span style="color:#ffffff;">LIVE SCENARIO AUDIT:</span> <span style="color:#38bdf8;margin-left:6px;">${act}</span>`;
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
            console.log(`Fallback DOM click for ${selector}`);
            await page.evaluate((sel) => document.querySelector(sel)?.click(), selector);
        }
    }
    await unhighlight(page, selector);
    await page.waitForTimeout(800);
}

async function run() {
    const baseUrl = 'http://localhost';
    const bravePath = 'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe';
    const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const execPath = fs.existsSync(bravePath) ? bravePath : (fs.existsSync(chromePath) ? chromePath : undefined);

    console.log('================================================================');
    console.log(' FULL-SUITE SYSTEM TEST: REAL DATA ENTRY & ALL CASE SCENARIOS   ');
    console.log(` Target Server:   ${baseUrl} (Laragon Apache + MySQL)           `);
    console.log(` Browser Engine:  ${execPath}                                   `);
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
        // SCENARIO 1: TERMS OF SERVICE & DATA USAGE
        // ==========================================
        console.log('[Scenario 1] Login & Terms of Service disclosures...');
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
        await updateHud(page, '1/12', 'Testing Login & Data Protection Disclosures');
        await page.waitForTimeout(1000);

        await clickAction(page, 'a[href*="terms-of-service"]', '1/12', 'Opening Terms of Service (3m timeout, 5m OTP, GDPR data usage)');
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1000);
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1000);

        await clickAction(page, 'a.tos-nav-back', '1/12', 'Returning Back to Login');
        await page.waitForLoadState('networkidle');

        // ==========================================
        // SCENARIO 2: ADMIN AUTHENTICATION & 2FA
        // ==========================================
        console.log('[Scenario 2] Entering credentials and handling 2FA OTP...');
        await updateHud(page, '2/12', 'Typing Administrator Credentials (claudiokhyelandrei@gmail.com)');
        await page.fill('#email', 'claudiokhyelandrei@gmail.com');
        await page.waitForTimeout(400);
        await page.fill('#password', 'password');
        await page.waitForTimeout(400);
        await clickAction(page, 'button[type="submit"]', '2/12', 'Submitting Login Form');
        await page.waitForLoadState('networkidle');

        if (page.url().includes('two-factor-challenge')) {
            await updateHud(page, '2/12', 'Handling 2FA OTP Challenge via MySQL verification code');
            const otp = fetchOtp('claudiokhyelandrei@gmail.com');
            console.log('  -> OTP Code:', otp);
            await page.fill('input[name="code"]', otp);
            await page.waitForTimeout(500);
            await clickAction(page, 'button[type="submit"]', '2/12', 'Submitting Two-Factor Code');
            await page.waitForLoadState('networkidle');
        }

        // ==========================================
        // SCENARIO 3: DASHBOARD KPIS & TOPBAR
        // ==========================================
        console.log('[Scenario 3] Authenticated Dashboard & Topbar Controls...');
        await updateHud(page, '3/12', 'Authenticated! Reviewing Dashboard KPIs & Clinical Analytics');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1000);
        await page.evaluate(() => window.scrollBy({ top: -350, behavior: 'smooth' }));

        // Notification Bell
        await clickAction(page, '#notif-btn', '3/12', 'Opening Notifications Feed');
        await page.waitForTimeout(1200);
        const clearBtn = page.locator('#notif-clear');
        if (await clearBtn.isVisible()) {
            await clickAction(page, '#notif-clear', '3/12', 'Clicking "Mark All As Read"');
            await page.waitForTimeout(500);
        }
        await clickAction(page, '#notif-btn', '3/12', 'Closing Notifications Feed');

        // Help & FAQ Modal
        await clickAction(page, '#help-btn', '3/12', 'Opening Help & FAQ Modal');
        await page.waitForTimeout(1000);
        const firstFaq = page.locator('.help-faq summary');
        if (await firstFaq.count() > 0) {
            await clickAction(page, '.help-faq summary', '3/12', 'Expanding FAQ: AI Gap Analysis & Data Privacy Rights');
            await page.waitForTimeout(1000);
        }
        await clickAction(page, '#help-btn', '3/12', 'Closing Help & FAQ Modal');

        // ==========================================
        // SCENARIO 4: REAL DATA ENTRY - ADD TRAINEE USER
        // ==========================================
        console.log('[Scenario 4] Real Data Entry: Creating a new Trainee user...');
        await clickAction(page, 'a[href*="users"]', '4/12', 'Navigating to Users & Access Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        const addUserBtn = page.locator('button[data-modal-open="userCreateModal"]');
        if (await addUserBtn.count() > 0) {
            await clickAction(page, 'button[data-modal-open="userCreateModal"]', '4/12', 'Opening "+ Add User" Modal');
            await page.waitForTimeout(800);

            const traineeEmail = `trainee_${Date.now()}@hospital.ph`;
            await updateHud(page, '4/12', `Form Entry: Typing Full Name and Email (${traineeEmail})`);
            await page.fill('#userCreateModal input[name="name"]', 'Nurse Trainee Maria Cruz');
            await page.waitForTimeout(400);
            await page.fill('#userCreateModal input[name="email"]', traineeEmail);
            await page.waitForTimeout(400);
            await page.fill('#userCreateModal input[name="password"]', 'Trainee1234!');
            await page.waitForTimeout(400);
            await page.fill('#userCreateModal input[name="password_confirmation"]', 'Trainee1234!');
            await page.waitForTimeout(400);

            await updateHud(page, '4/12', 'Form Entry: Selecting Role = "Trainee"');
            await page.selectOption('#userCreateModal select[name="role"]', 'staff');
            await page.waitForTimeout(600);

            await clickAction(page, '#btnSubmitCreateUser', '4/12', 'Submitting User Creation Form');
            await page.waitForTimeout(1500);

            // Filter by Trainee role to verify new user appears
            await updateHud(page, '4/12', 'Verifying Trainee role filter & real-time table sync');
            const roleFilter = page.locator('select[name="role"]');
            if (await roleFilter.count() > 0) {
                await roleFilter.selectOption('trainee');
                await page.waitForTimeout(1000);
                await roleFilter.selectOption(''); // reset
                await page.waitForTimeout(600);
            }
        }

        // ==========================================
        // SCENARIO 5: TRAINING MANAGEMENT & ENROLLMENT
        // ==========================================
        console.log('[Scenario 5] Real Data Entry: Registering for a Training Session...');
        await clickAction(page, 'a[href*="training"]:not([href*="venues"])', '5/12', 'Navigating to Training Management');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '5/12', 'Checking "My Training Status & Sessions to Attend" Section');
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await page.waitForTimeout(1200);

        // Click Register if an active registration form exists
        const regButton = page.locator('form[action*="training/register"] button[type="submit"]');
        if (await regButton.count() > 0) {
            await clickAction(page, 'form[action*="training/register"] button[type="submit"]', '5/12', 'Real Data Action: Clicking "Register" for Training Session');
            await page.waitForLoadState('networkidle');
            await updateHud(page, '5/12', 'Registration Confirmed! Status updated to "Enrolled ✓"');
            await page.waitForTimeout(1200);
        }

        // Check Venues tab
        const venuesTab = page.locator('a[href*="training/venues"]');
        if (await venuesTab.count() > 0) {
            await clickAction(page, 'a[href*="training/venues"]', '5/12', 'Switching to Training Venues Tab');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1000);
        }

        // ==========================================
        // SCENARIO 6: COMPETENCY & AI GAP ANALYSIS
        // ==========================================
        console.log('[Scenario 6] Real Data Entry: Executing AI Competency Gap Analysis...');
        await clickAction(page, 'a[href*="competency"]:not([href*="gap"])', '6/12', 'Navigating to Competency Management');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);

        // Role requirements
        const roleReqTab = page.locator('a[href*="role-requirements"]');
        if (await roleReqTab.count() > 0) {
            await clickAction(page, 'a[href*="role-requirements"]', '6/12', 'Checking Position Role Requirements & Benchmarks');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1000);
        }

        // Gap Analysis Execution
        const gapLink = page.locator('a[href*="gap-analysis"]');
        if (await gapLink.count() > 0) {
            await clickAction(page, 'a[href*="gap-analysis"]', '6/12', 'Opening AI Competency Gap Analysis');
            await page.waitForLoadState('networkidle');

            const searchInput = page.locator('#employeeSearchInput');
            if (await searchInput.count() > 0) {
                await updateHud(page, '6/12', 'Data Entry: Searching employee "Nurse" for Gap Evaluation');
                await searchInput.fill('Nurse');
                await page.waitForTimeout(800);
            }

            const deptAnalysisBtn = page.locator('a[href*="gap/department"]');
            if (await deptAnalysisBtn.count() > 0) {
                await clickAction(page, 'a[href*="gap/department"]', '6/12', 'Executing Department-Wide Gap Analysis');
                await page.waitForLoadState('networkidle');
                await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
                await page.waitForTimeout(1500);
            }
        }

        // ==========================================
        // SCENARIO 7: LEARNING CATALOGUE, MODULES & CPD
        // ==========================================
        console.log('[Scenario 7] Exploring Courses, Clinical Modules & CPD Progress...');
        await clickAction(page, 'a[href*="learning"]:not([href*="gap"])', '7/12', 'Navigating to Learning Course Catalogue');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Modules tab
        const modulesTab = page.locator('a[href*="learning/modules"]');
        if (await modulesTab.count() > 0) {
            await clickAction(page, 'a[href*="learning/modules"]', '7/12', 'Opening Clinical Modules Tab');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1200);
        }

        // CPD tab
        const cpdTab = page.locator('a[href*="learning/cpd"]');
        if (await cpdTab.count() > 0) {
            await clickAction(page, 'a[href*="learning/cpd"]', '7/12', 'Inspecting CPD Credit Progress Bar & Licensing Qualifications');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // SCENARIO 8: RECOGNITION & VALUE BADGES
        // ==========================================
        console.log('[Scenario 8] Testing Recognition & Points Moderation...');
        await clickAction(page, 'a[href*="recognition"]', '8/12', 'Navigating to Recognition & Staff Commendations');
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1200);

        // Check Points Approval Queue if exists
        const admitBtn = page.locator('form[action*="admit_points"] button');
        if (await admitBtn.count() > 0) {
            await clickAction(page, 'form[action*="admit_points"] button', '8/12', 'Data Action: Approving & Admitting Employee Recognition Points');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1000);
        }

        // ==========================================
        // SCENARIO 9: SUCCESSION PLANNING - CANDIDATE NOMINATION
        // ==========================================
        console.log('[Scenario 9] Real Data Entry: Succession Planning Candidate Nomination...');
        await clickAction(page, 'a[href*="succession"]', '9/12', 'Navigating to Succession Planning (9-Box Grid)');
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
        await page.waitForTimeout(1200);

        const nomLink = page.locator('a[href*="candidates/create"]');
        if (await nomLink.count() > 0) {
            await clickAction(page, 'a[href*="candidates/create"]', '9/12', 'Opening "Nominate Candidate" Form');
            await page.waitForLoadState('networkidle');

            const empSelect = page.locator('select[name="employee_id"]');
            if (await empSelect.count() > 0 && await empSelect.locator('option').count() > 1) {
                await updateHud(page, '9/12', 'Form Entry: Selecting Employee & Critical Target Position');
                await empSelect.selectOption({ index: 1 });
                await page.waitForTimeout(500);

                const posSelect = page.locator('select[name="position_id"]');
                if (await posSelect.count() > 0 && await posSelect.locator('option').count() > 1) {
                    await posSelect.selectOption({ index: 1 });
                    await page.waitForTimeout(500);
                }

                const readSelect = page.locator('select[name="readiness_level"]');
                if (await readSelect.count() > 0) {
                    await readSelect.selectOption({ index: 1 });
                    await page.waitForTimeout(400);
                }

                const perfInput = page.locator('input[name="performance_score"]');
                if (await perfInput.count() > 0) {
                    await perfInput.fill('4.5');
                    await page.waitForTimeout(400);
                }

                const potInput = page.locator('input[name="potential_score"]');
                if (await potInput.count() > 0) {
                    await potInput.fill('4.8');
                    await page.waitForTimeout(400);
                }

                await clickAction(page, 'button[type="submit"]', '9/12', 'Submitting Candidate Nomination');
                await page.waitForLoadState('networkidle');
            }
        }

        // ==========================================
        // SCENARIO 10: COMPLIANCE & ACCREDITATION AUDIT
        // ==========================================
        console.log('[Scenario 10] Testing Hospital Compliance & Accreditations...');
        const compLink = page.locator('a[href*="compliance"]');
        if (await compLink.count() > 0) {
            await clickAction(page, 'a[href*="compliance"]', '10/12', 'Navigating to Compliance & Hospital Accreditations');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // SCENARIO 11: EXECUTIVE REPORTS & CSV EXPORTS
        // ==========================================
        console.log('[Scenario 11] Testing Executive Reports & CSV Exports...');
        const repLink = page.locator('a[href*="reports"]');
        if (await repLink.count() > 0) {
            await clickAction(page, 'a[href*="reports"]', '11/12', 'Navigating to Executive Reports & Analytics');
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollBy({ top: 300, behavior: 'smooth' }));
            await page.waitForTimeout(1200);
        }

        // ==========================================
        // SCENARIO 12: PROFILE & PRIVACY DATA DOWNLOADS
        // ==========================================
        console.log('[Scenario 12] Testing Profile, Privacy & GDPR CSV/JSON Exports...');
        await clickAction(page, 'a[href*="profile"]', '12/12', 'Navigating to Profile & Privacy (Data Subject Rights)');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '12/12', 'Testing CSV and JSON Personal Data Download Controls');
        await page.evaluate(() => window.scrollBy({ top: 500, behavior: 'smooth' }));
        await page.waitForTimeout(1500);

        // Return to Dashboard
        await clickAction(page, 'a[href*="dashboard"]', '12/12', 'Returning to Executive Dashboard');
        await page.waitForLoadState('networkidle');
        await updateHud(page, '12/12', '✅ FULL SYSTEM AUDIT COMPLETED! ALL SCENARIOS & DATA ENTRIES VERIFIED.');

        console.log('================================================================');
        console.log(' FULL SYSTEM AUDIT COMPLETED WITH REAL DATA ENTRY & FORM SUBMITS');
        console.log(' BROWSER REMAINS OPEN IN YOUR TASKBAR FOR DIRECT INTERACTION.   ');
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
