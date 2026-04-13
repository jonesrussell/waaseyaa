<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Graph;

final readonly class ApplicationGraph
{
    /** @var array<string, GraphSection> */
    public array $sections;

    /** @param list<GraphSection> $sections */
    public function __construct(
        public string $version,
        array $sections,
    ) {
        $keyed = [];
        foreach ($sections as $section) {
            $keyed[$section->key] = $section;
        }
        $this->sections = $keyed;
    }

    public function getSection(string $key): ?GraphSection
    {
        return $this->sections[$key] ?? null;
    }

    /** @return array{version: string, sections: array<string, array<string, mixed>>} */
    public function toArray(): array
    {
        $sections = [];
        foreach ($this->sections as $key => $section) {
            $sections[$key] = $section->toArray();
        }

        return [
            'version' => $this->version,
            'sections' => $sections,
        ];
    }
}
