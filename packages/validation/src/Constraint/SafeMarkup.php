<?php

declare(strict_types=1);

namespace Waaseyaa\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that text does not contain dangerous HTML or scripts.
 *
 * Checks for script tags, event handler attributes (onclick, onerror, etc.),
 * javascript: URIs, and other XSS vectors.
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class SafeMarkup extends Constraint
{
    public string $message = 'The text contains potentially dangerous markup.';

    /**
     * Default safe HTML tags when no custom list is provided.
     */
    public const array DEFAULT_ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'code', 'dd', 'dl', 'dt',
        'em', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'li', 'ol',
        'p', 'pre', 'small', 'strong', 'sub', 'sup', 'table', 'tbody',
        'td', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    /**
     * @param string[] $allowedTags List of allowed HTML tag names (without angle brackets).
     * @param string|null $message Custom violation message.
     * @param string[]|string|null $groups Validation groups.
     * @param mixed|null $payload Payload for external use.
     */
    public function __construct(
        public readonly array $allowedTags = self::DEFAULT_ALLOWED_TAGS,
        ?string $message = null,
        array|string|null $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
