<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

use Waaseyaa\CLI\Exception\DuplicateCommandException;

/**
 * @api
 */
final class CommandRegistry
{
    /** @var array<string, CommandDefinition> */
    private array $commands = [];

    /**
     * Register a command definition.
     *
     * @throws DuplicateCommandException if a command with the same name is already registered.
     */
    public function register(CommandDefinition $command): void
    {
        if (isset($this->commands[$command->name])) {
            throw new DuplicateCommandException(
                sprintf('Command "%s" is already registered.', $command->name),
            );
        }

        $this->commands[$command->name] = $command;
    }

    /**
     * Return the command definition for the given name, or null if not found.
     */
    public function get(string $name): ?CommandDefinition
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * Return all registered commands sorted ASCII-lexically by name.
     *
     * @return array<string, CommandDefinition>
     */
    public function all(): array
    {
        $sorted = $this->commands;
        ksort($sorted);

        return $sorted;
    }

    /**
     * Return a sorted list of all registered command names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->commands);
        sort($names);

        return $names;
    }
}
