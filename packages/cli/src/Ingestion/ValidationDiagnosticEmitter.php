<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Ingestion;

final class ValidationDiagnosticEmitter
{
    /**
     * @param list<array<string, mixed>> $violations
     * @return list<array<string, mixed>>
     */
    public function emit(array $violations): array
    {
        $diagnostics = [];
        foreach ($violations as $violation) {
            $code = (string) ($violation['code'] ?? 'validation.unknown');
            $location = (string) ($violation['location'] ?? '');
            $context = $this->buildContext($violation);

            $diagnostics[] = [
                'code' => $code,
                'category' => $this->resolveCategory($code),
                'message' => $this->renderMessage($code, $violation),
                'location' => $location,
                'item_index' => array_key_exists('item_index', $violation) ? $violation['item_index'] : null,
                'context' => $context,
            ];
        }

        usort(
            $diagnostics,
            static function (array $left, array $right): int {
                $leftCategory = (string) ($left['category'] ?? '');
                $rightCategory = (string) ($right['category'] ?? '');
                if ($leftCategory !== $rightCategory) {
                    return strcmp($leftCategory, $rightCategory);
                }

                $leftLocation = (string) ($left['location'] ?? '');
                $rightLocation = (string) ($right['location'] ?? '');
                if ($leftLocation !== $rightLocation) {
                    return strcmp($leftLocation, $rightLocation);
                }

                return strcmp((string) ($left['code'] ?? ''), (string) ($right['code'] ?? ''));
            },
        );

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $violation
     * @return array<string, scalar|array<array-key, scalar>|null>
     */
    private function buildContext(array $violation): array
    {
        $context = [];
        foreach (['value', 'expected', 'workflow_state', 'node_key', 'relationship_index', 'from_key', 'to_key', 'remediation'] as $key) {
            if (array_key_exists($key, $violation)) {
                $context[$key] = $violation[$key];
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $violation
     */
    private function renderMessage(string $code, array $violation): string
    {
        return match ($code) {
            'validation.workflow.unknown_state' => sprintf(
                'Unknown workflow_state "%s". Allowed states: %s.',
                (string) ($violation['value'] ?? ''),
                implode(', ', array_map(static fn(mixed $state): string => (string) $state, (array) ($violation['expected'] ?? []))),
            ),
            'validation.workflow.status_state_mismatch' => sprintf(
                'Status "%s" does not match workflow_state "%s" (expected "%s").',
                (string) ($violation['value'] ?? ''),
                (string) ($violation['workflow_state'] ?? ''),
                (string) ($violation['expected'] ?? ''),
            ),
            'validation.semantic.missing_publishable_body' => 'Published nodes require non-empty body content.',
            'validation.semantic.insufficient_publishable_tokens' => sprintf(
                'Published node body has "%s" tokens, minimum required is "%s".',
                (string) ($violation['value'] ?? ''),
                (string) ($violation['expected'] ?? ''),
            ),
            'validation.visibility.missing_relationship_endpoint' => sprintf(
                'Relationship endpoint "%s" is missing from ingested nodes.',
                (string) ($violation['value'] ?? ''),
            ),
            'validation.visibility.relationship_requires_public_endpoints' => sprintf(
                'Published relationship "%s -> %s" requires both endpoints to be published.',
                (string) ($violation['from_key'] ?? ''),
                (string) ($violation['to_key'] ?? ''),
            ),
            default => (string) ($violation['message'] ?? 'Validation gate failed.'),
        };
    }

    private function resolveCategory(string $code): string
    {
        $parts = explode('.', $code);
        if (count($parts) >= 2) {
            return strtolower(trim((string) $parts[1]));
        }

        return 'unknown';
    }
}
