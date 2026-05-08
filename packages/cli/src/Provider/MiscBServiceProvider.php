<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\InstallHandler;
use Waaseyaa\CLI\Handler\RouteListHandler;
use Waaseyaa\CLI\Handler\ServeHandler;
use Waaseyaa\CLI\Handler\SyncRulesHandler;
use Waaseyaa\CLI\Handler\WaaseyaaVersionHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MiscBServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        $projectRoot = $this->projectRoot !== '' ? $this->projectRoot : (string) getcwd();

        yield new CommandDefinition(
            name: 'install',
            description: 'Install Waaseyaa with initial configuration',
            options: [
                new OptionDefinition(
                    name: 'site-name',
                    mode: OptionMode::Required,
                    description: 'The name of the site',
                    default: 'Waaseyaa',
                ),
                new OptionDefinition(
                    name: 'site-mail',
                    mode: OptionMode::Required,
                    description: 'Site email address',
                    default: 'admin@example.com',
                ),
                new OptionDefinition(
                    name: 'admin-email',
                    mode: OptionMode::Required,
                    description: 'Admin user email',
                    default: 'admin@example.com',
                ),
                new OptionDefinition(
                    name: 'admin-password',
                    mode: OptionMode::Required,
                    description: 'Admin user password',
                ),
            ],
            handler: function (\Waaseyaa\CLI\CliIO $io): int {
                /** @var \Waaseyaa\Config\ConfigManagerInterface $configManager */
                $configManager = $this->resolve(\Waaseyaa\Config\ConfigManagerInterface::class);
                /** @var \Waaseyaa\Entity\EntityTypeManagerInterface $entityTypeManager */
                $entityTypeManager = $this->resolve(\Waaseyaa\Entity\EntityTypeManagerInterface::class);

                return (new InstallHandler(
                    entityTypeManager: $entityTypeManager,
                    configManager: $configManager,
                ))->execute($io);
            },
        );

        yield new CommandDefinition(
            name: 'route:list',
            description: 'List all registered routes',
            options: [
                new OptionDefinition(
                    name: 'path',
                    mode: OptionMode::Required,
                    description: 'Filter routes by path pattern',
                ),
            ],
            handler: function (\Waaseyaa\CLI\CliIO $io): int {
                /** @var \Waaseyaa\Routing\WaaseyaaRouter $router */
                $router = $this->resolve(\Waaseyaa\Routing\WaaseyaaRouter::class);

                return (new RouteListHandler(router: $router))->execute($io);
            },
        );

        yield new CommandDefinition(
            name: 'serve',
            description: 'Start the PHP development server',
            options: [
                new OptionDefinition(
                    name: 'host',
                    mode: OptionMode::Optional,
                    description: 'Specify which IP address the server should listen on. Set to 127.0.0.1 to restrict to localhost only. Can also be set via APP_HOST.',
                    default: (getenv('APP_HOST') !== false ? getenv('APP_HOST') : '0.0.0.0'),
                ),
                new OptionDefinition(
                    name: 'port',
                    shortcut: 'p',
                    mode: OptionMode::Optional,
                    description: 'Specify which port the server should listen on. Can also be set via APP_PORT.',
                    default: (getenv('APP_PORT') !== false ? getenv('APP_PORT') : '8080'),
                ),
            ],
            handler: \Closure::fromCallable([new ServeHandler(projectRoot: $projectRoot), 'execute']),
        );

        $rulesSourceDir = $projectRoot . '/vendor/waaseyaa/framework/.claude/rules';
        $rulesTargetDir = $projectRoot . '/.claude/rules';

        yield new CommandDefinition(
            name: 'sync-rules',
            description: 'Sync framework rules from Waaseyaa to this app',
            options: [
                new OptionDefinition(
                    name: 'force',
                    shortcut: 'f',
                    mode: OptionMode::None,
                    description: 'Overwrite changed files without confirmation',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Show what would change without writing',
                ),
            ],
            handler: \Closure::fromCallable([new SyncRulesHandler(
                sourceDir: $rulesSourceDir,
                targetDir: $rulesTargetDir,
            ), 'execute']),
        );

        yield new CommandDefinition(
            name: 'waaseyaa:version',
            description: 'Print waaseyaa/* framework provenance (path SHA, lockfile versions, drift vs golden SHA)',
            options: [
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Machine-readable JSON',
                ),
                new OptionDefinition(
                    name: 'strict',
                    mode: OptionMode::None,
                    description: 'Fail on drift when golden SHA is set (same as default; omit --report-only)',
                ),
                new OptionDefinition(
                    name: 'report-only',
                    mode: OptionMode::None,
                    description: 'Print drift but always exit 0',
                ),
            ],
            handler: \Closure::fromCallable([new WaaseyaaVersionHandler(projectRoot: $projectRoot), 'execute']),
        );
    }
}
