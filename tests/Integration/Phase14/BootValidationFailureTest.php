<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase14;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\TranslatableInterface;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Listing\Exception\UnsupportedListingException;
use Waaseyaa\Listing\Filter;
use Waaseyaa\Listing\FilterDefinition;
use Waaseyaa\Listing\HasListingsInterface;
use Waaseyaa\Listing\ListingDefinition;
use Waaseyaa\Listing\ListingDefinitionRegistry;
use Waaseyaa\Listing\ListingDefinitionValidator;
use Waaseyaa\Listing\ListingDiscoverer;
use Waaseyaa\Listing\Operator;
use Waaseyaa\Listing\Sort;

/**
 * Integration test for the boot-time listing-definition validation gate.
 *
 * Simulates the kernel boot flow:
 *  1. Service providers (here `BootFakeProvider`) declare their listings
 *     through {@see HasListingsInterface}.
 *  2. {@see ListingDiscoverer} flattens provider contributions.
 *  3. {@see ListingDefinitionRegistry::fromList()} indexes the result.
 *  4. {@see ListingDefinitionValidator} runs against the populated
 *     {@see EntityTypeManager} — failures throw
 *     {@see UnsupportedListingException} and would abort kernel boot
 *     (FR-052, FR-053).
 *
 * Final wiring of step 4 into {@code PackageManifestCompiler::warm()} is
 * the responsibility of WP11. This test exercises the same validator the
 * compiler will invoke, with the same provider→registry→validator chain,
 * so the failure mode is identical.
 *
 * Coverage matrix:
 *  - Rule A: pageSize > 1000 without allowUnbounded()
 *  - Rule D: unknown entity type
 *  - Rule F: unknown filter field
 *  - Rule I: langcode filter on non-translatable entity type
 *  - Positive case: a fully valid registry boots without exception
 */
#[CoversNothing]
final class BootValidationFailureTest extends TestCase
{
    private function bootEntityTypeManager(): EntityTypeManager
    {
        $fieldRegistry = new FieldDefinitionRegistry();
        $etm = new EntityTypeManager(
            eventDispatcher: new EventDispatcher(),
            fieldRegistry: $fieldRegistry,
        );

        // Non-translatable entity type with two queryable fields.
        $etm->registerEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: BootFakeArticle::class,
            keys: ['id' => 'id', 'label' => 'title'],
        ));
        $fieldRegistry->registerCoreFields('article', [
            'id' => new FieldDefinition(name: 'id', type: 'integer', targetEntityTypeId: 'article', stored: FieldStorage::Column),
            'title' => new FieldDefinition(name: 'title', type: 'string', targetEntityTypeId: 'article', stored: FieldStorage::Column),
            'created' => new FieldDefinition(name: 'created', type: 'integer', targetEntityTypeId: 'article', stored: FieldStorage::Column),
        ]);

        // Translatable entity type so the positive Rule-I path is exercisable.
        $etm->registerEntityType(new EntityType(
            id: 'translatable_post',
            label: 'Translatable Post',
            class: BootFakeTranslatableEntity::class,
            keys: [
                'id' => 'id',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
        ));
        $fieldRegistry->registerCoreFields('translatable_post', [
            'id' => new FieldDefinition(name: 'id', type: 'integer', targetEntityTypeId: 'translatable_post', stored: FieldStorage::Column),
            'title' => new FieldDefinition(name: 'title', type: 'string', targetEntityTypeId: 'translatable_post', stored: FieldStorage::Column),
            'langcode' => new FieldDefinition(name: 'langcode', type: 'string', targetEntityTypeId: 'translatable_post', stored: FieldStorage::Column),
        ]);

        return $etm;
    }

    /**
     * Simulate kernel boot: discover listings from providers, validate
     * them against the entity-type manager. On a misconfigured listing,
     * the validator raises and the (real) kernel would abort.
     *
     * @param list<HasListingsInterface> $providers
     */
    private function bootKernel(EntityTypeManager $etm, array $providers): void
    {
        $discoverer = new ListingDiscoverer($providers);
        $registry = ListingDefinitionRegistry::fromList($discoverer->discover());
        $validator = new ListingDefinitionValidator($etm);
        $validator->validate($registry);
    }

    #[Test]
    public function bootSucceedsWithValidListings(): void
    {
        $etm = $this->bootEntityTypeManager();
        $provider = new BootFakeProvider([
            new ListingDefinition(
                id: 'articles_recent',
                entityType: 'article',
                filters: [Filter::eq('title', 'foo')],
                sorts: [Sort::desc('created')],
                pageSize: 20,
            ),
            new ListingDefinition(
                id: 'translatable_posts_en',
                entityType: 'translatable_post',
                filters: [Filter::langcode('en')],
                pageSize: 50,
            ),
        ]);
        $this->bootKernel($etm, [$provider]);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function bootFailsRuleA_pageSizeOver1000WithoutAllowUnbounded(): void
    {
        $etm = $this->bootEntityTypeManager();
        $provider = new BootFakeProvider([
            new ListingDefinition(
                id: 'test_listing',
                entityType: 'article',
                pageSize: 2000,
            ),
        ]);
        try {
            $this->bootKernel($etm, [$provider]);
            self::fail('Expected UnsupportedListingException on boot');
        } catch (UnsupportedListingException $e) {
            self::assertSame('test_listing', $e->listingId);
            self::assertSame('pageSize exceeds 1000 without allowUnbounded()', $e->reason);
        }
    }

    #[Test]
    public function bootFailsRuleD_unknownEntityType(): void
    {
        $etm = $this->bootEntityTypeManager();
        $provider = new BootFakeProvider([
            new ListingDefinition(
                id: 'orphan_listing',
                entityType: 'no_such_entity_type',
            ),
        ]);
        try {
            $this->bootKernel($etm, [$provider]);
            self::fail('Expected UnsupportedListingException on boot');
        } catch (UnsupportedListingException $e) {
            self::assertSame('orphan_listing', $e->listingId);
            self::assertStringContainsString('no_such_entity_type', $e->reason);
            self::assertStringContainsString('not registered', $e->reason);
        }
    }

    #[Test]
    public function bootFailsRuleF_unknownFilterField(): void
    {
        $etm = $this->bootEntityTypeManager();
        $provider = new BootFakeProvider([
            new ListingDefinition(
                id: 'bad_field_listing',
                entityType: 'article',
                filters: [Filter::eq('nonexistent_column', 'x')],
            ),
        ]);
        try {
            $this->bootKernel($etm, [$provider]);
            self::fail('Expected UnsupportedListingException on boot');
        } catch (UnsupportedListingException $e) {
            self::assertSame('bad_field_listing', $e->listingId);
            self::assertSame('nonexistent_column', $e->fieldName);
            self::assertStringContainsString('not declared', $e->reason);
        }
    }

    #[Test]
    public function bootFailsRuleI_langcodeOnNonTranslatableEntityType(): void
    {
        $etm = $this->bootEntityTypeManager();
        $provider = new BootFakeProvider([
            new ListingDefinition(
                id: 'lang_bad_listing',
                entityType: 'article',
                // Bypass the construction-time check by using FilterDefinition
                // directly — the validator must still reject langcode on a
                // non-translatable entity type.
                filters: [new FilterDefinition(field: 'langcode', op: Operator::EQ, value: 'en')],
            ),
        ]);
        try {
            $this->bootKernel($etm, [$provider]);
            self::fail('Expected UnsupportedListingException on boot');
        } catch (UnsupportedListingException $e) {
            self::assertSame('lang_bad_listing', $e->listingId);
            self::assertSame('langcode', $e->fieldName);
            self::assertStringContainsString('non-translatable', $e->reason);
        }
    }
}

/**
 * Provider stub: a single service provider contributes a fixed list of
 * listings to the discoverer (mirrors how a real `ServiceProvider`
 * implementing {@see HasListingsInterface} would declare them).
 *
 * @internal
 */
final class BootFakeProvider implements HasListingsInterface
{
    /** @param list<ListingDefinition> $defs */
    public function __construct(private readonly array $defs) {}

    /** @return list<ListingDefinition> */
    public function listings(): array
    {
        return $this->defs;
    }
}

/** @internal */
final class BootFakeArticle {}

/** @internal */
final class BootFakeTranslatableEntity implements TranslatableInterface
{
    public function defaultLangcode(): string
    {
        return 'en';
    }

    public function activeLangcode(): string
    {
        return 'en';
    }

    public function language(): string
    {
        return 'en';
    }

    public function hasTranslation(string $langcode): bool
    {
        return false;
    }

    public function getTranslation(string $langcode): static
    {
        return $this;
    }

    public function addTranslation(string $langcode): static
    {
        return $this;
    }

    public function removeTranslation(string $langcode): void {}

    public function translations(): iterable
    {
        return [];
    }

    /** @return string[] */
    public function getTranslationLanguages(): array
    {
        return [];
    }
}
