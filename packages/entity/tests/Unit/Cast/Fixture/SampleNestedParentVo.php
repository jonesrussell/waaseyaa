<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Cast\Fixture;

use Waaseyaa\Entity\Cast\FromArrayEntityValueInterface;

final class SampleNestedParentVo implements FromArrayEntityValueInterface
{
    public function __construct(
        public readonly SampleNestedChildVo $child,
    ) {}

    public static function fromArray(array $data): static
    {
        $childRaw = $data['child'] ?? [];
        if (!is_array($childRaw)) {
            $childRaw = [];
        }

        return new self(child: SampleNestedChildVo::fromArray($childRaw));
    }

    public function toArray(): array
    {
        return [
            'child' => $this->child->toArray(),
        ];
    }
}
