<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Listing\Exception\UnsupportedListingException;
use Waaseyaa\Listing\Filter;
use Waaseyaa\Listing\FilterDefinition;
use Waaseyaa\Listing\ListingDefinition;
use Waaseyaa\Listing\ListingDefinitionRegistry;
use Waaseyaa\Listing\ListingDefinitionValidator;
use Waaseyaa\Listing\Operator;
use Waaseyaa\Listing\Sort;
use Waaseyaa\Listing\SortDefinition;

/**
 * Unit tests for {@see ListingDefinitionValidator}: each rule (A-I) gets
 * a positive (passes) and a negative (throws with expected reason) case.
 */
#[CoversClass(ListingDefinitionValidator::class)]
final class ListingDefinitionValidatorTest extends TestCase
{
    /**
     * Build a fixture validator pre-loaded with two entity types:
     *
     *  - `widget`: non-translatable, has `id` (int, column), `title`
     *    (string, column), `body` (string, data-stored), `weight` (int,
     *    column). Bundles: `gizmo` (with `gizmo_only` field) and `gadget`.
     *  - `translatable_node`: translatable (langcode key declared), with
     *    `id`, `title`.
     *
     * The fake translatable class is registered as the entity class on
     * `translatable_node`; it implements TranslatableInterface so the
     * EntityType constructor accepts `translatable=true`.
     */
    private function buildValidator(): ListingDefinitionValidator
    {
        $fieldRegistry = new FieldDefinitionRegistry();

        $etm = new EntityTypeManager(
            eventDispatcher: new EventDispatcher(),
            fieldRegistry: $fieldRegistry,
        );

        // Register entity types FIRST — registerEntityType() seeds the
        // field registry with the type's own getFieldDefinitions() (here
        // empty), which would otherwise overwrite hand-registered fields.
        $etm->registerEntityType(new EntityType(
            id: 'widget',
            label: 'Widget',
            class: ValidatorTestWidget::class,
            keys: ['id' => 'id', 'bundle' => 'type'],
        ));

        $etm->registerEntityType(new EntityType(
            id: 'translatable_node',
            label: 'Translatable Node',
            class: ValidatorTestTranslatableEntity::class,
            keys: [
                'id' => 'id',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
        ));

        // widget core fields (after entity type registration).
        $fieldRegistry->registerCoreFields('widget', [
            'id' => new FieldDefinition(name: 'id', type: 'integer', targetEntityTypeId: 'widget', stored: FieldStorage::Column),
            'title' => new FieldDefinition(name: 'title', type: 'string', targetEntityTypeId: 'widget', stored: FieldStorage::Column),
            'body' => new FieldDefinition(name: 'body', type: 'string', targetEntityTypeId: 'widget', stored: FieldStorage::Data),
            'weight' => new FieldDefinition(name: 'weight', type: 'integer', targetEntityTypeId: 'widget', stored: FieldStorage::Column),
        ]);
        $fieldRegistry->registerBundleFields('widget', 'gizmo', [
            'gizmo_only' => new FieldDefinition(name: 'gizmo_only', type: 'string', targetEntityTypeId: 'widget', targetBundle: 'gizmo', stored: FieldStorage::Column),
        ]);
        $fieldRegistry->registerBundleFields('widget', 'gadget', [
            'gadget_only' => new FieldDefinition(name: 'gadget_only', type: 'string', targetEntityTypeId: 'widget', targetBundle: 'gadget', stored: FieldStorage::Column),
        ]);

        $fieldRegistry->registerCoreFields('translatable_node', [
            'id' => new FieldDefinition(name: 'id', type: 'integer', targetEntityTypeId: 'translatable_node', stored: FieldStorage::Column),
            'title' => new FieldDefinition(name: 'title', type: 'string', targetEntityTypeId: 'translatable_node', stored: FieldStorage::Column),
            'langcode' => new FieldDefinition(name: 'langcode', type: 'string', targetEntityTypeId: 'translatable_node', stored: FieldStorage::Column),
        ]);

        return new ListingDefinitionValidator($etm);
    }

    private function registry(ListingDefinition ...$defs): ListingDefinitionRegistry
    {
        return ListingDefinitionRegistry::fromList(array_values($defs));
    }

    /* ------------------------------------------------------------------
     * Empty/single-valid sanity checks
     * ------------------------------------------------------------------ */

    #[Test]
    public function emptyRegistryValidates(): void
    {
        $this->buildValidator()->validate($this->registry());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function singleValidDefinitionPasses(): void
    {
        $def = new ListingDefinition(
            id: 'widgets_recent',
            entityType: 'widget',
            filters: [Filter::eq('title', 'foo')],
            sorts: [Sort::desc('weight')],
            pageSize: 20,
        );
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    /* ------------------------------------------------------------------
     * Rule A — pageSize > 1000 without allowUnbounded()
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleAPositive_pageSizeOver1000WithAllowUnboundedPasses(): void
    {
        $def = (new ListingDefinition(
            id: 'big',
            entityType: 'widget',
            pageSize: 5000,
        ))->allowUnbounded();
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleANegative_pageSizeOver1000WithoutAllowUnboundedThrows(): void
    {
        $def = new ListingDefinition(id: 'big', entityType: 'widget', pageSize: 5000);
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('big', $e->listingId);
            self::assertNull($e->fieldName);
            self::assertSame('pageSize exceeds 1000 without allowUnbounded()', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule B — pageSize === null without allowUnbounded()
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleBPositive_nullPageSizeWithAllowUnboundedPasses(): void
    {
        $def = (new ListingDefinition(
            id: 'unbounded_ok',
            entityType: 'widget',
            pageSize: null,
        ))->allowUnbounded();
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleBNegative_nullPageSizeWithoutAllowUnboundedThrows(): void
    {
        $def = new ListingDefinition(id: 'null_ps', entityType: 'widget', pageSize: null);
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('null_ps', $e->listingId);
            self::assertSame('pageSize is null without allowUnbounded()', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule C — approximateTotal=true with allowUnbounded() and null pageSize
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleCPositive_approximateTotalWithoutUnboundedPasses(): void
    {
        $def = new ListingDefinition(
            id: 'approx_ok',
            entityType: 'widget',
            pageSize: 20,
            approximateTotal: true,
        );
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleCNegative_approximateTotalWithUnboundedNullPageSizeThrows(): void
    {
        $def = (new ListingDefinition(
            id: 'approx_bad',
            entityType: 'widget',
            pageSize: null,
            approximateTotal: true,
        ))->allowUnbounded();
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('approx_bad', $e->listingId);
            self::assertStringContainsString('approximateTotal=true', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule D — entity type must exist
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleDPositive_knownEntityTypePasses(): void
    {
        $def = new ListingDefinition(id: 'd_pos', entityType: 'widget');
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleDNegative_unknownEntityTypeThrows(): void
    {
        $def = new ListingDefinition(id: 'd_neg', entityType: 'no_such_type');
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('d_neg', $e->listingId);
            self::assertStringContainsString('no_such_type', $e->reason);
            self::assertStringContainsString('not registered', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule E — bundle (if set) must exist
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleEPositive_knownBundlePasses(): void
    {
        $def = new ListingDefinition(id: 'e_pos', entityType: 'widget', bundle: 'gizmo');
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleENegative_unknownBundleThrows(): void
    {
        $def = new ListingDefinition(id: 'e_neg', entityType: 'widget', bundle: 'unknown_bundle');
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('e_neg', $e->listingId);
            self::assertStringContainsString('unknown_bundle', $e->reason);
            self::assertStringContainsString('not registered', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule F — filter/sort field must exist on the entity type
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleFPositive_knownFieldPasses(): void
    {
        $def = new ListingDefinition(
            id: 'f_pos',
            entityType: 'widget',
            filters: [Filter::eq('title', 'x')],
            sorts: [Sort::asc('weight')],
        );
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleFNegative_unknownFilterFieldThrows(): void
    {
        $def = new ListingDefinition(
            id: 'f_neg',
            entityType: 'widget',
            filters: [Filter::eq('no_such_field', 'x')],
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('f_neg', $e->listingId);
            self::assertSame('no_such_field', $e->fieldName);
            self::assertStringContainsString('not declared', $e->reason);
        }
    }

    #[Test]
    public function ruleFNegative_unknownSortFieldThrows(): void
    {
        $def = new ListingDefinition(
            id: 'f_neg_sort',
            entityType: 'widget',
            sorts: [Sort::asc('no_such_sort_field')],
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('f_neg_sort', $e->listingId);
            self::assertSame('no_such_sort_field', $e->fieldName);
        }
    }

    /* ------------------------------------------------------------------
     * Rule G — backend supports query (Column-stored only)
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleGPositive_columnStoredFieldPasses(): void
    {
        $def = new ListingDefinition(
            id: 'g_pos',
            entityType: 'widget',
            filters: [Filter::eq('title', 'x')], // title is Column
        );
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleGNegative_dataStoredFieldThrows(): void
    {
        $def = new ListingDefinition(
            id: 'g_neg',
            entityType: 'widget',
            filters: [Filter::eq('body', 'x')], // body is Data
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('g_neg', $e->listingId);
            self::assertSame('body', $e->fieldName);
            self::assertStringContainsString('supportsQuery=false', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule H — operator-to-field-type compatibility
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleHPositive_betweenOnIntFieldPasses(): void
    {
        $def = new ListingDefinition(
            id: 'h_pos',
            entityType: 'widget',
            filters: [Filter::between('weight', 1, 10)],
        );
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleHNegative_betweenOnStringFieldThrows(): void
    {
        $def = new ListingDefinition(
            id: 'h_neg_between',
            entityType: 'widget',
            filters: [Filter::between('title', 'a', 'z')],
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('h_neg_between', $e->listingId);
            self::assertSame('title', $e->fieldName);
            self::assertStringContainsString('BETWEEN', $e->reason);
        }
    }

    #[Test]
    public function ruleHNegative_startsWithOnIntFieldThrows(): void
    {
        $def = new ListingDefinition(
            id: 'h_neg_sw',
            entityType: 'widget',
            filters: [
                // FilterDefinition's construction-time check accepts STARTS_WITH
                // with a string value; Rule H rejects because field type is integer.
                new FilterDefinition(field: 'weight', op: Operator::STARTS_WITH, value: 'pre'),
            ],
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('h_neg_sw', $e->listingId);
            self::assertSame('weight', $e->fieldName);
            self::assertStringContainsString('string/text', $e->reason);
        }
    }

    #[Test]
    public function ruleHNegative_inWithMismatchedElementTypesThrows(): void
    {
        $def = new ListingDefinition(
            id: 'h_neg_in',
            entityType: 'widget',
            filters: [Filter::in('weight', ['a', 'b'])],
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('h_neg_in', $e->listingId);
            self::assertSame('weight', $e->fieldName);
            self::assertStringContainsString('incompatible with field type', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Rule I — langcode filter only on translatable entity types
     * ------------------------------------------------------------------ */

    #[Test]
    public function ruleIPositive_langcodeOnTranslatablePasses(): void
    {
        $def = new ListingDefinition(
            id: 'i_pos',
            entityType: 'translatable_node',
            filters: [Filter::langcode('en')],
        );
        $this->buildValidator()->validate($this->registry($def));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ruleINegative_langcodeOnNonTranslatableThrows(): void
    {
        // `widget` is non-translatable, so even attempting a `langcode` filter
        // must be rejected. We bypass the construction-time check by using
        // FilterDefinition directly; the field doesn't exist on the widget
        // entity but Rule I fires first per the validator's check order.
        $def = new ListingDefinition(
            id: 'i_neg',
            entityType: 'widget',
            filters: [new FilterDefinition(field: 'langcode', op: Operator::EQ, value: 'en')],
        );
        try {
            $this->buildValidator()->validate($this->registry($def));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('i_neg', $e->listingId);
            self::assertSame('langcode', $e->fieldName);
            self::assertStringContainsString('non-translatable', $e->reason);
        }
    }

    /* ------------------------------------------------------------------
     * Fail-fast: validator stops at the first failing definition
     * ------------------------------------------------------------------ */

    #[Test]
    public function failFast_stopsAtFirstFailingDefinition(): void
    {
        $first = new ListingDefinition(id: 'first_bad', entityType: 'widget', pageSize: 5000);
        $second = new ListingDefinition(id: 'second_bad', entityType: 'no_such_type');

        try {
            $this->buildValidator()->validate($this->registry($first, $second));
            self::fail('Expected UnsupportedListingException');
        } catch (UnsupportedListingException $e) {
            self::assertSame('first_bad', $e->listingId, 'fail-fast must report the first failing listing');
        }
    }
}

/**
 * Minimal non-translatable entity stub for the `widget` entity type used
 * across this test. Does not need to implement EntityInterface for the
 * tests' purposes — the validator never instantiates entities, only
 * reads metadata from the {@see EntityTypeManager}.
 *
 * @internal
 */
final class ValidatorTestWidget
{
}

/**
 * Minimal translatable entity stub. Implements TranslatableInterface so
 * that EntityType's constructor accepts `translatable=true`.
 *
 * @internal
 */
final class ValidatorTestTranslatableEntity implements \Waaseyaa\Entity\TranslatableInterface
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

    public function removeTranslation(string $langcode): void
    {
    }

    public function translations(): iterable
    {
        return [];
    }

    /** @return string[] */
    public function getTranslationLanguages(): array
    {
        return [];
    }

    public function fieldLangcode(string $fieldName): ?string
    {
        return null;
    }
}
