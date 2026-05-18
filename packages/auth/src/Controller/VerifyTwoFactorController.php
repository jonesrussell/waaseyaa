<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Auth\RateLimiterInterface;
use Waaseyaa\Auth\TwoFactorService;
use Waaseyaa\User\User;

/**
 * POST /auth/2fa/verify — submit a TOTP or recovery code.
 *
 * Rate-limited identically to the login endpoint (5 attempts per IP per 60s)
 * under the distinct `2fa-verify:` namespace. Used both during login's
 * second-factor step and for sensitive-operation re-authentication.
 *
 * @api
 */
final class VerifyTwoFactorController
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly RateLimiterInterface $rateLimiter,
    ) {}

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

        $ip = $request->getClientIp() ?? '127.0.0.1';
        $key = '2fa-verify:' . $ip;

        if ($this->rateLimiter->tooManyAttempts($key, 5)) {
            $response = new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '429', 'title' => 'Too Many Requests', 'detail' => 'Too many verification attempts. Please try again later.']],
            ], 429);
            $response->headers->set('Retry-After', '60');
            return $response;
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
        if ($code === '') {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'code is required.']],
            ], 400);
        }

        if (!$this->twoFactor->verify($account, $code)) {
            $this->rateLimiter->hit($key, 60);
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Invalid Code', 'detail' => 'The submitted code is not valid.']],
            ], 401);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => ['type' => 'two-factor', 'attributes' => ['verified' => true]],
        ]);
    }
}
