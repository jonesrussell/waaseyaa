<?php

declare(strict_types=1);

namespace Waaseyaa\Cache\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\ContextNames;
use Waaseyaa\Cache\ContextRegistry;

#[CoversClass(ContextRegistry::class)]
#[CoversClass(ContextNames::class)]
final class ContextRegistryTest extends TestCase
{
    #[Test]
    public function seededCanonicalNamesArePresent(): void
    {
        $registry = new ContextRegistry();

        $this->assertTrue($registry->has(ContextNames::USER_ROLES));
        $this->assertTrue($registry->has(ContextNames::USER_ID));
        $this->assertTrue($registry->has(ContextNames::LANGUAGE_CONTENT));
        $this->assertTrue($registry->has(ContextNames::LANGUAGE_INTERFACE));
    }

    #[Test]
    public function urlQueryPrefixMatchesAnyConcreteParam(): void
    {
        $registry = new ContextRegistry();

        // Every url.query.<param> is treated as canonical by prefix match.
        $this->assertTrue($registry->has('url.query.page'));
        $this->assertTrue($registry->has('url.query.category'));
        $this->assertTrue($registry->has('url.query.q'));
        $this->assertTrue($registry->has('url.query.sort_by'));

        // The sentinel literal itself is also "has"-true (it is the canonical
        // entry for the prefix family).
        $this->assertTrue($registry->has('url.query.*'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownNames(): void
    {
        $registry = new ContextRegistry();

        $this->assertFalse($registry->has('user.unknown'));
        $this->assertFalse($registry->has('cookie.session'));
        $this->assertFalse($registry->has('language')); // missing suffix
        $this->assertFalse($registry->has('url.query')); // no trailing dot
        $this->assertFalse($registry->has(''));
    }

    #[Test]
    public function registerAddsNewName(): void
    {
        $registry = new ContextRegistry();

        $this->assertFalse($registry->has('extension.feature_flag'));

        $registry->register('extension.feature_flag');

        $this->assertTrue($registry->has('extension.feature_flag'));
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $registry = new ContextRegistry();

        $registry->register('extension.feature_flag');
        $registry->register('extension.feature_flag');
        $registry->register('extension.feature_flag');

        $this->assertTrue($registry->has('extension.feature_flag'));

        // Idempotency reflected in ::all(): only one entry for that name.
        $occurrences = array_filter(
            $registry->all(),
            static fn (string $name): bool => $name === 'extension.feature_flag',
        );
        $this->assertCount(1, $occurrences);
    }

    #[Test]
    public function registerRejectsUppercaseLetters(): void
    {
        $registry = new ContextRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid context name "User\.roles"/');

        $registry->register('User.roles');
    }

    #[Test]
    public function registerRejectsLeadingDigit(): void
    {
        $registry = new ContextRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('1.feature');
    }

    #[Test]
    public function registerRejectsSpecialCharacters(): void
    {
        $registry = new ContextRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('user-roles');
    }

    #[Test]
    public function registerRejectsEmptyString(): void
    {
        $registry = new ContextRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('');
    }

    #[Test]
    public function registerRejectsColon(): void
    {
        // Distinct from tag-string regex which permits ':' — context names do not.
        $registry = new ContextRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('user:roles');
    }

    #[Test]
    public function allReturnsSortedList(): void
    {
        $registry = new ContextRegistry();
        $registry->register('z.late');
        $registry->register('a.early');
        $registry->register('m.middle');

        $names = $registry->all();

        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names, 'all() must return a sorted list');

        // Sanity check: all seeded names appear plus the three registered.
        $this->assertContains(ContextNames::USER_ROLES, $names);
        $this->assertContains(ContextNames::USER_ID, $names);
        $this->assertContains(ContextNames::LANGUAGE_CONTENT, $names);
        $this->assertContains(ContextNames::LANGUAGE_INTERFACE, $names);
        $this->assertContains('url.query.*', $names);
        $this->assertContains('a.early', $names);
        $this->assertContains('m.middle', $names);
        $this->assertContains('z.late', $names);
    }
}
