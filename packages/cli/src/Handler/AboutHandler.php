<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;

final class AboutHandler
{
    /**
     * @param array<string, string> $info Key-value pairs of system information.
     */
    public function __construct(
        private readonly array $info = [],
    ) {}

    public function execute(CliIO $io): int
    {
        $info = array_merge($this->getDefaultInfo(), $this->info);

        $io->writeln('Waaseyaa');
        $io->writeln('');

        $maxKey = max(array_map('strlen', array_keys($info)));

        foreach ($info as $key => $value) {
            $io->writeln(sprintf('  %-' . $maxKey . 's  %s', $key, $value));
        }

        return 0;
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultInfo(): array
    {
        return [
            'Waaseyaa Version' => '0.1.0',
            'PHP Version' => PHP_VERSION,
            'Environment' => $_ENV['APP_ENV'] ?? 'production',
            'Debug Mode' => ($_ENV['APP_DEBUG'] ?? '0') === '1' ? 'ON' : 'OFF',
            'Database' => $_ENV['WAASEYAA_DB'] ?? './storage/waaseyaa.sqlite',
            'Config Dir' => $_ENV['WAASEYAA_CONFIG_DIR'] ?? './config/sync',
            'OS' => PHP_OS_FAMILY,
        ];
    }
}
