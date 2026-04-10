<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Cast\Fixture;

use Waaseyaa\Entity\Cast\FromArrayEntityValueInterface;

final class SampleValueObject implements FromArrayEntityValueInterface
{
    public function __construct(
        public readonly string $title = '',
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(title: (string) ($data['title'] ?? ''));
    }

    public function toArray(): array
    {
        return ['title' => $this->title];
    }
}
