<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

class TwoFactorAuthService
{
    /**
     * Base32 character set per RFC 4648.
     */
    protected const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new cryptographically secure Base32 secret for TOTP.
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
     * Generate 8 secure backup recovery codes.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::random(5) . '-' . Str::random(5);
        }

        return $codes;
    }

    /**
     * Generate standard otpauth:// URI for authenticator apps (Google Authenticator, Authy, 1Password).
     */
    public function getOtpAuthUri(string $company, string $email, string $secret): string
    {
        $encodedCompany = rawurlencode($company);
        $encodedEmail = rawurlencode($email);

        return "otpauth://totp/{$encodedCompany}:{$encodedEmail}?secret={$secret}&issuer={$encodedCompany}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Verify a 6-digit TOTP code against the secret with a window of +/- 1 time step (30 seconds).
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTime = floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = $this->calculateTotp($secret, (int) ($currentTime + $i));
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate 6-digit TOTP for a given counter value per RFC 6238 / RFC 4226.
     */
    protected function calculateTotp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);

        // Pack counter into 8-byte big-endian binary string
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);

        // Calculate HMAC-SHA1
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binaryCode = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $totp = $binaryCode % 1000000;

        return str_pad((string) $totp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32 string to binary bytes.
     */
    protected function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $val = strpos(self::BASE32_ALPHABET, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}

