<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Exception;

use Waaseyaa\Api\JsonApiDocument;

/**
 * Exception that carries a JsonApiDocument error response.
 *
 * Used to replace union return types (e.g., Entity|JsonApiDocument) in controller
 * helper methods. Instead of returning an error document that callers must
 * instanceof-check, the helper throws this exception and callers catch it once.
 */
final class JsonApiDocumentException extends \RuntimeException
{
    public function __construct(
        public readonly JsonApiDocument $document,
    ) {
        parent::__construct('JSON:API error response', $document->statusCode);
    }
}
