<?php

declare(strict_types=1);

namespace Waaseyaa\Queue;

/**
 * @api
 */
final class InMemoryQueue implements QueueInterface
{
    /** @var object[] */
    private array $messages = [];

    public function dispatch(object $message): void
    {
        $this->messages[] = $message;
    }

    /** @return object[] */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
