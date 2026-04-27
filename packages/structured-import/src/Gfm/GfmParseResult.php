<?php

declare(strict_types=1);

namespace Waaseyaa\StructuredImport\Gfm;

final readonly class GfmParseResult
{
    /**
     * @param list<array{prompt: string, value: string}> $rows   Parsed data rows from the first valid 2-column table.
     * @param list<string>                               $errors Non-fatal parse errors and skipped-table notices.
     */
    public function __construct(
        public array $rows,
        public array $errors,
    ) {}
}
