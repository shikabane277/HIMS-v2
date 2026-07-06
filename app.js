/* ==========================================================================
   HIMS Performance & Development - Core Application Logic & Mock Engine
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------
    // 1. Core Mock Database
    // ---------------------------------------------------------
    let db = null;
    const savedDb = localStorage.getItem('hims_database');
    if (savedDb) {
        try {
            db = JSON.parse(savedDb);
        } catch (e) {
            console.error("Failed to load saved HIMS database, reinitializing default records.", e);
        }
    }

    if (!db) {
        db = {
            employees: [
                { id: 'EMP-001', name: 'Dr. Elena Rostova', title: 'Medical Director / Administrator', dept: 'Administration', role: 'admin', img: 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=150', license: 'PRC-MED-9921', licenseExpiry: '2027-11-20', status: 'Active' },
                { id: 'EMP-002', name: 'Nurse Maria Santos', title: 'Senior ICU Nurse', dept: 'Nursing', role: 'employee', img: 'https://images.unsplash.com/photo-1594824813573-246434de83fb?auto=format&fit=crop&q=80&w=150', license: 'PRC-NUR-1204', licenseExpiry: '2026-08-15', status: 'Active' },
                { id: 'EMP-003', name: 'Nurse Clara de Leon', title: 'Ward Head Nurse', dept: 'Nursing', role: 'employee', img: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150', license: 'PRC-NUR-8834', licenseExpiry: '2026-07-28', status: 'Active' },
                { id: 'EMP-004', name: 'Dr. Albert Lim', title: 'Chief of Surgery / Dept Head', dept: 'Surgery', role: 'dept_head', img: 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=150', license: 'PRC-MED-0912', licenseExpiry: '2026-07-10', status: 'Active' },
                { id: 'EMP-005', name: 'John Doe', title: 'Admin Clerk', dept: 'Administration', role: 'employee', img: 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=150', license: null, licenseExpiry: null, status: 'Active' },
                { id: 'EMP-006', name: 'Nurse Carlos Diaz', title: 'ER Nurse', dept: 'Emergency Room', role: 'employee', img: 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=150', license: 'PRC-NUR-4412', licenseExpiry: '2026-05-12', status: 'Active' },
                { id: 'EMP-007', name: 'Dr. Sarah Tiongson', title: 'Pediatric Consultant', dept: 'Pediatrics', role: 'supervisor', img: 'https://images.unsplash.com/photo-1594824813573-246434de83fb?auto=format&fit=crop&q=80&w=150', license: 'PRC-MED-5561', licenseExpiry: '2028-01-30', status: 'Active' },
                { id: 'EMP-008', name: 'Clara Oswald', title: 'HR Officer / Admin', dept: 'Administration', role: 'hr', img: 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&q=80&w=150', license: null, licenseExpiry: null, status: 'Active' },
                { id: 'EMP-009', name: 'Mark Ramos', title: 'Education & Training Officer', dept: 'Administration', role: 'training_officer', img: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150', license: null, licenseExpiry: null, status: 'Active' }
            ],
            performanceReviews: [
                { id: 'REV-001', employeeId: 'EMP-002', employeeName: 'Nurse Maria Santos', reviewer: 'Dr. Sarah Tiongson', cycle: 'Annual 2025', overallScore: 4.2, selfRating: 4.0, supervisorRating: 4.2, peerRating: 4.4, status: 'Approved', auditNotes: 'No subjective issues flagged.', strengths: 'Excellent patient bedside care, highly responsive in critical ICU setups.', weaknesses: 'Needs to participate more in ward planning activities.' },
                { id: 'REV-002', employeeId: 'EMP-003', employeeName: 'Nurse Clara de Leon', reviewer: 'Dr. Albert Lim', cycle: 'Annual 2025', overallScore: 4.8, selfRating: 4.7, supervisorRating: 4.9, peerRating: 4.8, status: 'Approved', auditNotes: 'No issues flagged.', strengths: 'Exemplary leadership skills, organizes ward rosters effectively, zero incident record.', weaknesses: 'None noted. Ready for administrative promotion.' },
                { id: 'REV-003', employeeId: 'EMP-005', employeeName: 'John Doe', reviewer: 'Dr. Elena Rostova', cycle: 'Q1 2026', overallScore: 3.4, selfRating: 3.2, supervisorRating: 3.5, peerRating: 3.5, status: 'Approved', auditNotes: 'Draft contains biased comment: "does okay". Remedied to "satisfactory".', strengths: 'Reliable in file sorting, punctual.', weaknesses: 'Needs to improve software navigation speed.' },
                { id: 'REV-004', employeeId: 'EMP-006', employeeName: 'Nurse Carlos Diaz', reviewer: 'Dr. Sarah Tiongson', cycle: 'Q1 2026', overallScore: 3.8, selfRating: 3.8, supervisorRating: 3.7, peerRating: 3.9, status: 'Pending Approval', auditNotes: 'Awaiting Department Head sign-off.', strengths: 'Handles fast-paced ER workloads efficiently.', weaknesses: 'Needs improvement in patient communication under stress.' }
            ],
            competencies: [
                { id: 'COMP-001', name: 'Infection Control Protocol', category: 'Clinical', requiredProficiency: 4 },
                { id: 'COMP-002', name: 'Basic Life Support (BLS)', category: 'Clinical', requiredProficiency: 5 },
                { id: 'COMP-003', name: 'Patient Communication', category: 'Administrative', requiredProficiency: 4 },
                { id: 'COMP-004', name: 'Critical Vent Protocol', category: 'Technical', requiredProficiency: 5 }
            ],
            competencyAssessments: [
                { employeeId: 'EMP-002', employeeName: 'Nurse Maria Santos', c1: 4, c2: 5, c3: 3.2, c4: 3.4 },
                { employeeId: 'EMP-003', employeeName: 'Nurse Clara de Leon', c1: 5, c2: 5, c3: 4.8, c4: 4.5 },
                { employeeId: 'EMP-006', employeeName: 'Nurse Carlos Diaz', c1: 3.5, c2: 5, c3: 3.0, c4: 2.5 }
            ],
            courses: [
                { id: 'CRS-001', title: 'JCI Sterile & Infection Control Techniques', desc: 'Mandatory standard compliance module covering ward sanitation, barrier methods, and clinical procedures.', category: 'Compliance', cpdHours: 12, completionRate: 94, progress: 100, completed: true },
                { id: 'CRS-002', title: 'Advanced Cardiac & Basic Life Support (BLS)', desc: 'Simulation training course focusing on immediate clinical response, defibrillation, and team resuscitation protocols.', category: 'Clinical Care', cpdHours: 15, completionRate: 88, progress: 60, completed: false },
                { id: 'CRS-003', title: 'Active Patient Care Communication', desc: 'Soft-skills and compliance seminar teaching bedside manners, emergency notifications, and Tagalog patient interaction.', category: 'Compliance', cpdHours: 8, completionRate: 75, progress: 0, completed: false }
            ],
            trainingSessions: [
                { id: 'TRN-001', title: 'Infection Control Seminar', date: '2026-07-12', time: '09:00 - 12:00', venue: 'Main Auditorium A', instructor: 'Dr. Elena Rostova', capacity: 40, registered: 35, attendees: 32, category: 'compliance', feedback: [
                    { rating: 5, comment: 'Salamat, napaka-informative at direkta sa punto.' },
                    { rating: 4, comment: 'Good session, but the simulator room was quite cold.' },
                    { rating: 5, comment: 'Crucial refresher for JCI audit alignment.' }
                ] },
                { id: 'TRN-002', title: 'Basic Life Support Hands-on', date: '2026-07-20', time: '13:00 - 17:00', venue: 'ICU Simulation Center', instructor: 'Dr. Sarah Tiongson', capacity: 15, registered: 15, attendees: 15, category: 'clinical', feedback: [
                    { rating: 5, comment: 'The hands-on defibrillation test was excellent.' },
                    { rating: 5, comment: 'Highly recommended for ER interns.' }
                ] }
            ],
            successionPlans: [
                { id: 'SUC-001', position: 'Ward Head Nurse', critical: true, holder: 'Nurse Clara de Leon', successor: 'Nurse Maria Santos', readiness: 'Ready Now', potential: 3, performance: 3, risk: 'High', status: 'Approved' },
                { id: 'SUC-002', position: 'Chief of Surgery', critical: true, holder: 'Dr. Albert Lim', successor: 'Dr. Sarah Tiongson', readiness: '1-2 Years', potential: 2, performance: 3, risk: 'Medium', status: 'Proposed' },
                { id: 'SUC-003', position: 'ICU Lead Specialist', critical: true, holder: 'Dr. Sarah Tiongson', successor: 'Nurse Clara de Leon', readiness: '3+ Years', potential: 3, performance: 2, risk: 'Low', status: 'Proposed' }
            ],
            recognitionPosts: [
                { id: 'REC-001', author: 'Dr. Sarah Tiongson', authorImg: 'https://images.unsplash.com/photo-1594824813573-246434de83fb?auto=format&fit=crop&q=80&w=150', target: 'Nurse Maria Santos', badge: 'Compassion (Kalinga)', msg: 'Salamat kay Nurse Maria sa matiyagang pag-aalaga sa ICU Patient sa bed 4 habang understaffed kagabi! Truly showed outstanding bedside manner.', likes: 14, commentsCount: 2, timestamp: '2 hours ago' },
                { id: 'REC-002', author: 'Nurse Clara de Leon', authorImg: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150', target: 'John Doe', badge: 'Teamwork (Bayanihan)', msg: 'Big thanks to John for sorting the critical compliance files before the auditor team arrived. Great support!', likes: 8, commentsCount: 0, timestamp: '1 day ago' }
            ],
            auditTrails: [
                { timestamp: '2026-07-06T05:12:00Z', user: 'Dr. Elena Rostova', role: 'admin', action: 'Accessed succession registry metrics', resource: 'SUCCESSION_PLANS', ip: '192.168.10.45', statusHash: 'sha256-ac81a' },
                { timestamp: '2026-07-06T04:30:00Z', user: 'Mark Ramos', role: 'training_officer', action: 'Approved CPR Course Quiz completions for ICU cohort', resource: 'COURSES', ip: '192.168.12.80', statusHash: 'sha256-8ff90' },
                { timestamp: '2026-07-06T02:15:00Z', user: 'Dr. Sarah Tiongson', role: 'supervisor', action: 'Submitted Performance Review draft for Carlos Diaz', resource: 'PERFORMANCE_REVIEWS', ip: '192.168.11.11', statusHash: 'sha256-bd13d' }
            ],
            notifications: [
                { id: 'NOTI-001', title: 'PRC Nurse License Expiry Warning', text: 'PRC License for Nurse Clara de Leon expires in 22 days.', type: 'danger', date: 'Just now' },
                { id: 'NOTI-002', title: 'Pending Review Action', text: 'Performance Review of Nurse Carlos Diaz requires approval.', type: 'warning', date: '1 hour ago' },
                { id: 'NOTI-003', title: 'ICU Competency Gap Flagged', text: 'Advanced Vent Support competency averages under standard in Nursing Ward.', type: 'info', date: '3 hours ago' }
            ],
            users: [
                { username: 'admin', passwordHash: '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', role: 'admin', employeeId: 'EMP-001', name: 'Dr. Elena Rostova' }, // admin123
                { username: 'hr', passwordHash: '070a3b5e8d4bd5c46acccb91c9c54614c0cd649e78c4c4719e3a64270bae5ddf', role: 'hr', employeeId: 'EMP-008', name: 'Clara Oswald' }, // hr123
                { username: 'clara', passwordHash: 'c7b418ece9991f8e5182e257e8e609c8d2a7a49d3d3f140692a52ab75b197030', role: 'dept_head', employeeId: 'EMP-003', name: 'Nurse Clara de Leon' }, // clara123
                { username: 'sarah', passwordHash: 'dd7f6bfb6e0d8bcd754e97cae4975c07996f00508f8346d649c5814e19c3f9b9', role: 'supervisor', employeeId: 'EMP-007', name: 'Dr. Sarah Tiongson' }, // sarah123
                { username: 'maria', passwordHash: '626e3c805e77eeb472c42c6be607be2af7ac5c08fd7050f278e0330fe81abf57', role: 'employee', employeeId: 'EMP-002', name: 'Nurse Maria Santos' }, // maria123
                { username: 'carlos', passwordHash: 'ac9c2c34c9f7ad52528c3422af40a66e2e24aaf2a727831255413c9470158984', role: 'employee', employeeId: 'EMP-006', name: 'Nurse Carlos Diaz' } // carlos123
            ]
        };
        localStorage.setItem('hims_database', JSON.stringify(db));
    }

    function saveDbToStorage() {
        localStorage.setItem('hims_database', JSON.stringify(db));
    }

    // ---------------------------------------------------------
    // 2. Global State Management
    // ---------------------------------------------------------
    let loggedInUser = null;
    let currentRole = null; // admin, hr, dept_head, supervisor, training_officer, employee
    let activeTab = 'dashboard';
    let myChart = null;
    let currentCalMonth = 6;   // 0-indexed: July
    let currentCalYear = 2026;


    // ---------------------------------------------------------
    // 3. Selectors & DOM Bindings
    // ---------------------------------------------------------
    const logoutBtn = document.getElementById('logout-btn');
    const viewTitle = document.getElementById('view-title');
    const viewDescription = document.getElementById('view-description');
    const profileImg = document.getElementById('profile-img');
    const profileName = document.getElementById('profile-name');
    const profileRole = document.getElementById('profile-role');
    const navItems = document.querySelectorAll('.nav-item');
    const tabPanels = document.querySelectorAll('.tab-panel');
    const notificationBell = document.getElementById('noti-toggle');
    const notificationDropdown = document.getElementById('noti-dropdown');
    const notiBadge = document.getElementById('noti-badge');
    const notiList = document.getElementById('noti-list');
    const markAllReadBtn = document.getElementById('mark-all-read');
    const auditBtn = document.getElementById('audit-trail-btn');
    const auditModal = document.getElementById('audit-modal');
    const auditTableBody = document.getElementById('audit-logs-table-body');
    const closeModalBtns = document.querySelectorAll('.close-modal');

    // ---------------------------------------------------------
    // 4. Initialization & Routing
    // ---------------------------------------------------------
    function init() {
        bindEvents();
        loadAPIKey();
        
        // Session Check
        const savedSession = localStorage.getItem('hims_logged_in_user');
        if (savedSession) {
            try {
                loggedInUser = JSON.parse(savedSession);
                currentRole = loggedInUser.role;
                
                document.getElementById('login-screen').classList.add('layout-hidden');
                document.querySelector('.hims-layout').classList.remove('layout-hidden');
                
                updateRoleState();
                renderTab(activeTab);
                setupAI();
                populateEmployeeDropdowns();
            } catch (err) {
                console.error("Error restoration user session:", err);
                logout();
            }
        } else {
            // Force show login screen
            document.getElementById('login-screen').classList.remove('layout-hidden');
            document.querySelector('.hims-layout').classList.add('layout-hidden');
        }
    }


    function bindEvents() {
        // Tab switching
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                navItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
                activeTab = item.getAttribute('data-tab');
                renderTab(activeTab);
            });
        });

        // Logout Event
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                logout();
            });
        }

        // Login Submit
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const usernameInput = document.getElementById('login-username').value.trim();
                const passwordInput = document.getElementById('login-password').value;
                await performLogin(usernameInput, passwordInput);
            });
        }

        // Change Password Submit
        const changePasswordForm = document.getElementById('change-password-form');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const currentInput = document.getElementById('change-pwd-current').value;
                const newInput = document.getElementById('change-pwd-new').value;
                const confirmInput = document.getElementById('change-pwd-confirm').value;

                if (!loggedInUser) {
                    showToast('You must be logged in to change your password.', 'danger');
                    return;
                }

                const currentHash = await hashPassword(currentInput);
                if (loggedInUser.passwordHash !== currentHash) {
                    showToast('Current password does not match.', 'danger');
                    return;
                }

                if (newInput !== confirmInput) {
                    showToast('New passwords do not match.', 'danger');
                    return;
                }

                if (newInput.length < 6) {
                    showToast('New password must be at least 6 characters long.', 'warning');
                    return;
                }

                const newHash = await hashPassword(newInput);
                
                // Update in database users registry
                const userInDb = db.users.find(u => u.username.toLowerCase() === loggedInUser.username.toLowerCase());
                if (userInDb) {
                    userInDb.passwordHash = newHash;
                    loggedInUser.passwordHash = newHash;
                    localStorage.setItem('hims_logged_in_user', JSON.stringify(loggedInUser));
                    
                    showToast('Password updated and encrypted in local database.', 'success');
                    logAudit('Changed account password', 'USER_ACCOUNTS');
                    changePasswordForm.reset();
                } else {
                    showToast('User record not found in active database state.', 'danger');
                }
            });
        }

        // Notifications Toggle
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('dropdown-hidden');
        });

        document.addEventListener('click', () => {
            notificationDropdown.classList.add('dropdown-hidden');
        });

        markAllReadBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            db.notifications = [];
            renderNotifications();
            showToast('All notifications cleared', 'success');
        });

        // Security Audit Trigger
        auditBtn.addEventListener('click', () => {
            renderAuditTrail();
            auditModal.classList.add('modal-open');
        });

        // Settings Modal Trigger
        const settingsBtn = document.getElementById('settings-btn');
        const settingsModal = document.getElementById('settings-modal');
        if (settingsBtn && settingsModal) {
            settingsBtn.addEventListener('click', () => {
                loadAPIKey();
                settingsModal.classList.add('modal-open');
            });
        }

        // Toggle Key Visibility
        const toggleVisBtn = document.getElementById('toggle-key-visibility');
        const apiKeyInput = document.getElementById('ai-api-key');
        if (toggleVisBtn && apiKeyInput) {
            toggleVisBtn.addEventListener('click', () => {
                const type = apiKeyInput.getAttribute('type') === 'password' ? 'text' : 'password';
                apiKeyInput.setAttribute('type', type);
                toggleVisBtn.innerHTML = type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
            });
        }

        // Provider Selector change handling
        const providerSelect = document.getElementById('ai-provider-select');
        const modelInput = document.getElementById('ai-model-name');
        const baseUrlInput = document.getElementById('ai-base-url');
        const baseUrlContainer = document.getElementById('base-url-container');

        if (providerSelect && modelInput && baseUrlInput && baseUrlContainer) {
            providerSelect.addEventListener('change', () => {
                const prov = providerSelect.value;
                if (prov === 'gemini') {
                    baseUrlContainer.style.display = 'none';
                    modelInput.placeholder = 'e.g. gemini-3.5-flash';
                    if (!modelInput.value) modelInput.value = 'gemini-3.5-flash';
                } else if (prov === 'openai') {
                    baseUrlContainer.style.display = 'block';
                    baseUrlInput.placeholder = 'e.g. https://api.openai.com/v1';
                    if (!baseUrlInput.value) baseUrlInput.value = 'https://api.openai.com/v1';
                    modelInput.placeholder = 'e.g. gpt-4o-mini';
                    if (!modelInput.value || modelInput.value.startsWith('gemini')) modelInput.value = 'gpt-4o-mini';
                } else {
                    // Custom
                    baseUrlContainer.style.display = 'block';
                    baseUrlInput.placeholder = 'e.g. https://api.deepseek.com/v1';
                    modelInput.placeholder = 'e.g. deepseek-chat';
                }
            });
        }

        // Save AI Provider Credentials
        const saveKeyBtn = document.getElementById('btn-save-api-key');
        if (saveKeyBtn && apiKeyInput && providerSelect && modelInput && baseUrlInput) {
            saveKeyBtn.addEventListener('click', async () => {
                const key = apiKeyInput.value.trim();
                const provider = providerSelect.value;
                const model = modelInput.value.trim() || (provider === 'gemini' ? 'gemini-3.5-flash' : 'gpt-4o-mini');
                const baseUrl = baseUrlInput.value.trim() || (provider === 'openai' ? 'https://api.openai.com/v1' : '');

                if (!key) {
                    showToast('Please enter an API key / token.', 'warning');
                    return;
                }

                saveKeyBtn.disabled = true;
                saveKeyBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Saving...`;

                const statusText = document.getElementById('api-status-text');
                const statusBar = document.getElementById('api-status-bar');
                statusText.innerText = `Testing connection to ${provider}...`;

                const result = await testAIConnection(key, provider, model, baseUrl);

                if (result.success) {
                    localStorage.setItem('ai_provider', provider);
                    localStorage.setItem('ai_api_key', key);
                    localStorage.setItem('ai_model_name', model);
                    localStorage.setItem('ai_base_url', baseUrl);

                    statusBar.className = 'api-status-bar connected';
                    statusText.innerHTML = `<i class="fa-solid fa-circle-check"></i> Connected! AI is online using <strong>${model}</strong> via ${provider}.`;
                    showToast('AI Provider settings saved successfully.', 'success');
                    logAudit(`Configured AI Provider (${provider}: ${model})`, 'USER_ACCOUNTS');
                } else {
                    statusBar.className = 'api-status-bar failed';
                    statusText.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Connection Failed: ${result.error}`;
                    showToast('AI credentials validation failed.', 'danger');
                }
                saveKeyBtn.disabled = false;
                saveKeyBtn.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Save Key`;
            });
        }

        // Test connection
        const testKeyBtn = document.getElementById('btn-test-api');
        if (testKeyBtn && apiKeyInput && providerSelect && modelInput && baseUrlInput) {
            testKeyBtn.addEventListener('click', async () => {
                const key = apiKeyInput.value.trim();
                const provider = providerSelect.value;
                const model = modelInput.value.trim() || (provider === 'gemini' ? 'gemini-3.5-flash' : 'gpt-4o-mini');
                const baseUrl = baseUrlInput.value.trim() || (provider === 'openai' ? 'https://api.openai.com/v1' : '');

                if (!key) {
                    showToast('Please enter an API key to test.', 'warning');
                    return;
                }

                testKeyBtn.disabled = true;
                testKeyBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Testing...`;

                const result = await testAIConnection(key, provider, model, baseUrl);
                const statusText = document.getElementById('api-status-text');
                const statusBar = document.getElementById('api-status-bar');

                if (result.success) {
                    statusBar.className = 'api-status-bar connected';
                    statusText.innerHTML = `<i class="fa-solid fa-circle-check"></i> Connection active. AI returned: "${result.text}"`;
                    showToast('Connection test passed.', 'success');
                } else {
                    statusBar.className = 'api-status-bar failed';
                    statusText.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Connection test failed: ${result.error}`;
                    showToast('Connection test failed.', 'danger');
                }
                testKeyBtn.disabled = false;
                testKeyBtn.innerHTML = `<i class="fa-solid fa-plug"></i> Test Connection`;
            });
        }

        // Clear Settings
        const clearKeyBtn = document.getElementById('btn-clear-api-key');
        if (clearKeyBtn) {
            clearKeyBtn.addEventListener('click', () => {
                localStorage.removeItem('ai_provider');
                localStorage.removeItem('ai_api_key');
                localStorage.removeItem('ai_model_name');
                localStorage.removeItem('ai_base_url');
                loadAPIKey();
                showToast('AI Settings cleared.', 'info');
                logAudit('Cleared AI Provider credentials', 'USER_ACCOUNTS');
            });
        }

        // Close Modals
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.hims-modal').forEach(modal => modal.classList.remove('modal-open'));
            });
        });

        // Dynamic modals dismiss on overlay click
        document.querySelectorAll('.hims-modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.remove('modal-open');
            });
        });

        // Performance Action Create Review
        document.getElementById('btn-create-evaluation').addEventListener('click', () => {
            if (currentRole === 'employee') {
                showToast('Employees are only permitted to submit self-assessments.', 'warning');
            }
            document.getElementById('evaluation-modal').classList.add('modal-open');
        });

        // Performance review forms submit
        document.getElementById('evaluation-creation-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const empId = document.getElementById('eval-employee').value;
            const emp = db.employees.find(x => x.id === empId);
            const cycle = document.getElementById('eval-cycle').value;
            const score = parseFloat(document.getElementById('eval-rating').value);
            const strengths = document.getElementById('eval-strengths').value;
            const weaknesses = document.getElementById('eval-weaknesses').value;

            const newReview = {
                id: `REV-00${db.performanceReviews.length + 1}`,
                employeeId: empId,
                employeeName: emp.name,
                reviewer: getActiveUserName(),
                cycle: cycle,
                overallScore: score,
                selfRating: score - 0.2,
                supervisorRating: score,
                peerRating: score + 0.1,
                status: ['admin', 'hr', 'dept_head'].includes(currentRole) ? 'Approved' : 'Pending Approval',
                auditNotes: 'Tone scanned by AI.',
                strengths: strengths,
                weaknesses: weaknesses
            };

            db.performanceReviews.unshift(newReview);
            showToast(`Evaluation for ${emp.name} saved successfully!`, 'success');
            document.getElementById('evaluation-modal').classList.remove('modal-open');
            renderPerformanceTab();
            logAudit(`Created review ${newReview.id} for ${emp.name}`, 'PERFORMANCE_REVIEWS');
            
            // Add notification
            db.notifications.unshift({
                id: `NOTI-0${Math.random()}`,
                title: 'New Evaluation Saved',
                text: `Submitted evaluation for ${emp.name} scoring ${score}`,
                type: 'success',
                date: 'Just now'
            });
            renderNotifications();
        });

        // Performance review AI audit comment helper
        document.getElementById('btn-perf-ai-audit').addEventListener('click', async () => {
            const strengths = document.getElementById('eval-strengths').value;
            const weaknesses = document.getElementById('eval-weaknesses').value;
            const resultBox = document.getElementById('perf-ai-audit-output');
            
            if (!strengths && !weaknesses) {
                resultBox.classList.remove('hidden');
                resultBox.innerHTML = `<span style="color:var(--color-danger);"><i class="fa-solid fa-triangle-exclamation"></i> Please input feedback comments first to audit.</span>`;
                return;
            }

            resultBox.classList.remove('hidden');
            resultBox.innerHTML = `<em><i class="fa-solid fa-spinner fa-spin"></i> Scanning review text with AI...</em>`;

            // Try Gemini API first
            const apiKey = localStorage.getItem('ai_api_key');
            if (apiKey) {
                const prompt = `You are a JCI-compliant HR AI auditor. Analyze the following clinical performance review text for bias, vague language, non-measurable assessments, or unprofessional tone. Provide structured feedback in HTML using <strong>, <ul>, <li>, <p>. If the text is clean, confirm it.\n\nStrengths:\n"${strengths}"\n\nImprovement Areas:\n"${weaknesses}"`;
                const geminiResult = await getGeminiAIResponse(prompt);
                if (geminiResult) {
                    resultBox.innerHTML = parseMarkdown(geminiResult);
                    logAudit(`AI tone audit performed on evaluation draft`, 'PERFORMANCE_REVIEWS');
                    return;
                }
            }

            // Local rule-based fallback
            setTimeout(() => {
                let issues = [];
                let suggestion = "";

                if (strengths.toLowerCase().includes('sometimes emotional') || weaknesses.toLowerCase().includes('sometimes emotional')) {
                    issues.push("Flagged potential gender bias / subjective language: <strong>'sometimes emotional'</strong>");
                }
                if (strengths.toLowerCase().includes('does okay') || weaknesses.toLowerCase().includes('does okay')) {
                    issues.push("Flagged vague standard rating: <strong>'does okay'</strong>");
                }

                if (issues.length > 0) {
                    suggestion = `<div class="ai-flagged-issues">
                        <strong style="color:var(--color-danger);"><i class="fa-solid fa-triangle-exclamation"></i> AI Tone Warnings:</strong>
                        <ul>${issues.map(x => `<li>${x}</li>`).join('')}</ul>
                        <p class="mt-5"><strong>Recommended Fix:</strong> Replace vague phrases with measurable metrics (e.g., JCI procedures alignment index or task punctuality rates).</p>
                    </div>`;
                } else {
                    suggestion = `<strong style="color:var(--color-success);"><i class="fa-solid fa-circle-check"></i> Tone Review Clean:</strong>
                    <p>No biased or non-professional expressions detected. Sentiments match clinical competency standards.</p>`;
                }
                resultBox.innerHTML = suggestion;
            }, 600);
        });

        // Competency action: Assess competency
        document.getElementById('btn-assess-competency').addEventListener('click', () => {
            showToast('Opening Competency Self-Audit Sheet...', 'info');
            // Mock trigger
            const gapTable = document.getElementById('competency-gap-body');
            // Make one nurse instantly proficient as simulator
            const carlos = db.competencyAssessments.find(x => x.employeeId === 'EMP-006');
            if (carlos) {
                carlos.c4 = 5.0; // resolve gap
                renderCompetencyTab();
                showToast("Recalculated competence gaps. Carlos Diaz is now proficient in Critical Vent Protocol.", "success");
                logAudit(`Updated competency assessment for Carlos Diaz`, 'COMPETENCY_ASSESSMENTS');
            }
        });

        // Training schedule planned session button
        document.getElementById('btn-schedule-session').addEventListener('click', () => {
            if (currentRole === 'employee') {
                showToast("Unauthorized. Only Training Officers and Admins can plan workshops.", "danger");
                return;
            }
            // Mock schedule session
            const newSession = {
                id: `TRN-00${db.trainingSessions.length + 1}`,
                title: 'Emergency Biohazard Ward Protocol',
                date: '2026-07-25',
                time: '10:00 - 12:00',
                venue: 'Simulator Room B',
                instructor: 'Dr. Sarah Tiongson',
                capacity: 25,
                registered: 8,
                attendees: 0,
                category: 'safety',
                feedback: []
            };
            db.trainingSessions.push(newSession);
            showToast("Successfully scheduled 'Emergency Biohazard Ward Protocol' on July 25, 2026", "success");
            renderTrainingTab();
            logAudit(`Scheduled new training session: ${newSession.title}`, 'TRAINING_SESSIONS');
        });

        // Succession planning creation plan modal
        document.getElementById('btn-create-succession-plan').addEventListener('click', () => {
            if (currentRole === 'employee') {
                showToast("Only HR Admins and hospital executives can configure leadership pipelines.", "danger");
                return;
            }
            const newPlan = {
                id: `SUC-00${db.successionPlans.length + 1}`,
                position: 'Emergency Department Coordinator',
                critical: true,
                holder: 'Dr. Albert Lim',
                successor: 'Nurse Carlos Diaz',
                readiness: '1-2 Years',
                potential: 2,
                performance: 2,
                risk: 'High',
                status: 'Proposed'
            };
            db.successionPlans.push(newPlan);
            showToast("Proposed Carlos Diaz as successor for Emergency Coordinator position.", "success");
            renderSuccessionTab();
            logAudit(`Proposed succession pipeline for Emergency Coordinator`, 'SUCCESSION_PLANS');
        });

        // Social Recognition Message AI Builder
        document.getElementById('btn-rec-ai-suggest').addEventListener('click', async () => {
            const targetNameSelect = document.getElementById('rec-target-user');
            const targetName = targetNameSelect.options[targetNameSelect.selectedIndex].text;
            const badge = document.getElementById('rec-badge').value;
            const messageBox = document.getElementById('rec-message');
            const suggestTip = document.getElementById('rec-ai-suggestion-text');

            suggestTip.innerText = "🤖 Formulating message with AI...";

            // Try Gemini API first
            const apiKey = localStorage.getItem('ai_api_key');
            if (apiKey) {
                const prompt = `Write a warm, professional hospital staff recognition message in Taglish (mix of Filipino/Tagalog and English) for a colleague named "${targetName}" who has been awarded the "${badge}" badge. Keep it genuine, brief (2-3 sentences), and hospital-appropriate. Output only the message text, no quotation marks.`;
                const geminiResult = await getGeminiAIResponse(prompt);
                if (geminiResult) {
                    messageBox.value = geminiResult.trim();
                    suggestTip.innerText = "✨ AI-generated personalized message.";
                    return;
                }
            }

            // Local template fallback
            setTimeout(() => {
                let msg = "";
                if (badge.includes("Compassion")) {
                    msg = `Napakalaking tulong ni ${targetName} sa ating bedside nursing care today! Puno ng pasensya at kalinga sa mga pasyente kahit napaka-busy ng shift natin. Thank you so much!`;
                } else if (badge.includes("Teamwork")) {
                    msg = `I want to express my appreciation to ${targetName} for stepping up and assisting the ward coordinators during the shift handover bottleneck. Real Bayanihan spirit!`;
                } else {
                    msg = `Exceptional documentation accuracy and clinical competence shown by ${targetName} today. Ensuring zero compliance errors on JCI standards checks. Cheers!`;
                }
                messageBox.value = msg;
                suggestTip.innerText = "Suggested standard clinical text.";
            }, 500);
        });

        // Social Recognition Form Publish
        document.getElementById('recognition-create-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const targetId = document.getElementById('rec-target-user').value;
            const targetEmp = db.employees.find(x => x.id === targetId);
            const badge = document.getElementById('rec-badge').value;
            const msg = document.getElementById('rec-message').value;

            const newRec = {
                id: `REC-00${db.recognitionPosts.length + 1}`,
                author: getActiveUserName(),
                authorImg: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150',
                target: targetEmp.name,
                badge: badge,
                msg: msg,
                likes: 0,
                commentsCount: 0,
                timestamp: 'Just now'
            };

            db.recognitionPosts.unshift(newRec);
            showToast(`Published recognition for ${targetEmp.name}!`, 'success');
            document.getElementById('rec-message').value = "";
            renderSocialTab();
            logAudit(`Published recognition for ${targetEmp.name}`, 'RECOGNITION_POSTS');
        });

        // Calendar navigation click handlers
        const calPrevBtn = document.getElementById('cal-prev-btn');
        const calNextBtn = document.getElementById('cal-next-btn');
        if (calPrevBtn && calNextBtn) {
            calPrevBtn.addEventListener('click', () => {
                currentCalMonth--;
                if (currentCalMonth < 0) {
                    currentCalMonth = 11;
                    currentCalYear--;
                }
                renderTrainingTab();
            });
            calNextBtn.addEventListener('click', () => {
                currentCalMonth++;
                if (currentCalMonth > 11) {
                    currentCalMonth = 0;
                    currentCalYear++;
                }
                renderTrainingTab();
            });
        }
    }

    // ---------------------------------------------------------
    // API Helpers (Gemini API Integration)
    // ---------------------------------------------------------
    function loadAPIKey() {
        const apiKey = localStorage.getItem('ai_api_key');
        const provider = localStorage.getItem('ai_provider') || 'gemini';
        const model = localStorage.getItem('ai_model_name') || (provider === 'gemini' ? 'gemini-3.5-flash' : 'gpt-4o-mini');
        const baseUrl = localStorage.getItem('ai_base_url') || (provider === 'openai' ? 'https://api.openai.com/v1' : '');

        const input = document.getElementById('ai-api-key');
        const providerSelect = document.getElementById('ai-provider-select');
        const modelInput = document.getElementById('ai-model-name');
        const baseUrlInput = document.getElementById('ai-base-url');
        const baseUrlContainer = document.getElementById('base-url-container');
        const statusBar = document.getElementById('api-status-bar');
        const statusText = document.getElementById('api-status-text');

        if (input) input.value = apiKey || '';
        if (providerSelect) providerSelect.value = provider;
        if (modelInput) modelInput.value = model;
        if (baseUrlInput) baseUrlInput.value = baseUrl;
        if (baseUrlContainer) {
            baseUrlContainer.style.display = provider === 'gemini' ? 'none' : 'block';
        }

        if (statusBar && statusText) {
            if (apiKey) {
                statusBar.className = 'api-status-bar connected';
                statusText.innerHTML = `<i class="fa-solid fa-circle-check"></i> Connected! AI is online using <strong>${model}</strong> via <strong>${provider}</strong>.`;
            } else {
                statusBar.className = 'api-status-bar';
                statusText.innerHTML = `<i class="fa-solid fa-circle-info"></i> AI operates in local Demo Mode. Set key to enable Gemini.`;
            }
        }
    }

    async function testAIConnection(key, provider, model, baseUrl) {
        if (provider === 'gemini') {
            const versions = ['v1', 'v1beta'];
            let lastError = '';
            
            for (const version of versions) {
                try {
                    const response = await fetch(`https://generativelanguage.googleapis.com/${version}/models/${model}:generateContent?key=${key}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            contents: [{
                                parts: [{
                                    text: 'Reply in exactly one sentence: "AI Connection verified successfully."'
                                }]
                            }]
                        })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.candidates && data.candidates[0].content.parts[0].text) {
                            return { success: true, text: data.candidates[0].content.parts[0].text.trim() };
                        }
                    } else {
                        const errData = await response.json().catch(() => ({}));
                        lastError = errData.error?.message || `HTTP ${response.status}`;
                    }
                } catch (e) {
                    lastError = e.message;
                }
            }
            return { success: false, error: lastError };
        } else {
            // OpenAI / Custom OpenAI-Compatible
            const targetUrl = `${baseUrl.replace(/\/$/, '')}/chat/completions`;
            try {
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${key}`
                    },
                    body: JSON.stringify({
                        model: model,
                        messages: [
                            { role: 'user', content: 'Reply in exactly one sentence: "AI Connection verified successfully."' }
                        ],
                        max_tokens: 30
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.choices && data.choices[0] && data.choices[0].message) {
                        return { success: true, text: data.choices[0].message.content.trim() };
                    }
                } else {
                    const errData = await response.json().catch(() => ({}));
                    return { success: false, error: errData.error?.message || `HTTP ${response.status}` };
                }
            } catch (e) {
                return { success: false, error: e.message };
            }
        }
        return { success: false, error: 'Unknown provider option' };
    }

    async function getGeminiAIResponse(query) {
        const apiKey = localStorage.getItem('ai_api_key');
        if (!apiKey) {
            return null; // Fallback to local simulator
        }
        
        const provider = localStorage.getItem('ai_provider') || 'gemini';
        const model = localStorage.getItem('ai_model_name') || (provider === 'gemini' ? 'gemini-3.5-flash' : 'gpt-4o-mini');
        const baseUrl = localStorage.getItem('ai_base_url') || '';

        const systemInstruction = `You are "HIMS Performance AI Assistant", a built-in clinical NLP service for Hospital Information Management Systems.
        You help analyze performance reviews, JCI clinical and non-clinical KPIs, competency metrics, licensing status, course recommendations, and succession pipelines.
        Always prioritize hospital data privacy (no PHI, strictly staff stats).
        Keep your answers structured, professional, and action-oriented.
        Use HTML formatting (<h4>, <p>, <ul>, <li>, <strong>, <table class="hims-table">) for readability in the chat drawer.
        You support English, Tagalog, and Taglish/Filipino.
        
        Here is the live HIMS database context to base your answers on:
        - Employees: ${JSON.stringify(db.employees)}
        - Performance Reviews: ${JSON.stringify(db.performanceReviews)}
        - Competencies: ${JSON.stringify(db.competencies)}
        - Competency Assessments: ${JSON.stringify(db.competencyAssessments)}
        - Courses: ${JSON.stringify(db.courses)}
        - Training Sessions: ${JSON.stringify(db.trainingSessions)}
        - Succession Plans: ${JSON.stringify(db.successionPlans)}
        - Social Recognition Wall: ${JSON.stringify(db.recognitionPosts)}`;

        if (provider === 'gemini') {
            const versions = ['v1', 'v1beta'];
            for (const version of versions) {
                try {
                    const requestBody = {
                        contents: [{
                            parts: [{
                                text: `User query: "${query}"`
                            }]
                        }]
                    };
                    
                    requestBody.systemInstruction = {
                        parts: [{
                            text: systemInstruction
                        }]
                    };

                    const response = await fetch(`https://generativelanguage.googleapis.com/${version}/models/${model}:generateContent?key=${apiKey}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(requestBody)
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.candidates && data.candidates[0].content.parts[0].text) {
                            return data.candidates[0].content.parts[0].text;
                        }
                    } else {
                        console.warn(`Gemini call failed on ${version} for model ${model}. Status: ${response.status}`);
                    }
                } catch (err) {
                    console.error(`Gemini call error on ${version} for model ${model}:`, err);
                }
            }
        } else {
            // OpenAI / Custom OpenAI-Compatible
            const targetUrl = `${baseUrl.replace(/\/$/, '')}/chat/completions`;
            try {
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${apiKey}`
                    },
                    body: JSON.stringify({
                        model: model,
                        messages: [
                            { role: 'system', content: systemInstruction },
                            { role: 'user', content: query }
                        ]
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.choices && data.choices[0] && data.choices[0].message) {
                        return data.choices[0].message.content;
                    }
                } else {
                    console.error(`OpenAI-compatible call failed. Status: ${response.status}`);
                }
            } catch (err) {
                console.error(`OpenAI-compatible fetch error:`, err);
            }
        }
        return null;
    }

    // Helper functions for user credentials
    function getActiveUserName() {
        if (loggedInUser) {
            const matchingEmp = db.employees.find(e => e.id === loggedInUser.employeeId);
            return matchingEmp ? matchingEmp.name : loggedInUser.name || 'Unknown User';
        }
        return 'Not Logged In';
    }

    function getActiveUserProfileImg() {
        if (loggedInUser) {
            const matchingEmp = db.employees.find(e => e.id === loggedInUser.employeeId);
            return matchingEmp ? matchingEmp.img : 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=150';
        }
        return 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=150';
    }

    function getActiveUserTitle() {
        const roleTitles = {
            'admin': 'Hospital Administrator',
            'hr': 'HR Admin',
            'dept_head': 'Department Head',
            'supervisor': 'Supervisor',
            'training_officer': 'Training Officer',
            'employee': 'Employee (Nurse/Staff)'
        };
        return roleTitles[currentRole] || 'Employee';
    }

    function updateRoleState() {
        // Change profile visualization in footer
        profileImg.src = getActiveUserProfileImg();
        profileName.innerText = getActiveUserName();
        profileRole.innerText = getActiveUserTitle();

        // Update header active user badge
        const badgeText = document.getElementById('active-user-badge-text');
        if (badgeText) {
            badgeText.textContent = `${getActiveUserName()} — ${getActiveUserTitle()}`;
        }

        // Update system dashboard descriptions
        viewDescription.innerText = `Role view context: ${getActiveUserTitle()}. Subsystem authorizations are configured.`;
        
        // Populate standard dashboards elements based on Role authorizations
        renderNotifications();

        // RBAC: Show/Hide subsystem navigation tabs
        const sidebarItems = document.querySelectorAll('.nav-item');
        sidebarItems.forEach(item => {
            const tabName = item.getAttribute('data-tab');
            if (tabName === 'succession') {
                // Restricted to Admin, HR, Department Head
                item.style.display = ['admin', 'hr', 'dept_head'].includes(currentRole) ? 'flex' : 'none';
            }
            if (tabName === 'users') {
                // Only Admin and HR can see User Accounts
                item.style.display = ['admin', 'hr'].includes(currentRole) ? 'flex' : 'none';
            }
        });

        // RBAC: Handle direct access redirect if current tab is restricted
        if (activeTab === 'succession' && !['admin', 'hr', 'dept_head'].includes(currentRole)) {
            activeTab = 'dashboard';
            sidebarItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-tab') === 'dashboard') {
                    item.classList.add('active');
                }
            });
            renderTab('dashboard');
            showToast('Succession planning is restricted to Administrators and HR.', 'warning');
        }
        if (activeTab === 'users' && !['admin', 'hr'].includes(currentRole)) {
            activeTab = 'dashboard';
            sidebarItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-tab') === 'dashboard') item.classList.add('active');
            });
            renderTab('dashboard');
            showToast('User Accounts is restricted to Administrators and HR.', 'warning');
        }

        // RBAC: Toggle dashboard-level Action buttons visibility
        const evalBtn = document.getElementById('btn-create-evaluation');
        const compBtn = document.getElementById('btn-assess-competency');
        const trainBtn = document.getElementById('btn-schedule-session');
        const succBtn = document.getElementById('btn-create-succession-plan');
        const auditBtnEl = document.getElementById('audit-trail-btn');
        const createUserBtn = document.getElementById('btn-create-user-modal-trigger');

        if (evalBtn) evalBtn.style.display = ['admin', 'hr', 'dept_head', 'supervisor'].includes(currentRole) ? 'inline-flex' : 'none';
        if (compBtn) compBtn.style.display = ['admin', 'hr', 'dept_head', 'supervisor', 'training_officer'].includes(currentRole) ? 'inline-flex' : 'none';
        if (trainBtn) trainBtn.style.display = ['admin', 'hr', 'training_officer'].includes(currentRole) ? 'inline-flex' : 'none';
        if (succBtn) succBtn.style.display = ['admin', 'hr'].includes(currentRole) ? 'inline-flex' : 'none';
        if (auditBtnEl) auditBtnEl.style.display = ['admin', 'hr'].includes(currentRole) ? 'inline-flex' : 'none';
        if (createUserBtn) createUserBtn.style.display = ['admin', 'hr'].includes(currentRole) ? 'inline-flex' : 'none';
    }

    function populateEmployeeDropdowns() {
        const evalSelect = document.getElementById('eval-employee');
        const recSelect = document.getElementById('rec-target-user');
        
        evalSelect.innerHTML = "";
        recSelect.innerHTML = "";

        db.employees.forEach(emp => {
            // Dropdowns
            const opt1 = document.createElement('option');
            opt1.value = emp.id;
            opt1.textContent = `${emp.name} (${emp.title})`;
            evalSelect.appendChild(opt1);

            const opt2 = document.createElement('option');
            opt2.value = emp.id;
            opt2.textContent = `${emp.name} - ${emp.dept}`;
            recSelect.appendChild(opt2);
        });
    }

    // ---------------------------------------------------------
    // 4.5 Authentication & User Account Management
    // ---------------------------------------------------------
    async function hashPassword(password) {
        const msgBuffer = new TextEncoder().encode(password);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function performLogin(username, password) {
        const submitBtn = document.getElementById('btn-login-submit');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...';

        // Simulate network delay for realism
        await new Promise(r => setTimeout(r, 600));

        const inputHash = await hashPassword(password);
        const userRecord = db.users.find(u => u.username.toLowerCase() === username.toLowerCase() && u.passwordHash === inputHash);

        if (userRecord) {
            loggedInUser = userRecord;
            currentRole = userRecord.role;
            localStorage.setItem('hims_logged_in_user', JSON.stringify(userRecord));

            document.getElementById('login-screen').classList.add('layout-hidden');
            document.querySelector('.hims-layout').classList.remove('layout-hidden');

            updateRoleState();
            renderTab('dashboard');
            setupAI();
            populateEmployeeDropdowns();

            showToast(`Welcome back, ${userRecord.name}! Logged in as ${getActiveUserTitle()}.`, 'success');
            logAudit(`User login: ${userRecord.username} (${userRecord.role})`, 'USER_ACCOUNTS');
        } else {
            showToast('Authentication failed. Invalid username or password.', 'danger');
        }

        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-key"></i> Authenticate and Login';
    }

    function logout() {
        logAudit(`User logout: ${loggedInUser ? loggedInUser.username : 'unknown'}`, 'USER_ACCOUNTS');
        loggedInUser = null;
        currentRole = null;
        localStorage.removeItem('hims_logged_in_user');

        document.getElementById('login-screen').classList.remove('layout-hidden');
        document.querySelector('.hims-layout').classList.add('layout-hidden');

        // Clear form
        const loginForm = document.getElementById('login-form');
        if (loginForm) loginForm.reset();

        showToast('You have been securely logged out.', 'info');
    }

    function renderUsersTab() {
        const tbody = document.getElementById('user-accounts-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        db.users.forEach(user => {
            const emp = db.employees.find(e => e.id === user.employeeId);
            const roleTitles = {
                'admin': 'Super Admin (Hospital Administrator)',
                'hr': 'Admin (HR)',
                'dept_head': 'Department Head',
                'supervisor': 'Supervisor',
                'training_officer': 'Training Officer',
                'employee': 'Employee (Nurse/Staff)'
            };
            
            const deptText = emp ? `${emp.dept}` : 'N/A';
            const titleText = emp ? `${emp.title}` : 'N/A';
            const licenseText = emp && emp.license ? `${emp.license} (exp: ${emp.licenseExpiry || 'N/A'})` : 'None / N/A';

            // Don't show delete button for the primary 'admin' account to prevent lockouts
            const isPrimaryAdmin = user.username === 'admin';
            const deleteBtnHtml = isPrimaryAdmin 
                ? `<span style="font-size:11px;color:var(--text-muted);"><i class="fa-solid fa-lock"></i> Protected</span>`
                : `<button class="hims-btn btn-secondary btn-sm btn-delete-user" data-emp-id="${user.employeeId}" data-username="${user.username}" style="color:var(--color-danger);border-color:var(--color-danger);padding:4px 8px;font-size:11px;"><i class="fa-solid fa-trash"></i> Delete</button>`;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${user.name}</strong></td>
                <td>
                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);">${titleText}</div>
                    <div style="font-size:11px;color:var(--text-muted);">${deptText}</div>
                </td>
                <td><code>${user.username}</code></td>
                <td><span class="status-badge badge-${user.role === 'admin' ? 'danger' : user.role === 'hr' ? 'warning' : 'active'}">${roleTitles[user.role] || user.role}</span></td>
                <td><span class="hash-cell" title="${user.passwordHash}">${user.passwordHash.substring(0, 16)}…</span></td>
                <td style="font-size:11.5px;color:var(--text-primary);">${licenseText}</td>
                <td>${deleteBtnHtml}</td>
            `;
            tbody.appendChild(row);
        });

        // Delete User Action Handler
        const deleteButtons = tbody.querySelectorAll('.btn-delete-user');
        deleteButtons.forEach(btn => {
            btn.onclick = () => {
                const empId = btn.getAttribute('data-emp-id');
                const username = btn.getAttribute('data-username');
                
                if (confirm(`Are you sure you want to permanently delete staff member "${username}" and all their associated records from the hospital database?`)) {
                    // Delete from db.employees
                    db.employees = db.employees.filter(e => e.id !== empId);
                    // Delete from db.users
                    db.users = db.users.filter(u => u.username !== username);
                    
                    // Persist changes
                    saveDbToStorage();
                    
                    showToast(`Staff member "${username}" deleted successfully.`, 'success');
                    logAudit(`Deleted staff profile: ${username}`, 'USER_ACCOUNTS');
                    
                    // Check if they deleted themselves
                    if (loggedInUser && loggedInUser.username === username) {
                        logout();
                    } else {
                        renderUsersTab();
                        populateEmployeeDropdowns();
                    }
                }
            };
        });

        // Create User Modal trigger (no select population needed since inputs are direct text)
        const createBtn = document.getElementById('btn-create-user-modal-trigger');
        if (createBtn) {
            createBtn.onclick = () => {
                const modal = document.getElementById('create-user-modal');
                if (modal) {
                    modal.classList.add('modal-open');
                }
            };
        }

        // Create User Form submit
        const createForm = document.getElementById('create-user-form');
        if (createForm) {
            createForm.onsubmit = async (e) => {
                e.preventDefault();
                const fullname = document.getElementById('new-user-fullname').value.trim();
                const title = document.getElementById('new-user-title').value.trim();
                const dept = document.getElementById('new-user-dept').value;
                const license = document.getElementById('new-user-license').value.trim() || null;
                const licenseExpiry = document.getElementById('new-user-license-expiry').value || null;
                
                const username = document.getElementById('new-user-username').value.trim();
                const password = document.getElementById('new-user-password').value;
                const role = document.getElementById('new-user-role').value;

                if (!fullname || !username || !password) {
                    showToast('Full name, username, and password are required.', 'warning');
                    return;
                }

                // Check duplicate username
                if (db.users.some(u => u.username.toLowerCase() === username.toLowerCase())) {
                    showToast(`Username "${username}" already exists in the system.`, 'danger');
                    return;
                }

                // Create unique Employee ID
                const nextNum = db.employees.length > 0 ? Math.max(...db.employees.map(e => parseInt(e.id.split('-')[1]) || 0)) + 1 : 1;
                const empId = `EMP-${nextNum.toString().padStart(3, '0')}`;

                // Add to db.employees
                db.employees.push({
                    id: empId,
                    name: fullname,
                    title: title,
                    dept: dept,
                    role: role,
                    img: 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=150', // placeholder doc avatar
                    license: license,
                    licenseExpiry: licenseExpiry,
                    status: 'Active'
                });

                // Add to db.users
                const pwHash = await hashPassword(password);
                db.users.push({
                    username: username,
                    passwordHash: pwHash,
                    role: role,
                    employeeId: empId,
                    name: fullname
                });

                // Persist changes
                saveDbToStorage();

                showToast(`Staff profile for "${fullname}" successfully registered with encrypted credentials.`, 'success');
                logAudit(`Created staff profile: ${username} (${role}) linked to ${empId}`, 'USER_ACCOUNTS');

                document.getElementById('create-user-modal').classList.remove('modal-open');
                createForm.reset();
                renderUsersTab();
                populateEmployeeDropdowns();
            };
        }
    }

    // ---------------------------------------------------------
    // 5. System Routing and Renders
    // ---------------------------------------------------------
    function renderTab(tab) {
        tabPanels.forEach(panel => panel.classList.remove('active'));
        const targetPanel = document.getElementById(`tab-${tab}`);
        if (targetPanel) {
            targetPanel.classList.add('active');
        }

        // Title update
        const tabNames = {
            dashboard: 'Hospital Executive Dashboard',
            performance: 'Performance & Reviews Appraisal System',
            competency: 'JCI Competency & Licensure Monitor',
            learning: 'HIMS Learning Management Catalog (LMS)',
            training: 'Clinical Training Logistics Calendar',
            succession: 'Leadership Pipelines & Succession Mapping',
            social: 'Hospital Appreciation & Social Wall',
            users: 'User Accounts & Security Management',
            documentation: 'HIMS Subsystem Specifications'
        };
        viewTitle.innerText = tabNames[tab] || 'System Module';

        // Render functions per tab
        switch(tab) {
            case 'dashboard':
                renderDashboardTab();
                break;
            case 'performance':
                renderPerformanceTab();
                break;
            case 'competency':
                renderCompetencyTab();
                break;
            case 'learning':
                renderLearningTab();
                break;
            case 'training':
                renderTrainingTab();
                break;
            case 'succession':
                renderSuccessionTab();
                break;
            case 'social':
                renderSocialTab();
                break;
            case 'users':
                renderUsersTab();
                break;
            case 'documentation':
                renderDocumentationTab();
                break;
        }
    }

    // 5.1 Tab Render: Dashboard
    function renderDashboardTab() {
        const kpiContainer = document.getElementById('dashboard-kpis');
        kpiContainer.innerHTML = "";

        // Render custom dashboards cards depending on role context (RBAC demo)
        let cards = [];
        if (currentRole === 'employee') {
            cards = [
                { title: 'My Active Courses', val: '2 Modules', trend: 'Next: Basic Life Support', trendUp: true, type: 'clinical', icon: 'fa-graduation-cap' },
                { title: 'My Competency Gaps', val: '2 Areas Flagged', trend: 'ICU Ventilatory care deficiency', trendUp: false, type: 'compliance', icon: 'fa-triangle-exclamation' },
                { title: 'My Overall Rating', val: '4.2 / 5.0', trend: 'Above hospital average', trendUp: true, type: 'learning', icon: 'fa-star' },
                { title: 'Accumulated CPD Hours', val: '27 Hours', trend: 'Needed for PRC renewal: 45', trendUp: true, type: 'succession', icon: 'fa-id-card' }
            ];
        } else if (currentRole === 'training_officer') {
            cards = [
                { title: 'Active LMS Courses', val: '3 Courses', trend: 'JCI compliant units', trendUp: true, type: 'clinical', icon: 'fa-book' },
                { title: 'Scheduled Sessions', val: '2 Workshops', trend: 'Next: July 12 Auditorium A', trendUp: true, type: 'compliance', icon: 'fa-calendar-check' },
                { title: 'Total Registered Staff', val: '50 Registrations', trend: '92% attendance average', trendUp: true, type: 'learning', icon: 'fa-users' },
                { title: 'CPD Points Verified', val: '320 Hours', trend: 'Assessed this quarter', trendUp: true, type: 'succession', icon: 'fa-clock' }
            ];
        } else {
            // Admin, HR, Department Head
            cards = [
                { title: 'Pending Staff Reviews', val: '2 Reviews', trend: 'Action required this cycle', trendUp: false, type: 'clinical', icon: 'fa-clipboard' },
                { title: 'Critical License Expiring', val: '3 Expiring', trend: 'PRC expiration warning', trendUp: false, type: 'compliance', icon: 'fa-id-card' },
                { title: 'Department Gaps', val: '2 Departments', trend: 'Advanced Vent care deficient', trendUp: false, type: 'learning', icon: 'fa-triangle-exclamation' },
                { title: 'Critical Succession Risks', val: '3 Roles', trend: 'No successors approved', trendUp: false, type: 'succession', icon: 'fa-sitemap' }
            ];
        }

        cards.forEach(c => {
            kpiContainer.innerHTML += `
                <div class="kpi-card ${c.type}">
                    <div class="kpi-info">
                        <h4>${c.title}</h4>
                        <div class="value">${c.val}</div>
                        <div class="trend ${c.trendUp ? 'up' : 'down'}">
                            <i class="fa-solid ${c.trendUp ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}"></i> ${c.trend}
                        </div>
                    </div>
                    <div class="kpi-icon">
                        <i class="fa-solid ${c.icon}"></i>
                    </div>
                </div>
            `;
        });

        // Tasks Actions Builder
        const tasksContainer = document.getElementById('dashboard-tasks');
        tasksContainer.innerHTML = "";
        let listItems = [];

        if (currentRole === 'employee') {
            listItems = [
                { title: 'Complete Course Quiz: Sterile Techniques', meta: 'Due by July 15', state: 'urgent' },
                { title: 'Submit Annual 2026 Self-Evaluation', meta: 'Due in 2 days', state: 'urgent' },
                { title: 'PRC Nursing License Renewal upload', meta: 'Due in 22 days', state: 'pending' }
            ];
        } else if (currentRole === 'training_officer') {
            listItems = [
                { title: 'Register attendance sheet for Infection Control Seminar', meta: 'Session: July 12', state: 'info' },
                { title: 'Audit simulator bookings for Basic Life Support Class', meta: 'Room allocation conflict', state: 'urgent' }
            ];
        } else {
            listItems = [
                { title: 'Sign off Performance Appraisal: Nurse Carlos Diaz', meta: 'Review pending Dept Head signature', state: 'urgent' },
                { title: 'Approve Succession pipeline proposal for Chief of Surgery', meta: 'Proposed: Dr. Sarah Tiongson', state: 'pending' },
                { title: 'Audit PRC license statuses for Surgical staff cohort', meta: '1 key license expires in 4 days', state: 'urgent' }
            ];
        }

        listItems.forEach(t => {
            tasksContainer.innerHTML += `
                <li>
                    <div class="task-info">
                        <span class="task-dot ${t.state}"></span>
                        <div class="task-info-content">
                            <h5>${t.title}</h5>
                            <span>${t.meta}</span>
                        </div>
                    </div>
                    <button class="hims-btn btn-secondary btn-xs" onclick="alert('Demo: Task context navigated.')">Action</button>
                </li>
            `;
        });

        // Department coverage summaries
        const deptTable = document.getElementById('dept-coverage-table');
        deptTable.innerHTML = `
            <tr>
                <td><strong>Nursing</strong></td>
                <td>4 Nurses</td>
                <td><span class="badge-success">Proficient (4.3)</span></td>
                <td>3 / 4 Certs</td>
                <td><span class="badge-accent">88%</span></td>
            </tr>
            <tr>
                <td><strong>Emergency Room</strong></td>
                <td>1 Nurse, 1 Consultant</td>
                <td><span class="badge-warning">Minor Gap (3.8)</span></td>
                <td>1 / 2 Certs</td>
                <td><span class="badge-warning">65%</span></td>
            </tr>
            <tr>
                <td><strong>Surgery</strong></td>
                <td>1 Chief Surgeon</td>
                <td><span class="badge-success">Expert (4.8)</span></td>
                <td>1 / 1 Certs</td>
                <td><span class="badge-accent">100%</span></td>
            </tr>
        `;

        // Executive AI Insights Text block
        const aiSummary = document.getElementById('dashboard-ai-summary');
        aiSummary.innerHTML = `
            <div style="font-size:12.5px; line-height:1.5;">
                <p><strong>Clinical Competence Status:</strong> ICU cohort competency metrics indicate a gap in <strong>Advanced Vent Support</strong> (-1.6 points from target JCI requirement). Action recommended.</p>
                <p class="mt-5"><strong>Licensure Alerts:</strong> 1 clinical license (Dr. Albert Lim) expires in 4 days. HR renewal triggers have been activated.</p>
                <p class="mt-5" style="color:var(--color-ai);"><i class="fa-solid fa-sparkles"></i> <em>"ICU staff competency gaps should be resolved through the upcoming Advanced CPR module scheduled on July 20."</em></p>
            </div>
        `;

        // Graph Builder
        setTimeout(() => {
            const ctx = document.getElementById('organization-chart');
            if (!ctx) return;

            if (myChart) {
                myChart.destroy();
            }

            // Organization graph showing department clinical index values
            myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Nursing', 'ER', 'Surgery', 'Pediatrics', 'Admin'],
                    datasets: [{
                        label: 'Average Performance Score (1.0 - 5.0 scale)',
                        data: [4.2, 3.8, 4.8, 3.5, 3.4],
                        backgroundColor: 'rgba(13, 148, 136, 0.65)',
                        borderColor: 'rgba(13, 148, 136, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5
                        }
                    }
                }
            });
        }, 100);
    }

    // 5.2 Tab Render: Performance
    function renderPerformanceTab() {
        const tableBody = document.getElementById('perf-table-body');
        tableBody.innerHTML = "";

        // Handle search and filters
        const searchVal = document.getElementById('perf-search').value.toLowerCase();
        const deptVal = document.getElementById('perf-dept-filter').value;

        // Populate table rows
        db.performanceReviews.forEach(rev => {
            const emp = db.employees.find(e => e.id === rev.employeeId);
            if (!emp) return;

            // Filters
            if (searchVal && !rev.employeeName.toLowerCase().includes(searchVal) && !rev.reviewer.toLowerCase().includes(searchVal)) {
                return;
            }
            if (deptVal && emp.dept !== deptVal) {
                return;
            }

            // Render status pills
            let statusPill = "";
            if (rev.status === 'Approved') {
                statusPill = `<span class="badge-success">Approved</span>`;
            } else {
                statusPill = `<span class="badge-warning">Pending Approval</span>`;
            }

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="table-employee-cell">
                        <img src="${emp.img}" alt="Staff Pic">
                        <div class="employee-cell-meta">
                            <span class="name">${rev.employeeName}</span>
                            <span class="sub">${emp.title}</span>
                        </div>
                    </div>
                </td>
                <td>${emp.dept}</td>
                <td>${rev.cycle}</td>
                <td><strong>${rev.overallScore} / 5.0</strong></td>
                <td>${statusPill}</td>
                <td><span class="badge-ai" style="cursor:pointer;" onclick="viewDetailedFeedback('${rev.id}')"><i class="fa-solid fa-brain"></i> Tone Verified</span></td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="hims-btn btn-secondary btn-xs" onclick="viewDetailedFeedback('${rev.id}')">View</button>
                        ${(['admin', 'hr', 'dept_head'].includes(currentRole)) && rev.status === 'Pending Approval' ? 
                            `<button class="hims-btn btn-primary btn-xs" onclick="approveReview('${rev.id}')">Approve</button>` : ''}
                    </div>
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Set search event listener once
        if (!window.perfSearchListenerBound) {
            document.getElementById('perf-search').addEventListener('input', renderPerformanceTab);
            document.getElementById('perf-dept-filter').addEventListener('change', renderPerformanceTab);
            window.perfSearchListenerBound = true;
        }
    }

    // Global action shortcuts
    window.viewDetailedFeedback = function(id) {
        const rev = db.performanceReviews.find(r => r.id === id);
        if (rev) {
            const emp = db.employees.find(e => e.id === rev.employeeId);
            const content = document.getElementById('review-detail-content');
            if (content) {
                content.innerHTML = `
                    <div class="review-detail-header">
                        <div class="review-detail-user">
                            <img src="${emp ? emp.img : 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=150'}" alt="Staff Pic">
                            <div>
                                <h3 style="margin:0;font-size:15px;font-weight:700;">${rev.employeeName}</h3>
                                <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">${emp ? emp.title : 'Staff Nurse'} | ${emp ? emp.dept : 'Nursing'}</p>
                            </div>
                        </div>
                        <span class="badge-accent">${rev.cycle}</span>
                    </div>
                    <div class="review-detail-scores">
                        <div class="score-box overall">
                            <div class="score-label">Overall Score</div>
                            <div class="score-val" style="color:var(--primary-color);">${rev.overallScore} / 5.0</div>
                        </div>
                        <div class="score-box">
                            <div class="score-label">Self Rating</div>
                            <div class="score-val">${rev.selfRating || 'N/A'}</div>
                        </div>
                        <div class="score-box">
                            <div class="score-label">Supervisor</div>
                            <div class="score-val">${rev.supervisorRating || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="review-detail-text">
                        <label>Strengths & Positive Feedback</label>
                        <p>${rev.strengths || 'No strengths recorded.'}</p>
                    </div>
                    <div class="review-detail-text">
                        <label>Development Opportunities & Goals</label>
                        <p>${rev.weaknesses || 'No improvement opportunities recorded.'}</p>
                    </div>
                    <div class="review-detail-text">
                        <label>Compliance & Audit Log Remarks</label>
                        <p style="font-size:11.5px;font-family:monospace;background:#f1f5f9;color:var(--text-muted);"><i class="fa-solid fa-brain" style="color:var(--color-ai)"></i> ${rev.auditNotes || 'Tone audited.'}</p>
                    </div>
                `;
                document.getElementById('review-detail-modal').classList.add('modal-open');
                logAudit(`Viewed detailed feedback for review: ${rev.id}`, 'PERFORMANCE_REVIEWS');
            }
        }
    };

    window.approveReview = function(id) {
        const rev = db.performanceReviews.find(r => r.id === id);
        if (rev) {
            rev.status = 'Approved';
            showToast(`Approved Performance Evaluation review ${id}`, 'success');
            renderPerformanceTab();
            logAudit(`Approved performance appraisal ${id} for ${rev.employeeName}`, 'PERFORMANCE_REVIEWS');
        }
    };

    // 5.3 Tab Render: Competency
    function renderCompetencyTab() {
        const matrixSelect = document.getElementById('comp-role-select').value;
        const heatmap = document.getElementById('skills-heatmap');
        heatmap.innerHTML = "";

        // Heatmap rendering structure
        // Differentiate categories
        let skills = db.competencies;
        let evaluatedStaff = db.competencyAssessments;

        // Render matrix rows
        evaluatedStaff.forEach(staff => {
            const emp = db.employees.find(e => e.id === staff.employeeId);
            if (!emp) return;

            // Select display filters
            if (matrixSelect === 'Nurse' && !emp.title.includes('Nurse')) return;
            if (matrixSelect === 'Doctor' && !emp.title.includes('Doctor')) return;
            if (matrixSelect === 'Admin' && emp.title.includes('Nurse')) return;

            const row = document.createElement('div');
            row.className = 'heatmap-row';
            row.innerHTML = `
                <div class="role-label">${staff.employeeName}</div>
                <div class="heatmap-cells">
                    <div class="heatmap-cell ${getCellLevel(staff.c1, 4)}">
                        <span class="score">${staff.c1}</span>
                        <span class="skill-name">Infection</span>
                    </div>
                    <div class="heatmap-cell ${getCellLevel(staff.c2, 5)}">
                        <span class="score">${staff.c2}</span>
                        <span class="skill-name">Life Support</span>
                    </div>
                    <div class="heatmap-cell ${getCellLevel(staff.c3, 4)}">
                        <span class="score">${staff.c3}</span>
                        <span class="skill-name">Comm</span>
                    </div>
                    <div class="heatmap-cell ${getCellLevel(staff.c4, 5)}">
                        <span class="score">${staff.c4}</span>
                        <span class="skill-name">Vent</span>
                    </div>
                </div>
            `;
            heatmap.appendChild(row);
        });

        // Licensure expiry warning list
        const licList = document.getElementById('licensures-list-body');
        licList.innerHTML = "";
        let criticalLicenseCount = 0;

        db.employees.forEach(emp => {
            if (!emp.license) return;

            // Calculate days left to expiry
            const expDate = new Date(emp.licenseExpiry);
            const today = new Date('2026-07-06');
            const diffTime = expDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            let statusClass = "active";
            let warningText = "";

            if (diffDays <= 0) {
                statusClass = "expired";
                warningText = `<span class="badge-danger">EXPIRED</span>`;
                criticalLicenseCount++;
            } else if (diffDays <= 30) {
                statusClass = "warning";
                warningText = `<span class="badge-warning">Expires in ${diffDays} days</span>`;
                criticalLicenseCount++;
            } else {
                statusClass = "active";
                warningText = `<span class="badge-success">Active</span>`;
            }

            licList.innerHTML += `
                <div class="licensure-item ${statusClass}">
                    <div class="licensure-meta">
                        <h5>${emp.name}</h5>
                        <p>${emp.title} | ${emp.license}</p>
                    </div>
                    <div>${warningText}</div>
                </div>
            `;
        });

        document.getElementById('expiring-license-count').innerText = `${criticalLicenseCount} Expiring`;

        // Competency gaps checklist calculations
        const gapsBody = document.getElementById('competency-gap-body');
        gapsBody.innerHTML = "";

        db.competencyAssessments.forEach(staff => {
            const emp = db.employees.find(e => e.id === staff.employeeId);
            if (!emp) return;

            // Gaps
            const g1 = staff.c1 - 4;
            const g2 = staff.c2 - 5;
            const g3 = staff.c3 - 4;
            const g4 = staff.c4 - 5;

            const formatGap = (g) => {
                if (g < 0) return `<span style="color:var(--color-danger); font-weight:700;">${g}</span>`;
                return `<span style="color:var(--color-success); font-weight:700;">+${g}</span>`;
            };

            gapsBody.innerHTML += `
                <tr>
                    <td><strong>${staff.employeeName}</strong></td>
                    <td><code>${emp.license || 'N/A'}</code></td>
                    <td>${staff.c1} (${formatGap(g1)})</td>
                    <td>${staff.c2} (${formatGap(g2)})</td>
                    <td>${staff.c3} (${formatGap(g3)})</td>
                    <td>${staff.c4} (${formatGap(g4)})</td>
                    <td>
                        ${(g1 < 0 || g2 < 0 || g3 < 0 || g4 < 0) ? 
                        `<span class="badge-warning">Gaps Identified</span>` : `<span class="badge-success">Fully Proficient</span>`}
                    </td>
                </tr>
            `;
        });

        // Change select action
        if (!window.compRoleSelectBound) {
            document.getElementById('comp-role-select').addEventListener('change', renderCompetencyTab);
            window.compRoleSelectBound = true;
        }
    }

    function getCellLevel(score, req) {
        const gap = score - req;
        if (gap < -1.5) return 'level-critical';
        if (gap < 0) return 'level-low';
        if (gap < 1.0) return 'level-modest';
        return 'level-proficient';
    }

    // 5.4 Tab Render: Learning Catalog
    function renderLearningTab() {
        const completedCPD = db.courses
            .filter(c => c.completed)
            .reduce((sum, c) => sum + c.cpdHours, 0);
        
        const counterEl = document.getElementById('cpd-total-counter');
        if (counterEl) {
            const label = currentRole === 'employee' ? 'My Completed CPD Accumulated' : 'Total Department CPD Accumulated';
            counterEl.innerHTML = `${label}: <strong>${completedCPD} hrs</strong>`;
        }

        const grid = document.getElementById('courses-grid-body');
        grid.innerHTML = "";

        const searchVal = document.getElementById('lms-search').value.toLowerCase();
        const catVal = document.getElementById('lms-cat-filter').value;

        db.courses.forEach(c => {
            if (searchVal && !c.title.toLowerCase().includes(searchVal) && !c.desc.toLowerCase().includes(searchVal)) return;
            if (catVal && c.category !== catVal) return;

            // Class tags
            let bannerClass = "clinical";
            if (c.category === 'Compliance') bannerClass = "compliance";
            if (c.category === 'Safety') bannerClass = "safety";

            grid.innerHTML += `
                <div class="course-card">
                    <div>
                        <div class="course-banner ${bannerClass}">
                            <span class="course-category-tag">${c.category}</span>
                            <h4>${c.title}</h4>
                        </div>
                        <div class="course-body">
                            <p class="course-desc">${c.desc}</p>
                            <div class="course-meta-pills">
                                <span class="meta-pill"><i class="fa-solid fa-clock"></i> ${c.cpdHours} CPD Hours</span>
                                <span class="meta-pill"><i class="fa-solid fa-user-graduate"></i> ${c.completionRate}% Pass</span>
                            </div>
                            <div class="course-progress">
                                <div class="progress-info">
                                    <span>Progress</span>
                                    <span>${c.progress}%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: ${c.progress}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="course-footer">
                        ${c.completed ? 
                            `<button class="hims-btn btn-secondary btn-sm w-full" disabled><i class="fa-solid fa-circle-check"></i> Course Completed</button>` :
                            `<button class="hims-btn btn-primary btn-sm w-full" onclick="launchQuizModal('${c.id}')"><i class="fa-solid fa-graduation-cap"></i> Take Assessment Quiz</button>`
                        }
                    </div>
                </div>
            `;
        });

        // Set search event listener once
        if (!window.lmsSearchListenerBound) {
            document.getElementById('lms-search').addEventListener('input', renderLearningTab);
            document.getElementById('lms-cat-filter').addEventListener('change', renderLearningTab);
            window.lmsSearchListenerBound = true;
        }
    }

    // Launch interactive e-learning assessments quiz
    window.launchQuizModal = function(courseId) {
        const course = db.courses.find(c => c.id === courseId);
        if (!course) return;

        const modal = document.getElementById('quiz-modal');
        document.getElementById('quiz-course-title').innerText = course.title;

        // Custom quizzes renderer
        const quizBody = document.getElementById('quiz-questions-body');
        quizBody.innerHTML = "";

        // Quiz items templates
        const mockQuestions = [
            { q: "What is the standard hand rub friction duration required by World Health Organization (WHO) and JCI standards?", opts: ["5-10 seconds", "20-30 seconds", "60-90 seconds"], correct: 1 },
            { q: "Which class of infectious waste disposal bins should used syringes be discarded into?", opts: ["Black (Non-infectious Dry)", "Yellow (Sharps Container)", "Green (Biodegradable)"], correct: 1 }
        ];

        mockQuestions.forEach((item, index) => {
            quizBody.innerHTML += `
                <div style="margin-bottom:12px;">
                    <p style="font-weight:600; font-size:12.5px;">Q${index + 1}: ${item.q}</p>
                    <div style="display:flex; flex-direction:column; gap:6px; margin-top:6px;">
                        ${item.opts.map((opt, optIndex) => `
                            <label style="font-size:12px; display:flex; align-items:center; gap:6px;">
                                <input type="radio" name="question-${index}" value="${optIndex}" required>
                                ${opt}
                            </label>
                        `).join('')}
                    </div>
                </div>
            `;
        });

        modal.classList.add('modal-open');

        // Capture submit event
        document.getElementById('quiz-questions-form').onsubmit = (e) => {
            e.preventDefault();
            course.progress = 100;
            course.completed = true;
            showToast(`Congratulations! You passed the assessment for '${course.title}' and earned ${course.cpdHours} CPD hours.`, 'success');
            modal.classList.remove('modal-open');
            renderLearningTab();
            logAudit(`Completed LMS course evaluation quiz for ${course.title}`, 'COURSES');
        };
    };

    // 5.5 Tab Render: Training Management
    function renderTrainingTab() {
        const daysContainer = document.getElementById('calendar-days-container');
        daysContainer.innerHTML = "";

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        
        // Update Title text
        const titleEl = document.getElementById('calendar-month-year');
        if (titleEl) {
            titleEl.innerText = `${monthNames[currentCalMonth]} ${currentCalYear}`;
        }

        // Dynamically compute calendar limits
        const totalDays = new Date(currentCalYear, currentCalMonth + 1, 0).getDate();
        const startOffset = new Date(currentCalYear, currentCalMonth, 1).getDay();

        // Render offset placeholders
        for (let i = 0; i < startOffset; i++) {
            const cell = document.createElement('div');
            cell.className = 'calendar-day other-month';
            cell.innerHTML = `<span class="day-number"></span>`;
            daysContainer.appendChild(cell);
        }

        // Format month index for dates mapping
        const monthNumStr = (currentCalMonth + 1) < 10 ? '0' + (currentCalMonth + 1) : (currentCalMonth + 1);

        // Render active days
        for (let day = 1; day <= totalDays; day++) {
            const cell = document.createElement('div');
            const dayString = `${currentCalYear}-${monthNumStr}-${day < 10 ? '0' + day : day}`;
            
            // Check today (context local time is 2026-07-06)
            const isToday = currentCalYear === 2026 && currentCalMonth === 6 && day === 6;
            cell.className = `calendar-day ${isToday ? 'today' : ''}`;
            
            // Highlight scheduled sessions
            const sessionMatch = db.trainingSessions.find(s => s.date === dayString);
            let eventTag = "";
            if (sessionMatch) {
                let catClass = "clinical";
                if (sessionMatch.category === 'compliance') catClass = "compliance";
                if (sessionMatch.category === 'safety') catClass = "safety";
                eventTag = `<span class="calendar-event-tag ${catClass}">${sessionMatch.title}</span>`;
            }

            cell.innerHTML = `
                <span class="day-number">${day}</span>
                <div class="day-events">${eventTag}</div>
            `;

            // Click event details
            cell.addEventListener('click', () => {
                renderSessionLogistics(sessionMatch, dayString);
            });

            daysContainer.appendChild(cell);
        }

        // Trigger default select
        const matchedSessions = db.trainingSessions.filter(s => s.date.startsWith(`${currentCalYear}-${monthNumStr}`));
        if (matchedSessions.length > 0) {
            renderSessionLogistics(matchedSessions[0], matchedSessions[0].date);
        } else {
            renderSessionLogistics(null, `${currentCalYear}-${monthNumStr}-01`);
        }
    }

    function renderSessionLogistics(session, dateString) {
        const sidebar = document.getElementById('session-logistics-body');
        sidebar.innerHTML = "";

        if (!session) {
            sidebar.innerHTML = `
                <div class="session-detail-item">
                    <label>Selected Date</label>
                    <p>${dateString}</p>
                </div>
                <div class="session-detail-item" style="text-align:center; padding:20px 0;">
                    <i class="fa-solid fa-circle-info" style="font-size:24px; color:var(--text-light);"></i>
                    <p style="font-size:12px; margin-top:8px; color:var(--text-muted);">No training sessions planned on this date.</p>
                </div>
            `;
            return;
        }

        // Calculate capacity rate
        const capRate = Math.round((session.registered / session.capacity) * 100);

        sidebar.innerHTML = `
            <div class="session-detail-item">
                <label>Training Session</label>
                <p><strong>${session.title}</strong></p>
            </div>
            <div class="session-detail-item">
                <label>Date & Time</label>
                <p>${session.date} | ${session.time}</p>
            </div>
            <div class="session-detail-item">
                <label>Venue / Location</label>
                <p><i class="fa-solid fa-location-dot"></i> ${session.venue}</p>
            </div>
            <div class="session-detail-item">
                <label>Instructor / Facilitator</label>
                <p>${session.instructor}</p>
            </div>
            <div class="session-detail-item">
                <label>Logistics & Capacity</label>
                <p>${session.registered} / ${session.capacity} slots filled (${capRate}%)</p>
                <div class="session-capacity-bar">
                    <div class="progress-bar-bg" style="height:4px;">
                        <div class="progress-bar-fill" style="width: ${capRate}%; background-color: var(--secondary-color);"></div>
                    </div>
                </div>
            </div>
            <div class="session-detail-item">
                <label>Trainee Feedback Sentiment (AI Summary)</label>
                <p style="font-size:11.5px; line-height:1.4;">
                    ${session.feedback.length > 0 ? 
                        `Avg Rating: <strong>${(session.feedback.reduce((a,b)=>a+b.rating,0)/session.feedback.length).toFixed(1)} / 5</strong><br>` + 
                        `<em>"${session.feedback[0].comment}"</em>` : 
                        'Awaiting feedback collections.'}
                </p>
            </div>
            <div class="session-action-btn-row">
                <button class="hims-btn btn-primary btn-sm w-full" onclick="registerForTraining('${session.id}')"><i class="fa-solid fa-user-plus"></i> Register Staff</button>
            </div>
        `;
    }

    window.registerForTraining = function(sessionId) {
        const session = db.trainingSessions.find(s => s.id === sessionId);
        if (session) {
            if (session.registered >= session.capacity) {
                showToast("Training session is full", "danger");
                return;
            }
            session.registered++;
            showToast(`Registered successfully for '${session.title}'!`, 'success');
            renderTrainingTab();
            logAudit(`Registered new staff to session ${session.title}`, 'TRAINING_SESSIONS');
        }
    };

    // 5.6 Tab Render: Succession Planning
    function renderSuccessionTab() {
        const grid = document.getElementById('nine-box-body');
        grid.innerHTML = "";

        // 9 box matrix details definition
        const boxes = [
            { key: '3-1', title: 'High Potential, Low Perf', candidates: ['Nurse Carlos Diaz'], class: 'solid-talent' },
            { key: '3-2', title: 'High Potential, Med Perf', candidates: [], class: 'solid-talent' },
            { key: '3-3', title: 'Star / High-Flyer', candidates: ['Nurse Clara de Leon'], class: 'high-talent' },
            { key: '2-1', title: 'Solid Performer, Low Perf', candidates: ['John Doe'], class: 'underperform' },
            { key: '2-2', title: 'Key Performer', candidates: [], class: 'solid-talent' },
            { key: '2-3', title: 'High Potential, High Perf', candidates: ['Nurse Maria Santos'], class: 'high-talent' },
            { key: '1-1', title: 'Risk / Underperformer', candidates: [], class: 'underperform' },
            { key: '1-2', title: 'Solid Worker', candidates: [], class: 'solid-talent' },
            { key: '1-3', title: 'High Professional', candidates: [], class: 'solid-talent' }
        ];

        // Label Y
        grid.innerHTML += `<div class="nine-box-label-y">Potential (High -> Low)</div>`;

        // Render grid cells
        boxes.forEach(box => {
            grid.innerHTML += `
                <div class="nine-box-cell ${box.class}">
                    <span class="cell-title">${box.title}</span>
                    <div class="cell-candidates">
                        ${box.candidates.map(c => `<span class="candidate-tag" onclick="alert('Succession Profile: ${c}')">${c}</span>`).join('')}
                    </div>
                </div>
            `;
        });

        // Label X
        grid.innerHTML += `<div class="nine-box-label-x">Performance (Low -> High)</div>`;

        // Succession Risk index
        const riskList = document.getElementById('succession-risk-list');
        riskList.innerHTML = "";

        db.successionPlans.forEach(plan => {
            let riskBadge = "badge-success";
            if (plan.risk === 'High') riskBadge = "badge-danger";
            if (plan.risk === 'Medium') riskBadge = "badge-warning";

            riskList.innerHTML += `
                <div class="critical-position-item">
                    <div class="crit-header">
                        <h5>${plan.position}</h5>
                        <span class="${riskBadge}">${plan.risk} Risk</span>
                    </div>
                    <div class="crit-risk-bar">
                        <span>Current: <strong>${plan.holder}</strong></span>
                        <span>Successor: <strong>${plan.successor} (${plan.readiness})</strong></span>
                    </div>
                </div>
            `;
        });

        // Pipelines table
        const tableBody = document.getElementById('succession-table-body');
        tableBody.innerHTML = "";

        db.successionPlans.forEach(plan => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${plan.position}</strong></td>
                <td>${plan.holder}</td>
                <td>${plan.successor}</td>
                <td><span class="badge-accent">${plan.readiness}</span></td>
                <td><span class="badge-danger">${plan.risk} Risk</span></td>
                <td><span class="badge-info">${plan.status}</span></td>
                <td>
                    ${plan.status === 'Proposed' && (currentRole === 'admin' || currentRole === 'hr') ? 
                        `<button class="hims-btn btn-primary btn-xs" onclick="approveSuccession('${plan.id}')">Approve</button>` : 
                        `<button class="hims-btn btn-secondary btn-xs" disabled>Approved</button>`}
                </td>
            `;
            tableBody.appendChild(row);
        });
    }

    window.approveSuccession = function(planId) {
        const plan = db.successionPlans.find(p => p.id === planId);
        if (plan) {
            plan.status = 'Approved';
            showToast(`Approved Succession Pipeline proposal for ${plan.position}`, 'success');
            renderSuccessionTab();
            logAudit(`Approved succession plan ${planId}`, 'SUCCESSION_PLANS');
        }
    };

    // 5.7 Tab Render: Social Wall
    function renderSocialTab() {
        const feed = document.getElementById('recognition-feed-list');
        feed.innerHTML = "";

        db.recognitionPosts.forEach(post => {
            feed.innerHTML += `
                <div class="recognition-card">
                    <div class="rec-header">
                        <div class="rec-authors">
                            <img src="${post.authorImg}" alt="Author Pic">
                            <div class="rec-author-meta">
                                <h5>${post.author} <i class="fa-solid fa-caret-right" style="color:var(--text-light); margin:0 4px;"></i> <span class="target">${post.target}</span></h5>
                                <p>${post.timestamp} | Badge Awarded</p>
                            </div>
                        </div>
                        <span class="rec-badge-icon" title="${post.badge}">${getBadgeEmoji(post.badge)}</span>
                    </div>
                    <p class="rec-message">"${post.msg}"</p>
                    <div class="rec-footer">
                        <div class="rec-actions">
                            <button class="rec-action-btn" onclick="likePost('${post.id}')"><i class="fa-solid fa-thumbs-up"></i> Like (${post.likes})</button>
                        </div>
                        <span class="badge-accent">${post.badge}</span>
                    </div>
                </div>
            `;
        });

        // Update recognition wall leaderboard statistics
        const leadBody = document.getElementById('leaderboard-body');
        leadBody.innerHTML = `
            <div class="leaderboard-item">
                <span class="leaderboard-rank">1</span>
                <span>Nursing (Pediatrics Ward)</span>
                <strong>42 Badges</strong>
            </div>
            <div class="leaderboard-item">
                <span class="leaderboard-rank">2</span>
                <span>Emergency Room Group</span>
                <strong>28 Badges</strong>
            </div>
            <div class="leaderboard-item">
                <span class="leaderboard-rank">3</span>
                <span>Admin Clerk Staff</span>
                <strong>14 Badges</strong>
            </div>
        `;
    }

    function getBadgeEmoji(badge) {
        if (badge.includes("Compassion")) return "❤️";
        if (badge.includes("Teamwork")) return "🤝";
        if (badge.includes("Innovation")) return "💡";
        if (badge.includes("Excellence")) return "🩺";
        return "🛡️";
    }

    window.likePost = function(postId) {
        const post = db.recognitionPosts.find(p => p.id === postId);
        if (post) {
            post.likes++;
            renderSocialTab();
        }
    };

    // 5.8 Tab Render: Documentation viewer
    function renderDocumentationTab() {
        const container = document.getElementById('docs-content-view');
        // Load content from documentation dynamically using fetch OR embedded string
        // We'll fetch the real file! This makes it exceptionally cool because the browser subagent can check that the files are properly linked.
        fetch('HIMS_SYSTEM_DOCUMENTATION.md')
            .then(res => {
                if (!res.ok) throw new Error('File not found');
                return res.text();
            })
            .then(text => {
                container.innerHTML = parseMarkdown(text);
            })
            .catch(err => {
                container.innerHTML = `<p style="color:var(--color-danger);">Failed to read system documentation file. Ensure the local server is running or check file path. Error: ${err.message}</p>`;
            });
    }

    // Helper Markdown Parser (Simple regex for docs render)
    function parseMarkdown(md) {
        let html = md;
        // Headings
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
        // Bold
        html = html.replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>');
        // Codes
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');
        // Tables
        html = html.replace(/\| (.*) \|/g, function(match, content) {
            const cols = content.split(' | ');
            return `<tr>${cols.map(c => `<td>${c}</td>`).join('')}</tr>`;
        });
        // Wrap tables
        html = html.replace(/(<tr>.*<\/tr>)/g, '<table class="hims-table"><tbody>$1</tbody></table>');
        return html;
    }

    // ---------------------------------------------------------
    // 6. Natural Language Processing (NLP) Assistant Simulator
    // ---------------------------------------------------------
    function setupAI() {
        const drawer = document.getElementById('ai-drawer');
        const trigger = document.getElementById('ai-floating-trigger');
        const closeBtn = document.getElementById('ai-drawer-close');
        const header = document.getElementById('ai-drawer-toggle');
        const queryForm = document.getElementById('ai-query-form');
        const queryInput = document.getElementById('ai-query-input');
        const chatMessages = document.getElementById('ai-chat-messages');

        // Toggle Drawer
        trigger.addEventListener('click', () => {
            drawer.classList.remove('drawer-collapsed');
            trigger.style.display = "none";
        });

        header.addEventListener('click', () => {
            drawer.classList.add('drawer-collapsed');
            trigger.style.display = "flex";
        });

        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            drawer.classList.add('drawer-collapsed');
            trigger.style.display = "flex";
        });

        // Quick query clicks
        document.querySelectorAll('.quick-query-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const query = btn.getAttribute('data-query');
                queryInput.value = query;
                queryForm.dispatchEvent(new Event('submit'));
            });
        });

        // Submit query
        queryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const query = queryInput.value.trim();
            if (!query) return;

            // Render User Message
            appendMessage(query, 'user');
            queryInput.value = "";

            // Show loading placeholder
            const loadingId = 'ai-loading-' + Date.now();
            appendMessage(`<span id="${loadingId}"><i class="fa-solid fa-spinner fa-spin"></i> HIMS Performance AI is thinking...</span>`, 'assistant');

            try {
                // Call Gemini if API key exists
                const realResponse = await getGeminiAIResponse(query);
                const loadingPlaceholder = document.getElementById(loadingId);
                
                if (realResponse) {
                    if (loadingPlaceholder) {
                        loadingPlaceholder.parentElement.innerHTML = parseMarkdown(realResponse);
                    }
                    logAudit(`Queried Gemini AI: "${query.substring(0, 30)}..."`, 'AI_LOGS');
                } else {
                    // Fallback to offline rule-based simulation
                    const offlineResponse = processNLPQuery(query);
                    if (loadingPlaceholder) {
                        loadingPlaceholder.parentElement.innerHTML = offlineResponse;
                    }
                    logAudit(`Queried Offline AI Demo: "${query.substring(0, 30)}..."`, 'AI_LOGS');
                }
            } catch (err) {
                console.error(err);
                const offlineResponse = processNLPQuery(query);
                const loadingPlaceholder = document.getElementById(loadingId);
                if (loadingPlaceholder) {
                    loadingPlaceholder.parentElement.innerHTML = offlineResponse;
                }
            }
        });

        function appendMessage(text, sender) {
            const msgNode = document.createElement('div');
            msgNode.className = `ai-message ${sender}`;
            msgNode.innerHTML = `
                <div class="message-content">
                    ${text}
                </div>
            `;
            chatMessages.appendChild(msgNode);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // Regex-based bilingual NLP matching engine
    function processNLPQuery(query) {
        const lower = query.toLowerCase();

        // Query 1: Summarize performance of nurse Maria Santos
        if (lower.includes('summarize') && lower.includes('performance') && lower.includes('maria')) {
            return `
                <h4>📝 Employee Performance Summary: Nurse Maria Santos</h4>
                <p><strong>Cycle:</strong> Last 12 Months (JCI Alignment Audit)</p>
                <p><strong>Overall Score:</strong> 4.2 / 5.0 (Proficient)</p>
                <ul>
                    <li><strong>Strengths:</strong> Outstanding Bedside patient care compliance, high documentation accuracy (JCI index: 94%).</li>
                    <li><strong>Improvement Areas:</strong> Collaboration in ward administration rosters, critical ventilatory protocol training module.</li>
                </ul>
                <p class="mt-5" style="color:var(--primary-color);"><strong>AI Recommendation:</strong> Enroll in CPR & Advanced Vent protocols on July 20 to clear competency gap (-1.6 points).</p>
            `;
        }

        // Query 2: Show competency gaps for ICU nurses
        if (lower.includes('gaps') && lower.includes('icu') && lower.includes('nurs')) {
            return `
                <h4>🩺 ICU Nursing Competency Gaps:</h4>
                <p>Calculated across 2 Senior ICU Staff members:</p>
                <table class="hims-table mt-5">
                    <thead>
                        <tr><th>Staff</th><th>Competency</th><th>Proficiency</th><th>Gap</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Maria Santos</td><td>Critical Vent Protocol</td><td>3.4 / 5.0</td><td><strong style="color:var(--color-danger);">-1.6</strong></td></tr>
                        <tr><td>Carlos Diaz</td><td>Critical Vent Protocol</td><td>2.5 / 5.0</td><td><strong style="color:var(--color-danger);">-2.5</strong></td></tr>
                        <tr><td>Carlos Diaz</td><td>Patient Communication</td><td>3.0 / 4.0</td><td><strong style="color:var(--color-danger);">-1.0</strong></td></tr>
                    </tbody>
                </table>
                <p class="mt-10" style="color:var(--color-ai);"><strong>Training Suggestion:</strong> Session 'Basic Life Support Hands-on' on July 20 resolves Vent Protocol gaps.</p>
            `;
        }

        // Query 3: Recommend training for low communication scores
        if (lower.includes('recommend') && lower.includes('communicat')) {
            return `
                <h4>💡 Training Recommendations for Bedside Communication Gaps:</h4>
                <p>The following staff scored under standard (4.0) in Patient Bedside Communications:</p>
                <ul>
                    <li><strong>Nurse Maria Santos</strong> (Score: 3.2 | Gap: -0.8)</li>
                    <li><strong>Nurse Carlos Diaz</strong> (Score: 3.0 | Gap: -1.0)</li>
                </ul>
                <p class="mt-5"><strong>Recommended Course Assignment:</strong> <code>CRS-003: Active Patient Care Communication</code> (8 CPD hours, includes JCI patient safety disclosure protocols).</p>
            `;
        }

        // Query 4: Possible successors for Head Nurse position
        if (lower.includes('successor') && lower.includes('head nurse')) {
            return `
                <h4>👑 Succession Mapping: Head Nurse Position</h4>
                <p>Current Holder: <strong>Nurse Clara de Leon</strong> (PRC-NUR-8834)</p>
                <p>Top Identified Candidates in Talent Pool:</p>
                <ol>
                    <li><strong>Nurse Maria Santos</strong> (Readiness: <strong>Ready Immediately</strong> | Potential: High | Performance: High)</li>
                    <li><strong>Nurse Carlos Diaz</strong> (Readiness: <strong>1-2 Years</strong> | Potential: High | Performance: Med)</li>
                </ol>
                <p class="mt-5" style="color:var(--color-success);"><i class="fa-solid fa-shield-check"></i> Maria Santos matches 94% of compliance certifications required for this leadership role.</p>
            `;
        }

        // Query 5: Summarize training feedback from infection control seminar
        if (lower.includes('feedback') && lower.includes('infection')) {
            return `
                <h4>🔍 Feedback Analysis: Infection Control Seminar</h4>
                <p><strong>Session Date:</strong> July 12, 2026 | Instructor: Dr. Rostova</p>
                <p><strong>Average Trainee Score:</strong> 4.7 / 5.0 (High Satisfaction)</p>
                <ul>
                    <li><strong>Common Praise:</strong> "Salamat, napaka-informative at direkta sa punto." (Highly interactive slide presentations)</li>
                    <li><strong>Common Complaints/Issues:</strong> "Good session, but the simulator room was quite cold."</li>
                </ul>
                <p class="mt-5"><strong>AI Log:</strong> Venue coordinator notified. Thermostat settings updated for upcoming workshops.</p>
            `;
        }

        // Query 6: Create recognition message
        if (lower.includes('recognition') && lower.includes('patient care')) {
            return `
                <h4>❤️ AI Generated Recognition Message Suggestion:</h4>
                <p>Category: <strong>Compassion (Kalinga)</strong> Badge</p>
                <div style="background:var(--bg-main); padding:10px; border-radius:6px; border:1px solid var(--border-color); font-style:italic;">
                    "Salamat sa iyong mahusay na bedside manner at malasakit sa ating mga ICU patient kanina kahit medyo understaffed tayo sa ward! Ang iyong kalinga ay huwaran sa buong ward."
                </div>
            `;
        }

        // Query 7: Taglish staff needing training (Ipakita ang mga staff na kailangan ng training)
        if (lower.includes('ipakita') || lower.includes('kailangan') || lower.includes('training this quarter')) {
            return `
                <h4>🇵🇭 HIMS AI: Mga staff na nangangailangan ng training ngayong Quarter:</h4>
                <p>Batay sa skill matrices at lisensya, narito ang listahan:</p>
                <table class="hims-table mt-5">
                    <thead>
                        <tr><th>Pangalan</th><th>Kulang na Skill</th><th>Inirerekomendang Training</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Carlos Diaz</td><td>Critical Vent Protocol</td><td>BLS Simulator Class (July 20)</td></tr>
                        <tr><td>Maria Santos</td><td>Patient Communication</td><td>Active bedside interaction course (CRS-003)</td></tr>
                    </tbody>
                </table>
            `;
        }

        // Generic query recommendation engine
        return `
            <h4>🤖 HIMS Performance Assistant</h4>
            <p>I parsed your prompt: <em>"${query}"</em></p>
            <p>Could you clarify or choose from my core supported commands? I can analyze licensing, calculate JCI competency gaps, recommend training, summarize course completions, or map leadership succession paths.</p>
            <p class="mt-5"><strong>Example:</strong> try asking me <em>"Show competency gaps for ICU nurses"</em> or <em>"Sino ang successors para sa Head Nurse?"</em></p>
        `;
    }

    // ---------------------------------------------------------
    // 7. Dynamic Audits Log & Toast Systems
    // ---------------------------------------------------------
    function renderAuditTrail() {
        auditTableBody.innerHTML = "";
        db.auditTrails.forEach(log => {
            auditTableBody.innerHTML += `
                <tr>
                    <td><code>${new Date(log.timestamp).toLocaleString()}</code></td>
                    <td><strong>${log.user}</strong> <span class="badge-accent">${log.role}</span></td>
                    <td>${log.action}</td>
                    <td><code>${log.resource}</code></td>
                    <td><code>${log.ip}</code></td>
                    <td><small style="color:var(--text-muted);">${log.statusHash}</small></td>
                </tr>
            `;
        });
    }

    function logAudit(action, resource) {
        db.auditTrails.unshift({
            timestamp: new Date().toISOString(),
            user: getActiveUserName(),
            role: currentRole,
            action: action,
            resource: resource,
            ip: '192.168.10.99',
            statusHash: `sha256-${Math.random().toString(36).substring(2, 7)}`
        });
    }

    function renderNotifications() {
        notiList.innerHTML = "";
        notiBadge.innerText = db.notifications.length;

        if (db.notifications.length === 0) {
            notiList.innerHTML = `<li style="text-align:center; color:var(--text-muted);">No new notifications</li>`;
            notiBadge.style.display = "none";
            return;
        } else {
            notiBadge.style.display = "block";
        }

        db.notifications.forEach(n => {
            notiList.innerHTML += `
                <li>
                    <strong>${n.title}</strong>
                    <p style="margin-top:2px; font-size:11px; color:var(--text-muted);">${n.text}</p>
                    <span style="font-size:9px; color:var(--primary-color); float:right;">${n.date}</span>
                    <div style="clear:both;"></div>
                </li>
            `;
        });
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `hims-toast ${type}`;
        
        let icon = "fa-circle-check";
        if (type === 'warning') icon = "fa-triangle-exclamation";
        if (type === 'danger') icon = "fa-circle-xmark";
        if (type === 'info') icon = "fa-circle-info";

        toast.innerHTML = `
            <i class="fa-solid ${icon}"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);

        // Auto remove after 3s
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Execute application bootstrap
    init();
});
