<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Controller;

use Waaseyaa\AI\Agent\Enum\HitlMode;

/**
 * Validate the JSON body of {@see AgentRunController} endpoints.
 *
 * - POST /api/ai/agent/run — exactly one of `agent_id` or `bundle`,
 *   optional `params`, `destructive_approval`, `dry_run`. Bundle shape
 *   is validated per `contracts/agent-run-api.yaml` § InlineBundle.
 * - POST /api/ai/agent/run/{id}/approve — `{ call_id, decision }` with
 *   `decision ∈ {approve, deny}`.
 *
 * Returns `null` on success, or a structured error array shaped like:
 *
 *     [
 *       'error_code'    => 'validation_failed',
 *       'error_message' => '...',
 *       'details'       => ['field_errors' => [['field' => '...', 'message' => '...'], ...]],
 *     ]
 *
 * Controllers wrap this into a 400 JSON response. The validator does
 * NOT perform capability checks (those are route-level) or `call_id`
 * matching against the persisted `pending_approval_call_id` (that is
 * the controller's job because it requires loading the row).
 *
 * @api
 */
final class AgentRunRequestValidator
{
    private const VALID_HITL_MODES = ['none', 'all', 'interactive'];

    /**
     * Validate POST /api/ai/agent/run body.
     *
     * @param mixed $body Decoded JSON body (expected `array<string,mixed>`).
     * @return array{error_code: string, error_message: string, details: array{field_errors: list<array{field: string, message: string}>}}|null
     */
    public function validateCreate(mixed $body): ?array
    {
        if (!\is_array($body)) {
            return $this->fail('Request body must be a JSON object.', []);
        }

        $errors = [];

        $hasAgentId = \array_key_exists('agent_id', $body);
        $hasBundle = \array_key_exists('bundle', $body);

        if (!$hasAgentId && !$hasBundle) {
            $errors[] = ['field' => 'agent_id|bundle', 'message' => 'Exactly one of agent_id or bundle is required.'];
        } elseif ($hasAgentId && $hasBundle) {
            $errors[] = ['field' => 'agent_id|bundle', 'message' => 'agent_id and bundle are mutually exclusive.'];
        }

        if ($hasAgentId && !\is_string($body['agent_id'])) {
            $errors[] = ['field' => 'agent_id', 'message' => 'agent_id must be a string.'];
        } elseif ($hasAgentId && \is_string($body['agent_id']) && \trim($body['agent_id']) === '') {
            $errors[] = ['field' => 'agent_id', 'message' => 'agent_id must not be empty.'];
        }

        if ($hasBundle) {
            if (!\is_array($body['bundle'])) {
                $errors[] = ['field' => 'bundle', 'message' => 'bundle must be an object.'];
            } else {
                $errors = [...$errors, ...$this->validateBundle($body['bundle'])];
            }
        }

        if (\array_key_exists('destructive_approval', $body)) {
            $mode = $body['destructive_approval'];
            if (!\is_string($mode) || !\in_array($mode, self::VALID_HITL_MODES, strict: true)) {
                $errors[] = [
                    'field' => 'destructive_approval',
                    'message' => 'destructive_approval must be one of: ' . \implode(', ', self::VALID_HITL_MODES) . '.',
                ];
            }
        }

        if (\array_key_exists('params', $body) && !\is_array($body['params'])) {
            $errors[] = ['field' => 'params', 'message' => 'params must be an object.'];
        }

        if (\array_key_exists('dry_run', $body) && !\is_bool($body['dry_run'])) {
            $errors[] = ['field' => 'dry_run', 'message' => 'dry_run must be a boolean.'];
        }

        if ($errors !== []) {
            return $this->fail('Validation failed for createAgentRun.', $errors);
        }

        return null;
    }

    /**
     * Validate POST /api/ai/agent/run/{id}/approve body.
     *
     * @param mixed $body Decoded JSON body.
     * @return array{error_code: string, error_message: string, details: array{field_errors: list<array{field: string, message: string}>}}|null
     */
    public function validateApprove(mixed $body): ?array
    {
        if (!\is_array($body)) {
            return $this->fail('Request body must be a JSON object.', []);
        }

        $errors = [];

        if (!\array_key_exists('call_id', $body)) {
            $errors[] = ['field' => 'call_id', 'message' => 'call_id is required.'];
        } elseif (!\is_string($body['call_id']) || \trim($body['call_id']) === '') {
            $errors[] = ['field' => 'call_id', 'message' => 'call_id must be a non-empty string.'];
        }

        if (!\array_key_exists('decision', $body)) {
            $errors[] = ['field' => 'decision', 'message' => 'decision is required.'];
        } elseif (!\is_string($body['decision']) || !\in_array($body['decision'], ['approve', 'deny'], strict: true)) {
            $errors[] = ['field' => 'decision', 'message' => 'decision must be "approve" or "deny".'];
        }

        if ($errors !== []) {
            return $this->fail('Validation failed for approveAgentRun.', $errors);
        }

        return null;
    }

    /**
     * Resolve the effective destructive-approval mode for a POST /run body.
     *
     * Precedence: explicit body field → agent definition default →
     * conservative {@see HitlMode::None}. Pass `$agentDefault` as `null`
     * when the request used an inline bundle or when the named agent
     * has no `destructiveDefault`.
     */
    public function resolveDestructiveApproval(array $body, ?HitlMode $agentDefault): HitlMode
    {
        if (\array_key_exists('destructive_approval', $body) && \is_string($body['destructive_approval'])) {
            return HitlMode::from($body['destructive_approval']);
        }

        return $agentDefault ?? HitlMode::None;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function validateBundle(array $bundle): array
    {
        $errors = [];

        foreach (['prompt', 'model'] as $required) {
            if (!\array_key_exists($required, $bundle)) {
                $errors[] = ['field' => "bundle.{$required}", 'message' => "bundle.{$required} is required."];
            } elseif (!\is_string($bundle[$required]) || \trim($bundle[$required]) === '') {
                $errors[] = ['field' => "bundle.{$required}", 'message' => "bundle.{$required} must be a non-empty string."];
            }
        }

        if (!\array_key_exists('tools', $bundle)) {
            $errors[] = ['field' => 'bundle.tools', 'message' => 'bundle.tools is required.'];
        } elseif (!\is_array($bundle['tools'])) {
            $errors[] = ['field' => 'bundle.tools', 'message' => 'bundle.tools must be an array of tool names.'];
        } else {
            foreach ($bundle['tools'] as $i => $tool) {
                if (!\is_string($tool) || $tool === '') {
                    $errors[] = ['field' => "bundle.tools[{$i}]", 'message' => 'tool name must be a non-empty string.'];
                }
            }
        }

        if (\array_key_exists('system', $bundle) && !\is_string($bundle['system'])) {
            $errors[] = ['field' => 'bundle.system', 'message' => 'bundle.system must be a string.'];
        }

        if (\array_key_exists('max_iterations', $bundle)) {
            $max = $bundle['max_iterations'];
            if (!\is_int($max) || $max < 1 || $max > 100) {
                $errors[] = ['field' => 'bundle.max_iterations', 'message' => 'bundle.max_iterations must be an integer between 1 and 100.'];
            }
        }

        return $errors;
    }

    /**
     * @param list<array{field: string, message: string}> $fieldErrors
     * @return array{error_code: string, error_message: string, details: array{field_errors: list<array{field: string, message: string}>}}
     */
    private function fail(string $message, array $fieldErrors): array
    {
        return [
            'error_code' => 'validation_failed',
            'error_message' => $message,
            'details' => ['field_errors' => $fieldErrors],
        ];
    }
}
