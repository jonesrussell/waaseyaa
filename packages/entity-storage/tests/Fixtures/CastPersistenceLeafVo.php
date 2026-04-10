<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\Cast\FromArrayEntityValueInterface;

final class CastPersistenceLeafVo implements FromArrayEntityValueInterface
{
    public function __construct(
        public readonly string $code = '',
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(code: (string) ($data['code'] ?? ''));
    }

    public function toArray(): array
    {
        return ['code' => $this->code];
    }
}
