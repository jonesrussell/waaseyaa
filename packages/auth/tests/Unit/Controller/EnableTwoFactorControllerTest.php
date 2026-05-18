<?php

declare(strict_types=1);

namespace Waaseyaa\Auth\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Auth\Controller\EnableTwoFactorController;
use Waaseyaa\Auth\TwoFactorManager;

#[CoversClass(EnableTwoFactorController::class)]
final class EnableTwoFactorControllerTest extends TestCase
{
    public function testHappyPathEnables(): void
    {
        $service = TwoFactorTestKit::makeService();
        $manager = new TwoFactorManager();
        $secret = $manager->generateSecret();
        $codes = $manager->generateRecoveryCodes();
        $firstCode = $manager->getCurrentCode($secret);
        $controller = new EnableTwoFactorController($service);
        $request = TwoFactorTestKit::makeAuthenticatedRequest(TwoFactorTestKit::makeUser(), [
            'secret' => $secret,
            'recovery_codes' => $codes,
            'first_code' => $firstCode,
        ]);

        $response = $controller($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($body['data']['attributes']['enabled']);
    }

    public function testWrongFirstCodeReturns401(): void
    {
        $service = TwoFactorTestKit::makeService();
        $controller = new EnableTwoFactorController($service);
        $request = TwoFactorTestKit::makeAuthenticatedRequest(TwoFactorTestKit::makeUser(), [
            'secret' => 'JBSWY3DPEHPK3PXP',
            'recovery_codes' => ['a-1', 'b-2'],
            'first_code' => '000000',
        ]);

        $response = $controller($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testMissingFieldsReturns400(): void
    {
        $controller = new EnableTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest(TwoFactorTestKit::makeUser(), [
            'secret' => '',
        ]);

        $response = $controller($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUnauthenticatedReturns401(): void
    {
        $controller = new EnableTwoFactorController(TwoFactorTestKit::makeService());
        $request = TwoFactorTestKit::makeAuthenticatedRequest(null, ['secret' => 'x', 'recovery_codes' => ['a'], 'first_code' => '1']);

        $response = $controller($request);

        $this->assertSame(401, $response->getStatusCode());
    }
}
