<?php

namespace App\Support;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret key.
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $alphabetLength = strlen(self::BASE32_ALPHABET);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for a given timestamp.
     */
    public function getOtp(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / 30);
        $binarySecret = $this->base32Decode($secret);

        // Counter packed as 8-byte big-endian binary
        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $binaryTime, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = ($unpacked[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify whether a 6-digit user input matches the TOTP secret within window.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $currentTime = time();

        for ($slice = -$window; $slice <= $window; $slice++) {
            $checkTime = $currentTime + ($slice * 30);
            if (hash_equals($this->getOtp($secret, $checkTime), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build standard otpauth URI for QR code generation in authenticator apps.
     */
    public function getOtpAuthUri(string $company, string $accountName, string $secret): string
    {
        $companyEncoded = rawurlencode($company);
        $accountEncoded = rawurlencode($accountName);

        return "otpauth://totp/{$companyEncoded}:{$accountEncoded}?secret={$secret}&issuer={$companyEncoded}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Decode Base32 string to raw binary string.
     */
    private function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $val = strpos(self::BASE32_ALPHABET, $base32[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }
}
