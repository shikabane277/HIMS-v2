<?php

namespace Tests\Unit;

use App\Support\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new TotpService;
    }

    public function test_it_generates_valid_base32_secret(): void
    {
        $secret = $this->totp->generateSecret(16);
        $this->assertSame(16, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{16}$/', $secret);
    }

    public function test_it_generates_and_verifies_valid_6_digit_otp(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $currentTime = time();
        $otp = $this->totp->getOtp($secret, $currentTime);

        $this->assertSame(6, strlen($otp));
        $this->assertTrue(ctype_digit($otp));
        $this->assertTrue($this->totp->verify($secret, $otp));
    }

    public function test_it_rejects_invalid_otp(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->assertFalse($this->totp->verify($secret, '000000'));
        $this->assertFalse($this->totp->verify($secret, 'abcdef'));
        $this->assertFalse($this->totp->verify($secret, '123'));
    }

    public function test_it_builds_valid_otpauth_uri(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $this->totp->getOtpAuthUri('HIMS Hospital', 'doctor@hospital.ph', $secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
