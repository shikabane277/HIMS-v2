<?php

namespace App\Http\Controllers;

use App\Support\AuditTrail;
use App\Support\RecordAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RecordAccessController extends Controller
{
    /**
     * Display the record access password prompt — redirects to dashboard since access is gated in-place via blur.
     */
    public function show(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    /**
     * Verify the admin-set record access password.
     */
    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $password = $request->input('record_password') ?? $request->input('password');

        if (! RecordAccess::verify($password)) {
            $this->auditRecordAccess('record_access_failed');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect Record Access Password.',
                ], 422);
            }

            return back()->withInput()->with('error', 'Incorrect Record Access Password. Contact your hospital administrator if you do not have the current key.');
        }

        $this->auditRecordAccess('record_access_unlocked');

        // Only ever return to a page on this site. The old code redirected to
        // whatever `intended` held, so a crafted link could bounce a signed-in
        // user to an outside site straight after they typed the password.
        $intendedRaw = (string) $request->input('intended', '');
        $parsedPath = parse_url($intendedRaw, PHP_URL_PATH) ?: '/dashboard';
        $parsedQuery = parse_url($intendedRaw, PHP_URL_QUERY);
        $path = '/'.ltrim($parsedPath, '/\\');
        $intended = url($path).($parsedQuery ? '?'.$parsedQuery : '');

        // Grant access
        $grants = $request->session()->get('record_access_grants', []);
        $grants[$path] = time() + 3600;
        $request->session()->put('record_access_grants', $grants);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Records unlocked successfully.',
            ]);
        }

        return redirect()->to($intended);
    }

    /**
     * In-page AJAX unlock endpoint to unblur sensitive records.
     */
    public function unlock(Request $request): JsonResponse
    {
        $password = $request->input('password') ?? $request->input('record_password');

        if (! RecordAccess::verify($password)) {
            $this->auditRecordAccess('record_access_failed');

            return response()->json([
                'success' => false,
                'message' => 'Incorrect Record Access Password.',
            ], 422);
        }

        $this->auditRecordAccess('record_access_unlocked');

        return response()->json([
            'success' => true,
            'message' => 'Sensitive records unlocked successfully.',
        ]);
    }

    /**
     * Lock sensitive records (re-apply blur).
     */
    public function lock(Request $request): RedirectResponse|JsonResponse
    {
        RecordAccess::lock();
        $this->auditRecordAccess('record_access_locked');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sensitive records locked.',
            ]);
        }

        return back()->with('status', 'Sensitive records locked.');
    }

    private function auditRecordAccess(string $action): void
    {
        try {
            if (Schema::hasTable('audit_trails') && auth()->check()) {
                AuditTrail::record($action, 'system_settings', 'record_access_password_hash');
            }
        } catch (\Throwable) {
        }
    }
}
