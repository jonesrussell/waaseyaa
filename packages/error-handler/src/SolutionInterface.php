<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

/**
 * @api
 */
interface SolutionInterface
{
    public function getTitle(): string;

    public function getDescription(): string;

    /**
     * @return array<string, string> label => url
     */
    public function getDocumentationLinks(): array;
}
