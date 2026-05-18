<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Auth\TwoFactorService;
use Waaseyaa\User\User;

/**
 * POST /auth/2fa/enable — confirm + persist 2FA.
 *
 * Body: { secret, recovery_codes: list<string>, first_code: string }.
 * Verifies `first_code` matches `secret`; on success, persists the secret
 * plus Argon2id-hashed recovery codes to the user.
 *
 * @api
 */
final class EnableTwoFactorController
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
            $body = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'Request body is not valid JSON.']],
            ], 400);
        }

        $secret = is_string($body['secret'] ?? null) ? $body['secret'] : '';
        $firstCode = is_string($body['first_code'] ?? null) ? $body['first_code'] : '';
        /** @var list<string> $recoveryCodes */
        $recoveryCodes = [];
        if (is_array($body['recovery_codes'] ?? null)) {
            foreach ($body['recovery_codes'] as $code) {
                if (is_string($code) && $code !== '') {
                    $recoveryCodes[] = $code;
                }
            }
        }

        if ($secret === '' || $firstCode === '' || $recoveryCodes === []) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'secret, recovery_codes, and first_code are required.']],
            ], 400);
        }

        if (!$this->twoFactor->enable($account, $secret, $recoveryCodes, $firstCode)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Invalid Code', 'detail' => 'The submitted code does not match the secret.']],
            ], 401);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => ['type' => 'two-factor', 'attributes' => ['enabled' => true]],
        ]);
    }
}
