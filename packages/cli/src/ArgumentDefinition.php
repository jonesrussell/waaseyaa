<?php

declare(strict_types=1);

namespace Waaseyaa\Cli;

use Waaseyaa\CLI\Exception\InvalidArgumentDefinitionException;

final readonly class ArgumentDefinition
{
    public string|int|float|bool|array|null $default;

    public bool $isArray;

    public function __construct(
        public string $name,
        public ArgumentMode $mode = ArgumentMode::Optional,
        public string $description = '',
        string|int|float|bool|array|null $default = null,
        bool $isArray = false,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentDefinitionException(
                sprintf(
                    'Argument name "%s" is invalid. It must match /^[a-z][a-z0-9_]*$/.',
                    $name,
                ),
            );
        }

        if ($mode === ArgumentMode::Required && !$isArray && $default !== null) {
            throw new InvalidArgumentDefinitionException(
                sprintf(
                    'Argument "%s" is required (non-array) so its default must be null, got %s.',
                    $name,
                    var_export($default, true),
                ),
            );
        }

        // Array argument: default normalised to [] if null
        if ($isArray && $default === null) {
            $default = [];
        }

        $this->default = $default;
        $this->isArray = $isArray;
    }
}
