<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Exception;

use Waaseyaa\CLI\Parser\ParseError;

final class ParseException extends \RuntimeException
{
    public function __construct(
        public readonly ParseError $parseError,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($parseError->message, $code, $previous);
    }
}
