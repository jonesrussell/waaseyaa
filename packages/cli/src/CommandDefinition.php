<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

use Waaseyaa\CLI\Exception\InvalidCommandDefinitionException;

final class CommandDefinition
{
    /** @var list<ArgumentDefinition> */
    public readonly array $arguments;

    /** @var list<OptionDefinition> */
    public readonly array $options;

    /**
     * Normalised handler. When the original input was a [ClassFqn, method] array,
     * this closure throws LogicException until CliKernel wraps it with a container
     * resolver in WP04. When the original was a \Closure, it is stored as-is.
     *
     * @var \Closure(CliIO): int
     */
    public readonly \Closure $handler;

    /**
     * Raw [ClassFqn, method] pair stored so CliKernel can inject the DI container
     * at dispatch time. Null when the handler was provided as a \Closure directly.
     *
     * @var array{class-string, non-empty-string}|null
     */
    public readonly ?array $handlerReference;

    /**
     * @param list<ArgumentDefinition>                   $arguments
     * @param list<OptionDefinition>                     $options
     * @param \Closure|array{class-string, string}|null  $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        array $arguments = [],
        array $options = [],
        \Closure|array|null $handler = null,
    ) {
        $this->validateName($name);
        $this->validateArguments($arguments);
        $this->validateOptions($options);

        $this->arguments = array_values($arguments);
        $this->options = array_values($options);

        [$this->handler, $this->handlerReference] = $this->normaliseHandler($handler);
    }

    private function validateName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*(:[a-z][a-z0-9-]*)*$/', $name)) {
            throw new InvalidCommandDefinitionException(
                sprintf(
                    'Command name "%s" is invalid. It must match /^[a-z][a-z0-9-]*(:[a-z][a-z0-9-]*)*$/.',
                    $name,
                ),
            );
        }
    }

    /**
     * @param list<ArgumentDefinition> $arguments
     */
    private function validateArguments(array $arguments): void
    {
        $seen = [];
        $foundOptional = false;
        $arrayCount = 0;
        $arrayIndex = -1;

        foreach ($arguments as $index => $arg) {
            if (!$arg instanceof ArgumentDefinition) {
                throw new InvalidCommandDefinitionException(
                    'Each argument must be an instance of ArgumentDefinition.',
                );
            }

            if (isset($seen[$arg->name])) {
                throw new InvalidCommandDefinitionException(
                    sprintf('Duplicate argument name "%s".', $arg->name),
                );
            }
            $seen[$arg->name] = true;

            if ($arg->isArray) {
                ++$arrayCount;
                $arrayIndex = $index;
            }

            if ($arrayCount > 1) {
                throw new InvalidCommandDefinitionException(
                    'At most one array-mode argument is allowed.',
                );
            }

            // Required argument may not follow optional argument
            if ($arg->mode === ArgumentMode::Required && $foundOptional) {
                throw new InvalidCommandDefinitionException(
                    sprintf(
                        'Required argument "%s" may not follow an optional argument.',
                        $arg->name,
                    ),
                );
            }

            if ($arg->mode === ArgumentMode::Optional || $arg->isArray) {
                $foundOptional = true;
            }
        }

        // Array argument, if present, must be last
        if ($arrayCount === 1 && $arrayIndex !== count($arguments) - 1) {
            throw new InvalidCommandDefinitionException(
                'The array-mode argument must be the last argument.',
            );
        }
    }

    /**
     * @param list<OptionDefinition> $options
     */
    private function validateOptions(array $options): void
    {
        $seenNames = [];
        $seenShortcuts = [];

        foreach ($options as $opt) {
            if (!$opt instanceof OptionDefinition) {
                throw new InvalidCommandDefinitionException(
                    'Each option must be an instance of OptionDefinition.',
                );
            }

            if (isset($seenNames[$opt->name])) {
                throw new InvalidCommandDefinitionException(
                    sprintf('Duplicate option name "%s".', $opt->name),
                );
            }
            $seenNames[$opt->name] = true;

            if ($opt->shortcut !== null) {
                if (isset($seenShortcuts[$opt->shortcut])) {
                    throw new InvalidCommandDefinitionException(
                        sprintf('Duplicate option shortcut "%s".', $opt->shortcut),
                    );
                }
                $seenShortcuts[$opt->shortcut] = true;
            }
        }
    }

    /**
     * @param \Closure|array{class-string, string}|null $raw
     * @return array{\Closure(CliIO): int, array{class-string, non-empty-string}|null}
     */
    private function normaliseHandler(\Closure|array|null $raw): array
    {
        if ($raw === null) {
            throw new InvalidCommandDefinitionException(
                sprintf('Command "%s" must have a handler.', $this->name),
            );
        }

        if ($raw instanceof \Closure) {
            return [$raw, null];
        }

        // Array handler: [ClassFqn, 'methodName']
        if (count($raw) !== 2) {
            throw new InvalidCommandDefinitionException(
                'Handler array must be a 2-element [ClassFqn, methodName] pair.',
            );
        }

        [$fqn, $method] = array_values($raw);

        if (!is_string($fqn) || !is_string($method) || $fqn === '' || $method === '') {
            throw new InvalidCommandDefinitionException(
                'Handler array elements must be non-empty strings: [ClassFqn, methodName].',
            );
        }

        // Deferred closure: resolved by CliKernel via DI container in WP04.
        $deferred = static function (CliIO $io) use ($fqn, $method): int {
            throw new \LogicException(
                sprintf(
                    'Handler [%s::%s] requires a DI container. '
                    . 'Dispatch via CliKernel or provide a \\Closure handler for isolated tests.',
                    $fqn,
                    $method,
                ),
            );
        };

        /** @var array{class-string, non-empty-string} $reference */
        $reference = [$fqn, $method];

        return [$deferred, $reference];
    }
}
