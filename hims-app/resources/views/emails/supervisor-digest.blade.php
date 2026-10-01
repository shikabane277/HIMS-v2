<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HIMS Supervisor Digest</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #1e3a8a; color: #ffffff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
        .header p { margin: 6px 0 0; font-size: 13px; opacity: 0.85; }
        .content { padding: 32px; }
        .greeting { font-size: 15px; margin-bottom: 20px; }
        .stat-grid { display: table; width: 100%; margin-bottom: 24px; border-collapse: separate; border-spacing: 8px 0; }
        .stat-col { display: table-cell; width: 33.33%; background: #f1f5f9; padding: 14px; border-radius: 6px; text-align: center; }
        .stat-val { font-size: 22px; font-weight: 700; color: #1e3a8a; }
        .stat-lbl { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-top: 4px; }
        .section-title { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #334155; margin: 24px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; }
        .item-list { list-style: none; padding: 0; margin: 0; }
        .item-row { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .badge { display: inline-block; padding: 3px 7px; font-size: 11px; font-weight: 600; border-radius: 4px; }
        .badge-red { background: #fee2e2; color: #dc2626; }
        .badge-yellow { background: #fef3c7; color: #d97706; }
        .badge-blue { background: #dbeafe; color: #2563eb; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-top: 24px; text-align: center; }
        .footer { background: #f8fafc; padding: 16px 32px; font-size: 11.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>HIMS Department Digest</h1>
            <p>{{ $digestData['department_name'] ?? 'Team' }} — {{ now()->format('F d, Y') }}</p>
        </div>
        <div class="content">
            <div class="greeting">
                Hello <strong>{{ $digestData['supervisor_name'] }}</strong>,
                <br>Here is this week's operational digest and compliance action items for your team:
            </div>

            <div class="stat-grid">
                <div class="stat-col">
                    <div class="stat-val">{{ count($digestData['direct_reports'] ?? []) }}</div>
                    <div class="stat-lbl">Team Members</div>
                </div>
                <div class="stat-col">
                    <div class="stat-val">{{ count($digestData['pending_reviews'] ?? []) }}</div>
                    <div class="stat-lbl">Pending Reviews</div>
                </div>
                <div class="stat-col">
                    <div class="stat-val">{{ count($digestData['expiring_credentials'] ?? []) }}</div>
                    <div class="stat-lbl">Expiring Credentials</div>
                </div>
            </div>

            @if(!empty($digestData['pending_reviews']))
            <div class="section-title">⚠️ Reviews Requiring Your Action</div>
            <ul class="item-list">
                @foreach($digestData['pending_reviews'] as $rev)
                <li class="item-row">
                    <strong>{{ $rev->first_name }} {{ $rev->last_name }}</strong> — {{ $rev->cycle_name }}
                    <span class="badge badge-yellow" style="float: right;">{{ ucfirst($rev->status) }}</span>
                </li>
                @endforeach
            </ul>
            @endif

            @if(!empty($digestData['expiring_credentials']))
            <div class="section-title">🪪 Credentials Expiring Within 30 Days</div>
            <ul class="item-list">
                @foreach($digestData['expiring_credentials'] as $c)
                <li class="item-row">
                    <strong>{{ $c->first_name }} {{ $c->last_name }}</strong>: {{ $c->credential_type }}
                    <span class="badge {{ $c->days_until <= 0 ? 'badge-red' : 'badge-yellow' }}" style="float: right;">
                        {{ $c->days_until <= 0 ? 'Expired' : $c->days_until . ' days left' }}
                    </span>
                </li>
                @endforeach
            </ul>
            @endif

            @if(!empty($digestData['upcoming_sessions']))
            <div class="section-title">📅 Upcoming Training Sessions</div>
            <ul class="item-list">
                @foreach($digestData['upcoming_sessions'] as $session)
                <li class="item-row">
                    <strong>{{ $session->title }}</strong> — {{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }} at {{ $session->start_time }}
                    <span class="badge badge-blue" style="float: right;">{{ $session->venue_name ?? 'Online/TBD' }}</span>
                </li>
                @endforeach
            </ul>
            @endif

            <div style="text-align: center;">
                <a href="{{ config('app.url') }}/dashboard" class="btn">Open HIMS Dashboard</a>
            </div>
        </div>
        <div class="footer">
            This automated summary was generated by the Hospital Information Management System (HIMS).
            <br>Confidential healthcare personnel data protected under RA 10173 (Data Privacy Act of 2012).
        </div>
    </div>
</body>
</html>
