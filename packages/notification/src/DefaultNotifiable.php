<?php

declare(strict_types=1);

namespace Waaseyaa\Notification;

/**
 * Lightweight {@see NotifiableInterface} for tests and apps that do not use
 * {@see \Waaseyaa\Entity\EntityBase}. Uses {@see NotifiableTrait}.
 */
final class DefaultNotifiable implements NotifiableInterface
{
    use NotifiableTrait;

    /**
     * @param array<string, mixed> $values Keys used by {@see NotifiableTrait::routeNotificationFor} (e.g. mail, email).
     */
    public function __construct(
        private readonly string $entityTypeId,
        private readonly int|string $notifiableId,
        private readonly array $values = [],
    ) {}

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function id(): int|string
    {
        return $this->notifiableId;
    }

    public function getEntityTypeId(): string
    {
        return $this->entityTypeId;
    }
}
