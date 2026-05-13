<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\PluginFixtures;

use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;

/**
 * Process plugin that always raises {@see ProcessException}.
 * Used by Runner tests to exercise the per-record process-error path.
 */
final class AlwaysFailingProcessor implements ProcessPluginInterface
{
    public function __construct(
        public readonly string $sourceField = 'failing_field',
        public readonly string $processCode = 'TEST_FAILURE',
    ) {}

    public function id(): string
    {
        return 'test_failing';
    }

    public function stability(): string
    {
        return 'experimental';
    }

    public function transform(mixed $value, ProcessContext $context): mixed
    {
        throw new ProcessException(
            processCode: $this->processCode,
            sourceField: $this->sourceField,
            migrationId: $context->migrationId,
            message: 'AlwaysFailingProcessor: synthetic failure.',
        );
    }
}
