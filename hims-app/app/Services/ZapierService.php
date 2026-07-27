<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZapierService
{
    /**
     * Dispatch a webhook payload to a named Zapier endpoint.
     * Configure endpoint URLs in config/services.php under 'zapier'.
     *
     * @param  string $event   e.g. 'performance_review_approved', 'credential_expired'
     * @param  array  $payload Data to send in the webhook body
     */
    public function dispatch(string $event, array $payload): bool
    {
        $webhookUrl = config("services.zapier.{$event}");

        if (empty($webhookUrl)) {
            Log::debug("Zapier webhook not configured for event: {$event}");
            return false;
        }

        try {
            $response = Http::timeout(10)->post($webhookUrl, array_merge($payload, [
                'event'     => $event,
                'system'    => 'HIMS-PD',
                'timestamp' => now()->toIso8601String(),
            ]));

            if (!$response->successful()) {
                Log::warning("Zapier dispatch failed for {$event}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            Log::info("Zapier event dispatched: {$event}");
            return true;

        } catch (\Exception $e) {
            Log::error("Zapier dispatch exception for {$event}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Pre-built event dispatchers ──────────────────────────

    public function onReviewApproved(string $reviewId, string $employeeName, float $score): void
    {
        $this->dispatch('performance_review_approved', [
            'review_id'     => $reviewId,
            'employee_name' => $employeeName,
            'score'         => $score,
        ]);
    }

    public function onCredentialExpired(string $credentialId, string $employeeName, string $type, string $expiry): void
    {
        $this->dispatch('credential_expired', [
            'credential_id' => $credentialId,
            'employee_name' => $employeeName,
            'credential_type' => $type,
            'expiry_date'   => $expiry,
        ]);
    }

    public function onPipInitiated(string $pipId, string $employeeName, string $supervisorName): void
    {
        $this->dispatch('pip_initiated', [
            'pip_id'          => $pipId,
            'employee_name'   => $employeeName,
            'supervisor_name' => $supervisorName,
        ]);
    }

    public function onTrainingRegistration(string $sessionTitle, string $employeeName, string $date): void
    {
        $this->dispatch('training_registration', [
            'session_title' => $sessionTitle,
            'employee_name' => $employeeName,
            'session_date'  => $date,
        ]);
    }

    public function onCertificateIssued(string $employeeName, string $courseTitle, string $certCode): void
    {
        $this->dispatch('certificate_issued', [
            'employee_name' => $employeeName,
            'course_title'  => $courseTitle,
            'certificate_code' => $certCode,
        ]);
    }
}
