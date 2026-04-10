<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'make:entity-type',
    description: 'Generate an entity type class',
)]
class MakeEntityTypeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The entity type name (e.g. "event")')
            ->addOption('content', null, InputOption::VALUE_NONE, 'Generate a content entity (default is config entity)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $isContent = (bool) $input->getOption('content');

        $className = str_replace('_', '', ucwords($name, '_'));
        $baseClass = $isContent ? 'ContentEntityBase' : 'ConfigEntityBase';
        $baseImport = $isContent
            ? 'use Waaseyaa\Entity\ContentEntityBase;'
            : 'use Waaseyaa\Entity\ConfigEntityBase;';

        $hydrationUse = $isContent
            ? "use Waaseyaa\\Entity\\Hydration\\HydratableFromStorageInterface;\nuse Waaseyaa\\Entity\\Hydration\\HydrationContext;"
            : '';

        $implements = $isContent ? ' implements HydratableFromStorageInterface' : '';

        $extraBody = '';
        if ($isContent) {
            $extraBody = <<<'PHP'

    /**
     * Widen the constructor so {@see \Waaseyaa\Entity\ContentEntityBase::duplicateInstance()} can reconstruct instances.
     * Replace the defaults below with your entity type id and key map (must match EntityType registration).
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : 'CHANGE_ME';
        $entityKeys = $entityKeys !== [] ? $entityKeys : [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'label',
        ];

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        return new self($values);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return new self(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
    }

    protected function duplicateInstance(array $values): static
    {
        return new static(
            values: $values,
            entityTypeId: $this->getEntityTypeId(),
            entityKeys: $this->entityKeys,
            fieldDefinitions: $this->getFieldDefinitions(),
        );
    }
PHP;
        } else {
            $extraBody = <<<'PHP'

    /**
     * Widen the constructor so {@see \Waaseyaa\Entity\EntityBase::duplicateInstance()} can reconstruct instances.
     * Replace the defaults below with your entity type id and key map (must match EntityType registration).
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
    ) {
        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : 'CHANGE_ME';
        $entityKeys = $entityKeys !== [] ? $entityKeys : [
            'id' => 'id',
            'label' => 'label',
        ];

        parent::__construct($values, $entityTypeId, $entityKeys);
    }
PHP;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Entity;

{$baseImport}
{$hydrationUse}

class {$className} extends {$baseClass}{$implements}
{
{$extraBody}
}

PHP;

        $output->write($template);

        return Command::SUCCESS;
    }
}
