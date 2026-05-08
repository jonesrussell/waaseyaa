<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

/**
 * Provider capability: exposes native CLI commands to the CliKernel.
 *
 * Implement this interface on a ServiceProvider to register commands with the
 * native CLI kernel. Commands are discovered at manifest compile time and wired
 * into the CommandRegistry by CliKernelServiceProvider.
 *
 * Layer placement: Foundation (L0). This interface declares a return type via
 * fully-qualified class name only — no `use` import of the L6 CLI package is
 * needed. The FQN is resolved by the L6 CliKernelServiceProvider at runtime.
 *
 * Full contract: kitty-specs/native-cli-kernel-01KR2NR7/contracts/has-native-commands.md
 */
interface HasNativeCommandsInterface
{
    /**
     * Yield the command definitions provided by this service provider.
     *
     * Called exactly once per process boot during registry construction.
     * Implementations SHOULD be pure (no side effects, idempotent).
     *
     * @return iterable<\Waaseyaa\CLI\CommandDefinition>
     */
    public function nativeCommands(): iterable;
}
