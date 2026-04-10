<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\Cast\FromArrayEntityValueInterface;

final class CastPersistenceOuterVo implements FromArrayEntityValueInterface
{
    public function __construct(
        public readonly CastPersistenceLeafVo $leaf,
    ) {}

    public static function fromArray(array $data): static
    {
        $leafRaw = $data['leaf'] ?? [];
        if (!is_array($leafRaw)) {
            $leafRaw = [];
        }

        return new self(leaf: CastPersistenceLeafVo::fromArray($leafRaw));
    }

    public function toArray(): array
    {
        return [
            'leaf' => $this->leaf->toArray(),
        ];
    }
}
