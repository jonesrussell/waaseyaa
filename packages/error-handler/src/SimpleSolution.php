<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

final class SimpleSolution implements SolutionInterface
{
    /**
     * @param array<string, string> $documentationLinks
     */
    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly array $documentationLinks = [],
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDocumentationLinks(): array
    {
        return $this->documentationLinks;
    }
}
