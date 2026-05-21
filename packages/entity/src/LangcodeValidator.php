<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Validates langcode strings against a BCP-47-tolerant pattern.
 *
 * Accepts: language subtag (2-8 alpha), optionally followed by:
 *   - script subtag (4 alpha), e.g. `Hant` in `zh-Hant`
 *   - region subtag (2 alpha or 3 digits), e.g. `US` in `en-US`
 *
 * Examples of valid langcodes: en, en-US, zh-Hant, zh-Hant-TW, fr-CA, en-CA, mas
 * Out of scope (rejected): variant subtags, private-use extensions, grandfathered tags.
 *
 * @see https://www.rfc-editor.org/rfc/rfc5646 BCP 47
 *
 * @api
 */
final class LangcodeValidator
{
    /**
     * BCP-47-tolerant regex pattern for langcode validation.
     *
     * Covers language + optional script + optional region subtags.
     * Variant subtags and private-use extensions are out of scope for v1.
     */
    public const string BCP47_PATTERN = '/^[a-zA-Z]{2,8}(-[a-zA-Z]{4})?(-[a-zA-Z]{2}|\d{3})?$/D';

    /**
     * Validate a langcode string.
     *
     * @throws \InvalidArgumentException When the langcode does not match BCP47_PATTERN.
     */
    public static function validate(string $langcode): void
    {
        if ($langcode === '' || !preg_match(self::BCP47_PATTERN, $langcode)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid langcode "%s". Expected a BCP-47 language subtag (e.g. "en", "en-US", "zh-Hant").',
                    $langcode,
                ),
            );
        }
    }
}
