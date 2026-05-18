<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Auth\TwoFactorService;
use Waaseyaa\User\User;

/**
 * POST /auth/2fa/disable — wipe 2FA credentials after proof-of-possession.
 *
 * Body: { code }. Caller must submit a valid TOTP or unused recovery code
 * before the secret + recovery codes are wiped atomically. This guards
 * against an attacker with a hijacked session silently disabling 2FA.
 *
 * @api
 */
final class DisableTwoFactorController
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

        if (!$this->twoFactor->isEnabled($account)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Two-Factor Not Enabled', 'detail' => 'Two-factor authentication is not enabled for this user.']],
            ], 400);
        }

        try {
            $body = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'Request body is not valid JSON.']],
            ], 400);
        }

        $code = is_string($body['code'] ?? null) ? $body['code'] : '';
        if ($code === '' || !$this->twoFactor->verify($account, $code)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Invalid Code', 'detail' => 'A valid code is required to disable two-factor authentication.']],
            ], 401);
        }

        $this->twoFactor->disable($account);

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => ['type' => 'two-factor', 'attributes' => ['enabled' => false]],
        ]);
    }
}
