<?php

namespace Tests\Unit;

use App\Services\TwoFactorService;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    public function test_code_generation_guarantees_six_characters_and_mixed_case(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = TwoFactorService::generateCode();

            $this->assertSame(6, strlen($code), "Code {$code} is not 6 characters long.");
            $this->assertMatchesRegularExpression('/[A-Z]/', $code, "Code {$code} does not contain uppercase character.");
            $this->assertMatchesRegularExpression('/[a-z]/', $code, "Code {$code} does not contain lowercase character.");
        }
    }

    public function test_email_masking(): void
    {
        $this->assertSame('c****i@gmail.com', TwoFactorService::maskEmail('claudiokhyelandrei@gmail.com'));
        $this->assertSame('a***n@hospital.ph', TwoFactorService::maskEmail('admin@hospital.ph'));
        $this->assertSame('j***@hospital.ph', TwoFactorService::maskEmail('js@hospital.ph'));
        $this->assertSame('m***@hospital.ph', TwoFactorService::maskEmail('m@hospital.ph'));
    }
}
