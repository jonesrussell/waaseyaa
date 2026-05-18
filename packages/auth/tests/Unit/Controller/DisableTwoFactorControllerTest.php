<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Auth\Controller\DisableTwoFactorController;
use Waaseyaa\Auth\TwoFactorManager;

#[CoversClass(DisableTwoFactorController::class)]
final class DisableTwoFactorControllerTest extends TestCase
{
    public function testHappyPathDisables(): void
    {
        $user = TwoFactorTestKit::makeUser();
        $manager = new TwoFactorManager();
        $secret = $manager->generateSecret();
        $user->setTwoFactorSecret($secret);
        $user->setTwoFactorRecoveryCodesHash([]);
        $controller = new DisableTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest($user, ['code' => $manager->getCurrentCode($secret)]);

        $response = $controller($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($body['data']['attributes']['enabled']);
        $this->assertNull($user->getTwoFactorSecret());
        $this->assertNull($user->getTwoFactorRecoveryCodesHash());
    }

    public function testWrongCodeReturns401AndKeepsCredentials(): void
    {
        $user = TwoFactorTestKit::makeUser();
        $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $user->setTwoFactorRecoveryCodesHash(['hash']);
        $controller = new DisableTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest($user, ['code' => '000000']);

        $response = $controller($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->getTwoFactorSecret());
        $this->assertSame(['hash'], $user->getTwoFactorRecoveryCodesHash());
    }

    public function testNotEnabledReturns400(): void
    {
        $controller = new DisableTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest(TwoFactorTestKit::makeUser(), ['code' => '123456']);

        $response = $controller($request);

        $this->assertSame(400, $response->getStatusCode());
    }
}
