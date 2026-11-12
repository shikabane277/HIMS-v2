<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service & Data Protection Notice — HIMS</title>
    @include('partials.security-protect')
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('partials.app-css')
    <style>
        body {
            background-color: #0f172a;
            color: #334155;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .tos-container {
            max-width: 960px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .tos-header {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
            color: #ffffff;
            padding: 40px 48px;
            position: relative;
        }
        .tos-header h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .tos-header p {
            margin: 0;
            color: #a7f3d0;
            font-size: 14px;
        }
        .tos-nav-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            background: rgba(255,255,255,0.15);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 24px;
            backdrop-filter: blur(4px);
            transition: background 0.2s;
        }
        .tos-nav-back:hover {
            background: rgba(255,255,255,0.25);
            color: #ffffff;
        }
        .tos-body {
            padding: 48px;
            line-height: 1.7;
            font-size: 14.5px;
            color: #334155;
        }
        .tos-section {
            margin-bottom: 36px;
        }
        .tos-section h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 14px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
        }
        .tos-section h2 i {
            color: #059669;
        }
        .tos-section p, .tos-section li {
            color: #475569;
        }
        .tos-table-wrap {
            overflow-x: auto;
            margin: 16px 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .tos-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .tos-table th {
            background: #f8fafc;
            color: #1e293b;
            font-weight: 700;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        .tos-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }
        .tos-table tr:last-child td {
            border-bottom: none;
        }
        .tos-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .tos-badge-enc { background: #dbeafe; color: #1e40af; }
        .tos-badge-hash { background: #fef3c7; color: #92400e; }
        .tos-badge-plain { background: #f1f5f9; color: #475569; }
        .notice-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
            font-size: 13.5px;
            color: #065f46;
        }
        .notice-box-warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .tos-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #64748b;
            flex-wrap: wrap;
            gap: 16px;
        }
    </style>
</head>
<body>

<div class="tos-container">
    <div class="tos-header">
        <a href="{{ route('login') }}" class="tos-nav-back">
            <i class="bi bi-arrow-left"></i> Return to HIMS Login
        </a>
        <h1>Terms of Service &amp; Data Protection Notice</h1>
        <p>Hospital Information Management System (HIMS) — Performance &amp; Development Module</p>
        <p style="margin-top:4px;font-size:12px;opacity:0.85"><i class="bi bi-clock-history"></i> Effective &amp; Last Updated: November 12, 2026</p>
    </div>

    <div class="tos-body">
        <div class="notice-box">
            <strong><i class="bi bi-info-circle-fill"></i> Enterprise Healthcare Scope:</strong>
            This Terms of Service and Data Protection Notice outlines how the Hospital Information Management System (HIMS) collects, uses, encrypts, and retains workforce information strictly for institutional operations in full compliance with the <strong>Philippine Data Privacy Act of 2012 (Republic Act No. 10173)</strong>, <strong>GDPR (Articles 5, 6, 7, 13, 14, 15, 17, 20, 32)</strong>, and healthcare credentialing standards.
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-file-earmark-check"></i> 1. Acceptance of Terms</h2>
            <p>
                By accessing or authenticating into the HIMS Performance &amp; Development platform, you acknowledge that you have read, understood, and agreed to be bound by these Terms of Service. This platform is provisioned by the Hospital Administration solely for authorized healthcare personnel, administrators, and supervisors. Accounts are strictly provisioned by Hospital Administration; self-registration is disabled.
            </p>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-shield-lock"></i> 2. Authentication, Sessions &amp; Security Measures</h2>
            <p>
                To maintain the highest level of confidentiality and patient/workforce data integrity required in medical facilities, HIMS enforces the following security controls:
            </p>
            <ul>
                <li><strong>Session Inactivity Timeout (3 Minutes):</strong> To protect unattended hospital workstations and mobile terminals, active sessions automatically expire after <strong>three (3) minutes of inactivity</strong>. Any unsaved interaction requires re-authentication.</li>
                <li><strong>Two-Factor Authentication (OTP Expiry: 5 Minutes):</strong> Email-based One-Time Password (OTP) verification codes expire strictly after <strong>five (5) minutes</strong> from generation. Rate limiting and single-use validation prevent replay attacks. Time-based One-Time Password (TOTP/Authenticator app) 2FA is also supported.</li>
                <li><strong>Mandatory Credential Rotation &amp; Password Policy:</strong> Strong password hashing via <code>bcrypt</code> (minimum 12 rounds), lockout mechanisms after 5 consecutive failed attempts, and administrative credential reset workflows.</li>
                <li><strong>Record Access Password Gate:</strong> Highly sensitive personal employee records require a secondary hospital administrative authorization password prior to decryption and display.</li>
            </ul>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-database-check"></i> 3. Schedule of Personal Data Collected &amp; Purpose</h2>
            <p>
                The following schedule exhaustively details all personal and professional data collected, stored, and processed within HIMS:
            </p>
            <div class="tos-table-wrap">
                <table class="tos-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Specific Data Elements</th>
                            <th>Storage / Security</th>
                            <th>Purpose &amp; Legal Basis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Identity &amp; Account</strong></td>
                            <td>Full Name, Hospital Email, System Role, Password Hash, Employee ID linkage</td>
                            <td><span class="tos-badge tos-badge-hash">Bcrypt Hashed</span></td>
                            <td>Authentication, role-based access control (RBAC), and session provisioning (RA 10173 / GDPR Art. 6.1.b).</td>
                        </tr>
                        <tr>
                            <td><strong>Employee Master Records</strong></td>
                            <td>Job Title, Department, Date of Birth, Gender, Employment Status, Hire Date, Supervisor Linkage</td>
                            <td><span class="tos-badge tos-badge-plain">Relational DB</span></td>
                            <td>Workforce allocation, institutional hierarchy, and organizational reporting.</td>
                        </tr>
                        <tr>
                            <td><strong>Contact Details</strong></td>
                            <td>Personal / Contact Phone Number</td>
                            <td><span class="tos-badge tos-badge-enc">AES-256-CBC Encrypted</span></td>
                            <td>Urgent hospital recall, incident response, and authorized HR communications.</td>
                        </tr>
                        <tr>
                            <td><strong>Clinical &amp; Professional Credentials</strong></td>
                            <td>PRC License Number, Professional Credentials, Issue &amp; Expiry Dates, Issuing Body</td>
                            <td><span class="tos-badge tos-badge-enc">AES-256-CBC Encrypted</span></td>
                            <td>Regulatory compliance, hospital accreditation, and automated license renewal alerts.</td>
                        </tr>
                        <tr>
                            <td><strong>Two-Factor Secrets</strong></td>
                            <td>TOTP Shared Secret, Recovery state</td>
                            <td><span class="tos-badge tos-badge-enc">AES-256-CBC Encrypted</span></td>
                            <td>Cryptographic verification for multi-factor authentication.</td>
                        </tr>
                        <tr>
                            <td><strong>Performance &amp; Competency</strong></td>
                            <td>KPI ratings, evaluation scores, gap analysis, developmental goals, PIP records</td>
                            <td><span class="tos-badge tos-badge-plain">Audited Tables</span></td>
                            <td>Staff evaluation, professional development, and clinical capability assessments.</td>
                        </tr>
                        <tr>
                            <td><strong>Learning &amp; CPD Records</strong></td>
                            <td>Course enrollments, Continuing Professional Development (CPD) hours, certificates earned</td>
                            <td><span class="tos-badge tos-badge-plain">Cryptographic Hash Codes</span></td>
                            <td>Tracking mandatory healthcare training (BLS, Infection Control) and PRC CPD compliance.</td>
                        </tr>
                        <tr>
                            <td><strong>Succession &amp; Leadership</strong></td>
                            <td>9-box evaluation matrices, leadership readiness, candidate nominations</td>
                            <td><span class="tos-badge tos-badge-plain">Role-Gated</span></td>
                            <td>Hospital executive leadership continuity and clinical department head succession.</td>
                        </tr>
                        <tr>
                            <td><strong>Audit &amp; Access Logs</strong></td>
                            <td>IP addresses, user agent, timestamps, modified fields (before/after states), record access unlocks</td>
                            <td><span class="tos-badge tos-badge-plain">Immutable Append-Only</span></td>
                            <td>Security telemetry, clinical forensic investigation, and compliance audits (GDPR Art. 32).</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-robot"></i> 4. Artificial Intelligence &amp; Privacy Boundary</h2>
            <div class="notice-box notice-box-warning">
                <strong><i class="bi bi-shield-exclamation"></i> Zero Employee Personal Data Access:</strong>
                In strict compliance with hospital confidentiality standards, the integrated AI Assistant operates under a strict isolation boundary:
            </div>
            <ul>
                <li><strong>No PII Access:</strong> The AI Assistant topic policy strictly blocks access to all employee personal data (<code>AiAccessPolicy::TOPICS['employees']['roles'] = []</code>). No personal employee data is transmitted to AI inference endpoints.</li>
                <li><strong>De-identification &amp; Redaction:</strong> When generating competency gap narratives or training recommendations, employee names and identifiers are automatically redacted.</li>
                <li><strong>No Model Training:</strong> Hospital workforce data is never used to train third-party artificial intelligence models.</li>
            </ul>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-person-gear"></i> 5. Data Subject Rights (GDPR &amp; RA 10173)</h2>
            <p>
                Every hospital employee and system user maintains sovereign rights over their personal data:
            </p>
            <ul>
                <li><strong>Right of Access &amp; Portability (Art. 15 &amp; Art. 20):</strong> Users can directly download a complete, machine-readable JSON archive of all personal records, evaluations, CPD logs, and certifications via the <a href="{{ route('profile.edit') }}" style="color:#059669;font-weight:600">Profile &amp; Privacy</a> dashboard.</li>
                <li><strong>Right to Rectification (Art. 16):</strong> Users can request corrections to inaccurate personal records or license details through their designated Department Administrator or HR Manager.</li>
                <li><strong>Right to Erasure / Anonymization (Art. 17):</strong> Upon termination of employment or formal request, personal data is irreversibly anonymized while preserving institutional audit logs required by health regulations.</li>
                <li><strong>Right to Withdraw Consent:</strong> Users may review and manage data processing consents at any time.</li>
            </ul>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-hdd-network"></i> 6. Data Retention, Pruning &amp; Disclosures</h2>
            <p>
                HIMS enforces automated data retention policies via the scheduled pruning daemon (<code>hims:prune-expired-data</code>):
            </p>
            <ul>
                <li><strong>Expired Session &amp; 2FA Codes:</strong> Automatically cleared upon expiration (3-minute idle and 5-minute OTP lifetime).</li>
                <li><strong>Third-Party Disclosures:</strong> Personal records are never sold, rented, or leased. Data is shared strictly with authorized healthcare integration partners (e.g., HR1/HR2 external payroll/HR systems) via encrypted, authenticated API keys.</li>
            </ul>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-slash-circle"></i> 7. Acceptable Use Policy</h2>
            <p>Users must not:</p>
            <ul>
                <li>Share system credentials or bypass multi-factor authentication protocols.</li>
                <li>Leave authenticated workstations unattended without manually locking the terminal.</li>
                <li>Attempt to reverse-engineer, exploit, or conduct unauthorized vulnerability scanning against hospital systems.</li>
                <li>Export or disclose colleague evaluation records or credential files outside official medical hospital duties.</li>
            </ul>
        </div>

        <div class="tos-section">
            <h2><i class="bi bi-geo-alt"></i> 8. Governing Law &amp; Contact</h2>
            <p>
                These Terms are governed by and construed in accordance with the laws of the Republic of the Philippines. For data protection inquiries, contact the Hospital Data Protection Officer (DPO) or the IT Security Directorate through the internal hospital service desk.
            </p>
        </div>
    </div>

    <div class="tos-footer">
        <div>
            &copy; {{ date('Y') }} Hospital Information Management System (HIMS). All rights reserved.
        </div>
        <div>
            <a href="{{ route('login') }}" style="color:#059669;text-decoration:none;font-weight:600">
                <i class="bi bi-box-arrow-in-right"></i> Sign In to HIMS
            </a>
        </div>
    </div>
</div>

</body>
</html>
