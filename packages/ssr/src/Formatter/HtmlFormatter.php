<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Formatter;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Waaseyaa\Field\FieldFormatterInterface;
use Waaseyaa\SSR\Attribute\AsFormatter;

#[AsFormatter(fieldType: 'text_long')]
final class HtmlFormatter implements FieldFormatterInterface
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->forceHttpsUrls();
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function format(mixed $value, array $settings = []): string
    {
        $html = (string) ($value ?? '');
        if ($html === '') {
            return '';
        }

        return $this->sanitizer->sanitize($html);
    }
}
