<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Auth\TwoFactorService;
use Waaseyaa\User\User;

/**
 * POST /auth/2fa/setup — initiate 2FA setup.
 *
 * Generates a fresh secret + recovery codes for the authenticated user
 * and returns them for display. Nothing is persisted until the caller
 * follows up with POST /auth/2fa/enable carrying a matching TOTP.
 *
 * @api
 */
final class SetupTwoFactorController
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->attributes->get('_account');
        if (!$account instanceof User) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Authentication required.']],
            ], 401);
        }

        try {
            $result = $this->twoFactor->setup($account);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '409', 'title' => 'Already Enabled', 'detail' => $e->getMessage()]],
            ], 409);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => [
                'type' => 'two-factor-setup',
                'attributes' => [
                    'secret' => $result->secret,
                    'qr_code_uri' => $result->qrCodeUri,
                    'recovery_codes' => $result->recoveryCodes,
                ],
            ],
        ]);
    }
}
