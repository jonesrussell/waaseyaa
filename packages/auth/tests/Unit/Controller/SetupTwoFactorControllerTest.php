<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Auth\Controller\SetupTwoFactorController;

#[CoversClass(SetupTwoFactorController::class)]
final class SetupTwoFactorControllerTest extends TestCase
{
    public function testHappyPathReturnsSecretQrAndCodes(): void
    {
        $controller = new SetupTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest(TwoFactorTestKit::makeUser());

        $response = $controller($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('two-factor-setup', $body['data']['type']);
        $attrs = $body['data']['attributes'];
        $this->assertNotEmpty($attrs['secret']);
        $this->assertStringStartsWith('otpauth://totp/', $attrs['qr_code_uri']);
        $this->assertCount(8, $attrs['recovery_codes']);
    }

    public function testUnauthenticatedReturns401(): void
    {
        $controller = new SetupTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest(null);

        $response = $controller($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAlreadyEnabledReturns409(): void
    {
        $service = TwoFactorTestKit::makeService();
        $user = TwoFactorTestKit::makeUser();
        $user->setTwoFactorSecret('ALREADYENABLED');
        $controller = new SetupTwoFactorController($service);
        $request = TwoFactorTestKit::makeAuthenticatedRequest($user);

        $response = $controller($request);

        $this->assertSame(409, $response->getStatusCode());
    }
}
