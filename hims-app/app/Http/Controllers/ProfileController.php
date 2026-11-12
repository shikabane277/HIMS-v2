<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AuditTrail;
use App\Support\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account (GDPR Article 17 — Right to Erasure).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $userId = (string) $user->id;

        // Purge associated personal chat transcripts and active OTP tokens under GDPR Art. 17
        try {
            if (Schema::hasTable('ai_chat_messages')) {
                DB::table('ai_chat_messages')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('ai_chat_sessions')) {
                DB::table('ai_chat_sessions')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('login_otps')) {
                DB::table('login_otps')->where('email', $user->email)->delete();
            }
            if (Schema::hasTable('audit_trails')) {
                AuditTrail::record('gdpr_account_erasure', 'users', $userId, metadata: [
                    'gdpr_article' => 'Article 17 - Right to Erasure',
                    'erased_at' => now()->toIso8601String(),
                ]);
            }
        } catch (\Throwable) {
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * GDPR Article 15 & Article 20 — Right of Access & Right to Data Portability.
     *
     * Exports a complete, machine-readable JSON archive of all personal data
     * held about the authenticated user and their linked employee profile.
     */
    public function exportData(Request $request): StreamedResponse
    {
        $user = $request->user();
        $employeeId = $user->employee_id;

        $employeeProfile = null;
        $reviews = [];
        $competencyAssessments = [];
        $credentials = [];
        $courseEnrollments = [];
        $cpdLogs = [];
        $trainingRegistrations = [];

        if ($employeeId && Schema::hasTable('employees')) {
            $employeeProfile = DB::table('employees')->where('employee_id', $employeeId)->first();
            if ($employeeProfile && ! empty($employeeProfile->phone)) {
                try {
                    $employeeProfile->phone = Crypt::decryptString($employeeProfile->phone);
                } catch (\Throwable) {
                }
            }

            if (Schema::hasTable('performance_reviews')) {
                $reviews = DB::table('performance_reviews')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('created_at')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('competency_assessments')) {
                $competencyAssessments = DB::table('competency_assessments')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('assessed_date')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('employee_credentials')) {
                $credentials = DB::table('employee_credentials')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('created_at')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('course_enrollments')) {
                $courseEnrollments = DB::table('course_enrollments')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('enrollment_date')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('cpd_records')) {
                $cpdLogs = DB::table('cpd_records')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('date_earned')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('training_registrations')) {
                $trainingRegistrations = DB::table('training_registrations')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('registration_date')
                    ->get()
                    ->all();
            }
        }

        $chatSessions = Schema::hasTable('ai_chat_sessions')
            ? DB::table('ai_chat_sessions')->where('user_id', $user->id)->get(['id', 'title', 'created_at', 'updated_at'])->all()
            : [];

        $chatMessages = Schema::hasTable('ai_chat_messages')
            ? DB::table('ai_chat_messages')->where('user_id', $user->id)->get(['session_id', 'role', 'message', 'created_at'])->all()
            : [];

        $auditHistory = Schema::hasTable('audit_trails')
            ? DB::table('audit_trails')
                ->where('user_id', (string) $user->id)
                ->orderByDesc('timestamp')
                ->limit(250)
                ->get(['audit_id', 'timestamp', 'action', 'resource_type', 'resource_id', 'ip_address', 'request_path'])
                ->all()
            : [];

        $consentTimestamp = null;
        if (Schema::hasTable('system_settings')) {
            $consentTimestamp = DB::table('system_settings')
                ->where('key', 'gdpr_consent_user_'.$user->id)
                ->value('value');
        }

        $exportPayload = [
            'gdpr_compliance_metadata' => [
                'regulation' => 'EU General Data Protection Regulation (GDPR) Articles 15 & 20 / PH RA 10173',
                'exported_at' => now()->toIso8601String(),
                'data_controller' => config('app.name', 'HIMS Hospital'),
                'ai_employee_data_sharing' => 'PROHIBITED (Zero employee personal data is accessible to AI models)',
                'consent_acknowledged_at' => $consentTimestamp ?: session('gdpr_consent_acknowledged_at'),
            ],
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) ($user->is_active ?? true),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'totp_2fa_enabled' => ! empty($user->totp_secret),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'employee_profile' => $employeeProfile,
            'performance_reviews' => $reviews,
            'competency_assessments' => $competencyAssessments,
            'credentials_and_licenses' => $credentials,
            'course_enrollments' => $courseEnrollments,
            'cpd_hours_log' => $cpdLogs,
            'training_registrations' => $trainingRegistrations,
            'ai_assistant_history' => [
                'sessions' => $chatSessions,
                'messages' => $chatMessages,
            ],
            'security_audit_log' => $auditHistory,
        ];

        if (Schema::hasTable('audit_trails')) {
            AuditTrail::record('gdpr_data_export', 'users', (string) $user->id, metadata: [
                'gdpr_articles' => 'Art. 15 (Right of Access) & Art. 20 (Data Portability)',
            ]);
        }

        $filename = 'hims-personal-data-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($exportPayload) {
            echo json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    /**
     * Download personal data as a CSV archive (ZIP containing structured CSV files).
     * Supports individual tabular CSV analysis in spreadsheet tools (Excel, Calc).
     */
    public function exportCsv(Request $request): BinaryFileResponse|StreamedResponse
    {
        $user = $request->user();
        $employeeId = $user->employee_id;

        $employeeProfile = null;
        $reviews = [];
        $competencyAssessments = [];
        $credentials = [];
        $courseEnrollments = [];
        $cpdLogs = [];
        $trainingRegistrations = [];

        if ($employeeId && Schema::hasTable('employees')) {
            $employeeProfile = DB::table('employees')->where('employee_id', $employeeId)->first();
            if ($employeeProfile && ! empty($employeeProfile->phone)) {
                try {
                    $employeeProfile->phone = Crypt::decryptString($employeeProfile->phone);
                } catch (\Throwable) {
                }
            }

            if (Schema::hasTable('performance_reviews')) {
                $reviews = DB::table('performance_reviews')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('created_at')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('competency_assessments')) {
                $competencyAssessments = DB::table('competency_assessments')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('assessed_date')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('employee_credentials')) {
                $credentials = DB::table('employee_credentials')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('created_at')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('course_enrollments')) {
                $courseEnrollments = DB::table('course_enrollments')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('enrollment_date')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('cpd_records')) {
                $cpdLogs = DB::table('cpd_records')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('date_earned')
                    ->get()
                    ->all();
            }

            if (Schema::hasTable('training_registrations')) {
                $trainingRegistrations = DB::table('training_registrations')
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('registration_date')
                    ->get()
                    ->all();
            }
        }

        $auditHistory = Schema::hasTable('audit_trails')
            ? DB::table('audit_trails')
                ->where('user_id', (string) $user->id)
                ->orderByDesc('timestamp')
                ->limit(250)
                ->get(['audit_id', 'timestamp', 'action', 'resource_type', 'resource_id', 'ip_address', 'request_path'])
                ->all()
            : [];

        if (Schema::hasTable('audit_trails')) {
            AuditTrail::record('gdpr_csv_export', 'users', (string) $user->id, metadata: [
                'format' => 'CSV/ZIP',
                'gdpr_articles' => 'Art. 15 (Right of Access) & Art. 20 (Data Portability)',
            ]);
        }

        // Generate CSV buffers for each data entity
        $csvFiles = [];

        // 1. Account Summary CSV
        $accountRows = [
            ['Field', 'Value'],
            ['User ID', $user->id],
            ['Name', $user->name],
            ['Email', $user->email],
            ['Role', $user->role],
            ['Active', ($user->is_active ?? true) ? 'Yes' : 'No'],
            ['2FA TOTP Enabled', ! empty($user->totp_secret) ? 'Yes' : 'No'],
            ['Account Created', (string) $user->created_at],
            ['Last Updated', (string) $user->updated_at],
        ];
        if ($employeeProfile) {
            $accountRows[] = ['Employee Code', $employeeProfile->employee_code ?? ''];
            $accountRows[] = ['Job Title', $employeeProfile->position_title ?? ''];
            $accountRows[] = ['Department ID', $employeeProfile->department_id ?? ''];
            $accountRows[] = ['Contact Phone', $employeeProfile->phone ?? ''];
            $accountRows[] = ['Date of Birth', $employeeProfile->date_of_birth ?? ''];
            $accountRows[] = ['Hire Date', $employeeProfile->hire_date ?? ''];
            $accountRows[] = ['Employment Status', $employeeProfile->employment_status ?? ''];
        }
        $csvFiles['account_details.csv'] = $this->buildCsvString($accountRows);

        // 2. Performance Reviews CSV
        if (! empty($reviews)) {
            $reviewHeaders = ['Review ID', 'Period Start', 'Period End', 'Overall Score', 'Status', 'Created At'];
            $reviewRows = [$reviewHeaders];
            foreach ($reviews as $rev) {
                $reviewRows[] = [
                    $rev->review_id ?? '',
                    $rev->review_period_start ?? '',
                    $rev->review_period_end ?? '',
                    $rev->overall_rating ?? $rev->overall_score ?? '',
                    $rev->status ?? '',
                    $rev->created_at ?? '',
                ];
            }
            $csvFiles['performance_reviews.csv'] = $this->buildCsvString($reviewRows);
        }

        // 3. Competency Assessments CSV
        if (! empty($competencyAssessments)) {
            $compHeaders = ['Assessment ID', 'Competency ID', 'Proficiency Level', 'Assessed Date', 'Created At'];
            $compRows = [$compHeaders];
            foreach ($competencyAssessments as $comp) {
                $compRows[] = [
                    $comp->assessment_id ?? $comp->id ?? '',
                    $comp->competency_id ?? '',
                    $comp->proficiency_level ?? $comp->rating ?? '',
                    $comp->assessed_date ?? '',
                    $comp->created_at ?? '',
                ];
            }
            $csvFiles['competency_assessments.csv'] = $this->buildCsvString($compRows);
        }

        // 4. Credentials & Licenses CSV
        if (! empty($credentials)) {
            $credHeaders = ['Credential ID', 'Type', 'Issuing Body', 'Issue Date', 'Expiry Date', 'Status'];
            $credRows = [$credHeaders];
            foreach ($credentials as $cred) {
                $credRows[] = [
                    $cred->credential_id ?? $cred->id ?? '',
                    $cred->credential_type ?? $cred->type ?? '',
                    $cred->issuing_body ?? '',
                    $cred->issue_date ?? '',
                    $cred->expiry_date ?? '',
                    $cred->status ?? '',
                ];
            }
            $csvFiles['credentials_and_licenses.csv'] = $this->buildCsvString($credRows);
        }

        // 5. Course Enrollments CSV
        if (! empty($courseEnrollments)) {
            $enrollHeaders = ['Enrollment ID', 'Course ID', 'Status', 'Progress %', 'CPD Hours Earned', 'Enrollment Date', 'Completed At'];
            $enrollRows = [$enrollHeaders];
            foreach ($courseEnrollments as $enr) {
                $enrollRows[] = [
                    $enr->enrollment_id ?? '',
                    $enr->course_id ?? '',
                    $enr->status ?? '',
                    $enr->progress_pct ?? '0',
                    $enr->cpd_hours_earned ?? '0',
                    $enr->enrollment_date ?? '',
                    $enr->completed_at ?? '',
                ];
            }
            $csvFiles['course_enrollments.csv'] = $this->buildCsvString($enrollRows);
        }

        // 6. CPD Records CSV
        if (! empty($cpdLogs)) {
            $cpdHeaders = ['CPD ID', 'Activity Name', 'CPD Hours', 'Source Type', 'Date Earned', 'Verified'];
            $cpdRows = [$cpdHeaders];
            foreach ($cpdLogs as $cpd) {
                $cpdRows[] = [
                    $cpd->cpd_id ?? '',
                    $cpd->activity_name ?? '',
                    $cpd->cpd_hours ?? '',
                    $cpd->source_type ?? '',
                    $cpd->date_earned ?? '',
                    ($cpd->verified ?? false) ? 'Yes' : 'No',
                ];
            }
            $csvFiles['cpd_hours_log.csv'] = $this->buildCsvString($cpdRows);
        }

        // 7. Training Registrations CSV
        if (! empty($trainingRegistrations)) {
            $trainHeaders = ['Registration ID', 'Session ID', 'Registration Date', 'Status', 'Check-In Time'];
            $trainRows = [$trainHeaders];
            foreach ($trainingRegistrations as $tr) {
                $trainRows[] = [
                    $tr->registration_id ?? '',
                    $tr->session_id ?? '',
                    $tr->registration_date ?? '',
                    $tr->status ?? '',
                    $tr->check_in_time ?? '',
                ];
            }
            $csvFiles['training_registrations.csv'] = $this->buildCsvString($trainRows);
        }

        // 8. Security Audit History CSV
        if (! empty($auditHistory)) {
            $auditHeaders = ['Audit ID', 'Timestamp', 'Action', 'Resource Type', 'Resource ID', 'IP Address', 'Request Path'];
            $auditRows = [$auditHeaders];
            foreach ($auditHistory as $aud) {
                $auditRows[] = [
                    $aud->audit_id ?? '',
                    $aud->timestamp ?? '',
                    $aud->action ?? '',
                    $aud->resource_type ?? '',
                    $aud->resource_id ?? '',
                    $aud->ip_address ?? '',
                    $aud->request_path ?? '',
                ];
            }
            $csvFiles['security_audit_log.csv'] = $this->buildCsvString($auditRows);
        }

        // If ZipArchive is available, bundle all CSV files into a single zip archive
        if (class_exists('ZipArchive')) {
            $zipTempPath = tempnam(sys_get_temp_dir(), 'hims_csv_');
            $zip = new ZipArchive();
            if ($zip->open($zipTempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($csvFiles as $filename => $csvContent) {
                    $zip->addFromString($filename, $csvContent);
                }
                $zip->close();

                $downloadName = 'hims-my-data-csv-'.now()->format('Y-m-d').'.zip';

                return response()->download($zipTempPath, $downloadName, [
                    'Content-Type' => 'application/zip',
                ])->deleteFileAfterSend(true);
            }
        }

        // Fallback: If ZipArchive fails, stream the primary account_details.csv directly
        $filename = 'hims-my-data-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csvFiles) {
            echo $csvFiles['account_details.csv'] ?? '';
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Convert an array of rows into a UTF-8 CSV string with BOM for Excel compatibility.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function buildCsvString(array $rows): string
    {
        $handle = fopen('php://memory', 'r+');
        // UTF-8 BOM so Excel opens accents and symbols cleanly
        fputs($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * GDPR Article 7 & Article 13 — Record explicit user acknowledgment of the
     * Hospital Data Processing & Privacy Notice.
     */
    public function acknowledgeGdprConsent(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $timestamp = now()->toIso8601String();

        $request->session()->put('gdpr_consent_acknowledged_at', $timestamp);

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'gdpr_consent_user_'.$user->id],
                ['value' => $timestamp, 'updated_at' => now()]
            );
        }

        if (Schema::hasTable('audit_trails')) {
            AuditTrail::record('gdpr_consent_acknowledged', 'users', (string) $user->id, metadata: [
                'acknowledged_at' => $timestamp,
                'policy_version' => '2026.1-GDPR-RA10173',
            ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'acknowledged_at' => $timestamp,
            ]);
        }

        return back()->with('status', 'gdpr-consent-recorded');
    }

    /**
     * Generate temporary TOTP secret for setup.
     */
    public function setupTotp(Request $request, TotpService $totpService)
    {
        $secret = $totpService->generateSecret(16);
        $request->session()->put('totp_setup_secret', $secret);

        $otpauth = $totpService->getOtpAuthUri(
            config('app.name', 'HIMS Hospital'),
            $request->user()->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'otpauth' => $otpauth,
        ]);
    }

    /**
     * Confirm 6-digit code and bind TOTP secret to user.
     */
    public function confirmTotp(Request $request, TotpService $totpService): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'totp_code' => ['required', 'string', 'size:6'],
            'totp_secret' => ['required', 'string'],
        ]);

        $secret = $request->input('totp_secret');
        $code = $request->input('totp_code');

        if (! $totpService->verify($secret, $code)) {
            return back()->with('error', 'Invalid 6-digit code. Please verify the code in your authenticator app.');
        }

        $user = $request->user();
        $user->totp_secret = Crypt::encryptString($secret);
        $user->totp_consumed_counter = null;
        $user->save();

        AuditTrail::record('totp_2fa_enabled', 'users', (string) $user->id);

        return back()->with('success', 'Authenticator App (TOTP) successfully enabled! You can now use your app for two-factor verification.');
    }

    /**
     * Disable TOTP authentication.
     */
    public function disableTotp(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->totp_secret = null;
        $user->save();

        AuditTrail::record('totp_2fa_disabled', 'users', (string) $user->id);

        return back()->with('success', 'Authenticator App 2FA has been disabled. Login will fall back to email verification.');
    }
}
