<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\MigrationConcurrencyException;

#[CoversClass(MigrationConcurrencyException::class)]
final class MigrationConcurrencyExceptionTest extends TestCase
{
    #[Test]
    public function round_trips_all_readonly_properties(): void
    {
        $e = new MigrationConcurrencyException(
            migrationId: 'wp_users',
            lockPath: '/tmp/migration-locks/wp_users.lock',
            holdingPid: 4242,
        );

        self::assertSame('wp_users', $e->migrationId);
        self::assertSame('/tmp/migration-locks/wp_users.lock', $e->lockPath);
        self::assertSame(4242, $e->holdingPid);
        self::assertSame(0, $e->getCode(), 'Integer Exception::code stays at 0; semantics live in CODE constant.');
    }

    #[Test]
    public function stable_code_constant(): void
    {
        self::assertSame('MIGRATION_CONCURRENT_RUN', MigrationConcurrencyException::CODE);
    }

    #[Test]
    public function message_contains_lock_path_and_pid(): void
    {
        $e = new MigrationConcurrencyException(
            migrationId: 'wp_posts',
            lockPath: '/var/lock/migration-locks/wp_posts.lock',
            holdingPid: 1234,
        );

        $message = $e->getMessage();
        self::assertStringContainsString("Migration 'wp_posts' is already running", $message);
        self::assertStringContainsString('/var/lock/migration-locks/wp_posts.lock', $message);
        self::assertStringContainsString('pid: 1234', $message);
        // Recovery hint:
        self::assertStringContainsString('rm /var/lock/migration-locks/wp_posts.lock', $message);
    }

    #[Test]
    public function null_pid_renders_unknown(): void
    {
        $e = new MigrationConcurrencyException(
            migrationId: 'wp_comments',
            lockPath: '/tmp/x.lock',
            holdingPid: null,
        );

        self::assertNull($e->holdingPid);
        self::assertStringContainsString('pid: unknown', $e->getMessage());
        self::assertStringNotContainsString('pid: 0', $e->getMessage());
    }

    #[Test]
    public function rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationConcurrencyException(
            migrationId: '',
            lockPath: '/tmp/x.lock',
            holdingPid: null,
        );
    }

    #[Test]
    public function rejects_empty_lock_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationConcurrencyException(
            migrationId: 'wp_users',
            lockPath: '',
            holdingPid: null,
        );
    }
}
