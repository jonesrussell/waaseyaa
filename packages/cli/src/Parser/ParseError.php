<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Parser;

final readonly class ParseError
{
    public function __construct(
        public ParseErrorKind $kind,
        public string $message,
        public ?string $offendingToken = null,
    ) {}
}
