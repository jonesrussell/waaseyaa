<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\User\User;

#[CoversClass(User::class)]
final class UserTwoFactorFieldsTest extends TestCase
{
    public function testTwoFactorSecretDefaultsToNull(): void
    {
        $user = new User();

        $this->assertNull($user->getTwoFactorSecret());
    }

    public function testTwoFactorRecoveryCodesDefaultToNull(): void
    {
        $user = new User();

        $this->assertNull($user->getTwoFactorRecoveryCodesHash());
    }

    public function testSetTwoFactorSecretRoundTrips(): void
    {
        $user = new User();

        $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');

        $this->assertSame('JBSWY3DPEHPK3PXP', $user->getTwoFactorSecret());
    }

    public function testSetTwoFactorSecretAcceptsNull(): void
    {
        $user = new User();
        $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');

        $user->setTwoFactorSecret(null);

        $this->assertNull($user->getTwoFactorSecret());
    }

    public function testSetTwoFactorRecoveryCodesRoundTrips(): void
    {
        $user = new User();
        $hashes = ['$argon2id$v=19$m=65536,t=4,p=1$a$b', '$argon2id$v=19$m=65536,t=4,p=1$c$d'];

        $user->setTwoFactorRecoveryCodesHash($hashes);

        $this->assertSame($hashes, $user->getTwoFactorRecoveryCodesHash());
    }

    public function testSetTwoFactorRecoveryCodesAcceptsNull(): void
    {
        $user = new User();
        $user->setTwoFactorRecoveryCodesHash(['hash1', 'hash2']);

        $user->setTwoFactorRecoveryCodesHash(null);

        $this->assertNull($user->getTwoFactorRecoveryCodesHash());
    }

    public function testRecoveryCodesGetterFiltersNonStringEntries(): void
    {
        $user = new User();

        // Set via the entity API directly to simulate corrupted/legacy data.
        $user->set('two_factor_recovery_codes_hash', ['valid', 123, null, 'also-valid']);

        $this->assertSame(['valid', 'also-valid'], $user->getTwoFactorRecoveryCodesHash());
    }

    public function testTwoFactorFieldsCoexistWithExistingUserFields(): void
    {
        $user = User::make(['name' => 'alice', 'mail' => 'alice@example.com']);
        $user->setTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $user->setTwoFactorRecoveryCodesHash(['hash1']);

        // Existing fields still work.
        $this->assertSame('alice', $user->getName());
        $this->assertSame('alice@example.com', $user->getEmail());

        // New fields also work.
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->getTwoFactorSecret());
        $this->assertSame(['hash1'], $user->getTwoFactorRecoveryCodesHash());
    }
}
