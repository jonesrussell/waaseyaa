<?php

declare(strict_types=1);

namespace Waaseyaa\Auth;

/**
 * Result of a successful 2FA setup call.
 *
 * Carries the data the user needs to complete the enable handshake: the
 * Base32 secret (paste into authenticator app), the otpauth:// QR URI
 * (scan with authenticator app), and the 8 plaintext recovery codes
 * (shown to the user exactly once). Only hashes of the recovery codes
 * are persisted on enable — the plaintext values must be displayed and
 * captured before the response is closed.
 *
 * @api
 */
final readonly class TwoFactorSetupResult
{
    /**
     * @param list<string> $recoveryCodes Plaintext recovery codes, displayed once.
     */
    public function __construct(
        public string $secret,
        public string $qrCodeUri,
        public array $recoveryCodes,
    ) {}
}
