<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Diagnostic\DiagnosticCode;
use Waaseyaa\Foundation\Diagnostic\DiagnosticEmitter;

final class ContentTypeValidator
{
    /**
     * Validate that at least one content type is registered and enabled.
     *
     * @param list<string> $disabledTypeIds
     *
     * @throws \RuntimeException
     */
    public function validate(EntityTypeManager $entityTypeManager, array $disabledTypeIds): void
    {
        $emitter = new DiagnosticEmitter();
        $definitions = $entityTypeManager->getDefinitions();

        if ($definitions === []) {
            $entry = $emitter->emit(
                DiagnosticCode::DEFAULT_TYPE_MISSING,
                DiagnosticCode::DEFAULT_TYPE_MISSING->defaultMessage(),
                ['registered_type_count' => 0],
            );
            throw new \RuntimeException('[CRITICAL] ' . $entry->code->value . ': ' . $entry->message);
        }

        $enabledTypes = array_filter(
            $definitions,
            static fn(\Waaseyaa\Entity\EntityTypeInterface $def): bool => !in_array($def->id(), $disabledTypeIds, true),
        );

        if ($enabledTypes === []) {
            $entry = $emitter->emit(
                DiagnosticCode::DEFAULT_TYPE_DISABLED,
                DiagnosticCode::DEFAULT_TYPE_DISABLED->defaultMessage(),
                ['disabled_ids' => $disabledTypeIds, 'registered_type_count' => count($definitions)],
            );
            throw new \RuntimeException('[CRITICAL] ' . $entry->code->value . ': ' . $entry->message);
        }
    }
}
