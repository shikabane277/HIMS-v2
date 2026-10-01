<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - {{ $certificate->certificate_code }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --cert-gold: #b45309;
            --cert-gold-light: #fef3c7;
            --cert-navy: #0f172a;
            --cert-border: #cbd5e1;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            min-height: 100vh;
        }
        .action-bar {
            width: 100%;
            max-width: 900px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }
        .btn-primary {
            background: #1e40af;
            color: #ffffff;
        }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-outline {
            background: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-outline:hover { background: #f8fafc; }

        /* Certificate Container */
        .cert-container {
            width: 100%;
            max-width: 900px;
            background: #ffffff;
            border: 12px double #d97706;
            padding: 48px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            position: relative;
            text-align: center;
            background-image: radial-gradient(#f8fafc 15%, transparent 16%);
            background-size: 20px 20px;
        }
        .cert-header {
            margin-bottom: 24px;
        }
        .hospital-brand {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #0f172a;
            text-transform: uppercase;
        }
        .hospital-sub {
            font-size: 12px;
            letter-spacing: 0.12em;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .cert-title-wrap {
            margin: 32px 0 20px;
        }
        .cert-title {
            font-family: 'Cinzel', serif;
            font-size: 34px;
            font-weight: 800;
            color: #92400e;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .cert-presented {
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-top: 6px;
        }
        .recipient-name {
            font-family: 'Cinzel', serif;
            font-size: 30px;
            font-weight: 700;
            color: #0f172a;
            margin: 20px 0 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
            min-width: 380px;
        }
        .recipient-title {
            font-size: 13.5px;
            color: #475569;
            font-weight: 500;
        }
        .cert-body {
            font-size: 15px;
            line-height: 1.7;
            color: #334155;
            max-width: 680px;
            margin: 24px auto 32px;
        }
        .course-title {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 17px;
        }
        .cert-meta {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            margin-top: 48px;
            padding-top: 24px;
        }
        .meta-col {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .sign-line {
            width: 180px;
            height: 1px;
            background: #94a3b8;
            margin-bottom: 8px;
        }
        .sign-name {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }
        .sign-title {
            font-size: 11px;
            color: #64748b;
        }
        .seal-badge {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(217, 119, 6, 0.3);
            border: 3px double #ffffff;
        }
        .seal-badge i { font-size: 26px; }
        .seal-badge span { font-size: 9px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 2px; }
        .cert-footer {
            margin-top: 36px;
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
        }
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #16a34a;
            font-weight: 600;
        }

        /* Printable PDF Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .action-bar { display: none !important; }
            .cert-container {
                box-shadow: none;
                border: 8px double #d97706;
                padding: 40px;
                max-width: 100%;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="javascript:history.back()" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <div style="display:flex;gap:10px">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print / Download PDF
            </button>
        </div>
    </div>

    <div class="cert-container">
        <div class="cert-header">
            <div class="hospital-brand">Hospital Information &amp; Management System</div>
            <div class="hospital-sub">Center for Medical Continuing Education &amp; Staff Development</div>
        </div>

        <div class="cert-title-wrap">
            <div class="cert-title">Certificate of Completion</div>
            <div class="cert-presented">This is proudly awarded to</div>
        </div>

        <div class="recipient-name">{{ $certificate->first_name }} {{ $certificate->last_name }}</div>
        <div class="recipient-title">{{ $certificate->position_title ?? 'Healthcare Professional' }} &middot; {{ $certificate->department_name }}</div>

        <div class="cert-body">
            For successfully meeting all training requirements and demonstrating clinical competency in
            <div class="course-title" style="margin: 8px 0;">"{{ $certificate->course_title }}"</div>
            awarding <strong>{{ $certificate->cpd_hours ?? 0 }} Continuing Professional Development (CPD) Credit Units</strong>
            in compliance with Philippine PRC Regulations and JCI SQE Standards.
        </div>

        <div class="cert-meta">
            <div class="meta-col">
                <div class="sign-line"></div>
                <div class="sign-name">Hospital Training Director</div>
                <div class="sign-title">Medical Education Committee</div>
            </div>

            <div class="meta-col">
                <div class="seal-badge">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>Official Seal</span>
                </div>
            </div>

            <div class="meta-col">
                <div class="sign-line"></div>
                <div class="sign-name">Director of Human Resources</div>
                <div class="sign-title">Hospital Administration</div>
            </div>
        </div>

        <div class="cert-footer">
            <div>
                Certificate Code: <strong>{{ $certificate->certificate_code }}</strong> &middot; 
                Issued: {{ \Carbon\Carbon::parse($certificate->issued_date)->format('F d, Y') }}
            </div>
            <div class="verified-badge">
                <i class="bi bi-shield-fill-check"></i> Authenticity Verified &amp; Recorded
            </div>
        </div>
    </div>

</body>
</html>
