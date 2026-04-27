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
        $typeId = strtolower((string) $name);
        $label = ucwords(str_replace('_', ' ', (string) $name));

        $template = $isContent
            ? $this->renderContentTemplate($className, $typeId, $label)
            : $this->renderConfigTemplate($className, $typeId);

        $output->write($template);

        return Command::SUCCESS;
    }

    /**
     * Emit an attribute-first content entity scaffold.
     *
     * The generated class declares fields as `#[Field]`-decorated public typed
     * properties. Register it with `EntityType::fromClass()` in your
     * ServiceProvider — type metadata flows automatically from the attributes.
     */
    private function renderContentTemplate(string $className, string $typeId, string $label): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Entity;

use Waaseyaa\\Entity\\Attribute\\ContentEntityKeys;
use Waaseyaa\\Entity\\Attribute\\ContentEntityType;
use Waaseyaa\\Entity\\Attribute\\Field;
use Waaseyaa\\Entity\\ContentEntityBase;

#[ContentEntityType(id: '{$typeId}', label: '{$label}')]
#[ContentEntityKeys(label: 'title')]
final class {$className} extends ContentEntityBase
{
    #[Field] public string \$title = '';
    // Add additional #[Field] properties as needed.
}

// Register in your ServiceProvider:
//
//     \$this->entityType(EntityType::fromClass({$className}::class, group: 'content'));

PHP;
    }

    /**
     * Emit a config entity scaffold. Config entities continue to use the
     * legacy `new EntityType(...)` registration since attribute reflection
     * applies only to ContentEntityBase subclasses.
     */
    private function renderConfigTemplate(string $className, string $typeId): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Entity;

use Waaseyaa\\Entity\\ConfigEntityBase;

class {$className} extends ConfigEntityBase
{
    /**
     * Widen the constructor so {@see \\Waaseyaa\\Entity\\EntityBase::duplicateInstance()} can reconstruct instances.
     * Replace the defaults below with your entity type id and key map (must match EntityType registration).
     */
    public function __construct(
        array \$values = [],
        string \$entityTypeId = '',
        array \$entityKeys = [],
    ) {
        \$entityTypeId = \$entityTypeId !== '' ? \$entityTypeId : '{$typeId}';
        \$entityKeys = \$entityKeys !== [] ? \$entityKeys : [
            'id' => 'id',
            'label' => 'label',
        ];

        parent::__construct(\$values, \$entityTypeId, \$entityKeys);
    }
}

PHP;
    }
}
