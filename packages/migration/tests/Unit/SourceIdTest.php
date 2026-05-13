<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Canonical\CanonicalForm;
use Waaseyaa\Migration\SourceId;

#[CoversClass(SourceId::class)]
#[CoversClass(CanonicalForm::class)]
final class SourceIdTest extends TestCase
{
    #[Test]
    public function constructor_accepts_well_formed_value(): void
    {
        $id = new SourceId(sourceType: 'wordpress_post', keys: ['post_id' => 42]);

        self::assertSame('wordpress_post', $id->sourceType);
        self::assertSame(['post_id' => 42], $id->keys);
    }

    #[Test]
    public function constructor_rejects_empty_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId(sourceType: '', keys: ['id' => 1]);
    }

    #[Test]
    public function constructor_rejects_uppercase_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SourceId::$sourceType must match');
        new SourceId(sourceType: 'WordpressPost', keys: ['id' => 1]);
    }

    #[Test]
    public function constructor_rejects_empty_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId(sourceType: 'wordpress_post', keys: []);
    }

    #[Test]
    public function constructor_rejects_non_scalar_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // @phpstan-ignore-next-line — intentionally passing nested array to trigger the guard.
        new SourceId(sourceType: 'wordpress_post', keys: ['composite' => ['nested' => 1]]);
    }

    #[Test]
    public function constructor_accepts_null_value(): void
    {
        $id = new SourceId(sourceType: 'wordpress_post', keys: ['post_id' => 42, 'site_id' => null]);

        self::assertSame(['post_id' => 42, 'site_id' => null], $id->keys);
    }

    #[Test]
    public function constructor_rejects_numeric_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId(sourceType: 'wordpress_post', keys: [0 => 'value']);
    }

    #[Test]
    public function hash_is_a_sha256_hex_digest(): void
    {
        $hash = (new SourceId('wordpress_post', ['post_id' => 42]))->hash();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    #[Test]
    public function hash_is_deterministic_across_invocations(): void
    {
        $id = new SourceId('wordpress_post', ['post_id' => 42, 'site_id' => 7]);

        $first = $id->hash();
        for ($i = 0; $i < 1_000; $i++) {
            self::assertSame($first, $id->hash());
        }
    }

    #[Test]
    public function hash_is_independent_of_key_declaration_order(): void
    {
        $a = new SourceId('wordpress_post', ['post_id' => 42, 'site_id' => 7]);
        $b = new SourceId('wordpress_post', ['site_id' => 7, 'post_id' => 42]);

        self::assertSame($a->hash(), $b->hash());
        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function hash_differs_when_source_type_differs(): void
    {
        $a = new SourceId('wordpress_post', ['id' => 1]);
        $b = new SourceId('wordpress_page', ['id' => 1]);

        self::assertNotSame($a->hash(), $b->hash());
        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function hash_differs_when_key_value_type_differs(): void
    {
        $intKey = new SourceId('wordpress_post', ['id' => 42]);
        $stringKey = new SourceId('wordpress_post', ['id' => '42']);

        self::assertNotSame(
            $intKey->hash(),
            $stringKey->hash(),
            'Integer 42 and string "42" must produce distinct hashes (no implicit coercion).',
        );
    }

    #[Test]
    public function hash_differs_when_keys_differ(): void
    {
        $a = new SourceId('wordpress_post', ['post_id' => 42]);
        $b = new SourceId('wordpress_post', ['post_id' => 43]);

        self::assertNotSame($a->hash(), $b->hash());
    }
}
