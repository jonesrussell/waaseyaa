<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

use Waaseyaa\CLI\Exception\InvalidOptionDefinitionException;

final readonly class OptionDefinition
{
    /** @var list<string> */
    private const RESERVED_NAMES = ['help', 'verbose', 'quiet', 'no-interaction', 'version'];

    /** @var list<string> */
    private const RESERVED_SHORTCUTS = ['h', 'v', 'q'];

    public string|int|float|bool|array|null $default;

    public function __construct(
        public string $name,
        public ?string $shortcut = null,
        public OptionMode $mode = OptionMode::None,
        public string $description = '',
        string|int|float|bool|array|null $default = null,
    ) {
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            throw new InvalidOptionDefinitionException(
                sprintf(
                    'Option name "%s" is invalid. It must match /^[a-z][a-z0-9-]*$/.',
                    $name,
                ),
            );
        }

        if (in_array($name, self::RESERVED_NAMES, true)) {
            throw new InvalidOptionDefinitionException(
                sprintf(
                    'Option name "%s" is reserved by the kernel and cannot be redefined.',
                    $name,
                ),
            );
        }

        if ($shortcut !== null) {
            if (!preg_match('/^[a-zA-Z]$/', $shortcut)) {
                throw new InvalidOptionDefinitionException(
                    sprintf(
                        'Option shortcut "%s" must be exactly one ASCII letter.',
                        $shortcut,
                    ),
                );
            }

            if (in_array($shortcut, self::RESERVED_SHORTCUTS, true)) {
                throw new InvalidOptionDefinitionException(
                    sprintf(
                        'Option shortcut "%s" is reserved by the kernel.',
                        $shortcut,
                    ),
                );
            }
        }

        if ($mode === OptionMode::Negatable && str_starts_with($name, 'no-')) {
            throw new InvalidOptionDefinitionException(
                sprintf(
                    'Negatable option name "%s" must NOT start with "no-".',
                    $name,
                ),
            );
        }

        // Normalise defaults per mode
        if ($mode === OptionMode::Array_) {
            $default = $default ?? [];
        } elseif ($mode === OptionMode::None) {
            $default = false;
        } elseif ($mode === OptionMode::Negatable) {
            $default = null;
        }

        $this->default = $default;
    }
}
