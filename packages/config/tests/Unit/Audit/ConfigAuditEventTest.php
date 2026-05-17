<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Audit\ConfigAuditEvent;

#[CoversClass(ConfigAuditEvent::class)]
final class ConfigAuditEventTest extends TestCase
{
    #[Test]
    public function constructor_assigns_all_fields(): void
    {
        $event = new ConfigAuditEvent(
            operation: ConfigAuditEvent::OP_RESET,
            actor: 'alice',
            entityRef: 'role.admin',
            beforeAfterDigest: hash('sha256', 'before:after'),
            timestamp: 1_700_000_000,
            context: ['dry_run' => false, 'skip_confirmation' => true],
        );

        self::assertSame(ConfigAuditEvent::OP_RESET, $event->operation);
        self::assertSame('alice', $event->actor);
        self::assertSame('role.admin', $event->entityRef);
        self::assertSame(hash('sha256', 'before:after'), $event->beforeAfterDigest);
        self::assertSame(1_700_000_000, $event->timestamp);
        self::assertSame(['dry_run' => false, 'skip_confirmation' => true], $event->context);
    }

    #[Test]
    public function operation_constants_have_stable_string_values(): void
    {
        // FR-053 stability: operation codes are part of the audit-log
        // contract — downstream tooling pivots on these literals.
        self::assertSame('export', ConfigAuditEvent::OP_EXPORT);
        self::assertSame('import', ConfigAuditEvent::OP_IMPORT);
        self::assertSame('reset', ConfigAuditEvent::OP_RESET);
    }

    #[Test]
    public function invalid_operation_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConfigAuditEvent(
            operation: 'bogus',
            actor: null,
            entityRef: null,
            beforeAfterDigest: null,
            timestamp: 0,
            context: [],
        );
    }

    #[Test]
    public function digest_is_deterministic_sha256_of_before_colon_after(): void
    {
        $digest = ConfigAuditEvent::digest('old yaml', 'new yaml');

        self::assertSame(hash('sha256', 'old yaml:new yaml'), $digest);
    }

    #[Test]
    public function digest_serializes_null_sides_as_literal_null(): void
    {
        self::assertSame(
            hash('sha256', 'null:after'),
            ConfigAuditEvent::digest(null, 'after'),
        );
        self::assertSame(
            hash('sha256', 'before:null'),
            ConfigAuditEvent::digest('before', null),
        );
        self::assertSame(
            hash('sha256', 'null:null'),
            ConfigAuditEvent::digest(null, null),
        );
    }

    #[Test]
    public function nullable_fields_accept_null(): void
    {
        $event = new ConfigAuditEvent(
            operation: ConfigAuditEvent::OP_IMPORT,
            actor: null,
            entityRef: null,
            beforeAfterDigest: null,
            timestamp: 1,
            context: [],
        );

        self::assertNull($event->actor);
        self::assertNull($event->entityRef);
        self::assertNull($event->beforeAfterDigest);
    }
}
