<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Foundation\Schema\SchemaRegistryInterface;

#[AsCommand(
    name: 'schema:list',
    description: 'List registered schemas with versions and compatibility policy',
)]
final class SchemaListCommand extends Command
{
    public function __construct(
        private readonly SchemaRegistryInterface $registry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entries = $this->registry->list();

        if ($entries === []) {
            $output->writeln('No schemas found.');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Entity Type', 'Version', 'Compatibility', 'Schema Path']);

        foreach ($entries as $entry) {
            $table->addRow([
                $entry->id,
                $entry->version,
                $entry->compatibility,
                $entry->schemaPath,
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
