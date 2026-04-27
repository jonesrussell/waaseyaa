<?php

declare(strict_types=1);

namespace Waaseyaa\StructuredImport\Gfm;

/**
 * Stateless prompt-string normalizer for alias matching.
 *
 * Applies only mb_strtolower (UTF-8) and whitespace collapsing.
 * No transliteration — diacritics are preserved (C-012).
 */
final class PromptNormalizer
{
    public function normalize(string $prompt): string
    {
        $lowered = mb_strtolower($prompt, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $lowered);

        return trim($collapsed ?? $lowered);
    }
}
