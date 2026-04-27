<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Item;

/**
 * Single exception class for all EnumItem error variants.
 *
 * The variant is carried in `$code` as the discriminator suffix so callers
 * (and `assertSame()` in tests) can identify the precise error without
 * introducing a class hierarchy. Messages always include the offending
 * field name and enum class per NFR-002.
 *
 * Variants:
 *   - EnumFieldTypeException::MISSING_ENUM_CLASS
 *   - EnumFieldTypeException::UNKNOWN_ENUM_CLASS
 *   - EnumFieldTypeException::NOT_A_BACKED_ENUM
 *   - EnumFieldTypeException::UNSUPPORTED_BACKING_TYPE
 *   - EnumFieldTypeException::INVALID_INPUT_VALUE
 *   - EnumFieldTypeException::INVALID_STORED_VALUE
 *
 * The string codes mirror the contract names (e.g. `EnumFieldType.UnknownEnumClass`)
 * so error messages remain greppable.
 */
final class EnumFieldTypeException extends \RuntimeException
{
    public const string MISSING_ENUM_CLASS = 'EnumFieldType.MissingEnumClass';
    public const string UNKNOWN_ENUM_CLASS = 'EnumFieldType.UnknownEnumClass';
    public const string NOT_A_BACKED_ENUM = 'EnumFieldType.NotABackedEnum';
    public const string UNSUPPORTED_BACKING_TYPE = 'EnumFieldType.UnsupportedBackingType';
    public const string INVALID_INPUT_VALUE = 'EnumFieldType.InvalidInputValue';
    public const string INVALID_STORED_VALUE = 'EnumFieldType.InvalidStoredValue';

    public function __construct(
        public readonly string $variant,
        string $message,
        public readonly string $fieldName,
        public readonly ?string $enumClass = null,
    ) {
        // Pass 0 to the int-typed parent code; carry the discriminator in $variant.
        parent::__construct($message, 0);
    }
}
