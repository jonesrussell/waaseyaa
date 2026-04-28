<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities;

/**
 * Backed enum fixture for the inferrer tests (string-backed).
 */
enum InferrerSampleEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}

/**
 * Int-backed enum fixture used to verify the inferrer emits a single 'enum'
 * type id regardless of backing type (column shape is owned by EnumItem).
 */
enum InferrerSampleIntEnum: int
{
    case One = 1;
    case Two = 2;
}

/**
 * Plain (non-enum) class used to exercise the "unsupported user class" error path.
 */
final class InferrerNonEnumClass
{
}

/**
 * Fixture class with one public typed property per inference rule.
 *
 * Used by `FieldTypeInferrerTest`. Properties are deliberately untouched at
 * runtime — only their reflection metadata is consumed.
 */
final class InferrerTestFixtures
{
    public string $aString;
    public ?string $aNullableString = null;
    public int $anInt;
    public ?int $aNullableInt = null;
    public bool $aBool;
    public ?bool $aNullableBool = null;
    public float $aFloat;
    public ?float $aNullableFloat = null;
    /** @var array<int|string, mixed> */
    public array $anArray;
    /** @var array<int|string, mixed>|null */
    public ?array $aNullableArray = null;
    public \DateTimeImmutable $aDateTime;
    public ?\DateTimeImmutable $aNullableDateTime = null;
    public InferrerSampleEnum $anEnum;
    public ?InferrerSampleEnum $aNullableEnum = null;
    public InferrerSampleIntEnum $anIntEnum;
    public ?InferrerSampleIntEnum $aNullableIntEnum = null;

    public string|int $aUnion;
    public mixed $untyped;

    public iterable $anIterable;

    public InferrerNonEnumClass $aUserClass;

    /**
     * Property used by override-compatibility tests: explicit `type:` must work
     * when compatible with the declared PHP type, and conflict when not.
     */
    public string $aStringForOverride;

    /**
     * Property used to verify that an explicit `type:` accepted on an unsupported
     * PHP type bypasses inference (e.g. user class → `entity_reference`).
     */
    public InferrerNonEnumClass $aUserClassForOverride;

    /**
     * Properties used to verify the asymmetric scalar → entity_reference rule:
     * an explicit `type: 'entity_reference'` is accepted on `int`/`?int`/`string`/`?string`,
     * but `entity_reference` is never inferred from a bare scalar.
     */
    public ?int $aNullableIntForRef = null;
    public int $anIntForRef = 0;
    public ?string $aNullableStringForRef = null;
    public string $aStringForRef = '';
}
