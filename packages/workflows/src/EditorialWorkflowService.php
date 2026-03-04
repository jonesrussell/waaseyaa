<?php

declare(strict_types=1);

namespace Waaseyaa\Workflows;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\FieldableInterface;

final class EditorialWorkflowService
{
    /**
     * @param list<string> $coreBundles
     */
    public function __construct(
        private readonly array $coreBundles,
    ) {}

    /**
     * @param FieldableInterface&\Waaseyaa\Entity\EntityInterface $node
     */
    public function transitionNode(FieldableInterface $node, string $toState, AccountInterface $account): void
    {
        $bundle = (string) ($node->get('type') ?? '');
        if (!in_array($bundle, $this->coreBundles, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Workflow transition is only supported for configured bundles. Got: "%s".',
                $bundle,
            ));
        }

        $to = strtolower(trim($toState));
        if (!in_array($to, ['draft', 'review', 'published'], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown workflow state: "%s".', $toState));
        }

        $from = $this->stateFromNode($node);
        if ($from === $to) {
            return;
        }

        $allowed = [
            'draft' => ['review'],
            'review' => ['draft', 'published'],
            'published' => ['draft'],
        ];
        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new \RuntimeException(sprintf(
                'Invalid workflow transition for "%s": %s -> %s.',
                $bundle,
                $from,
                $to,
            ));
        }

        $requiredPermission = match ("{$from}:{$to}") {
            'draft:review' => "submit {$bundle} for review",
            'review:published' => "publish {$bundle} content",
            'review:draft' => "return {$bundle} to draft",
            'published:draft' => "revert {$bundle} to draft",
            default => '',
        };

        if ($requiredPermission !== ''
            && !$account->hasPermission('administer nodes')
            && !$account->hasPermission($requiredPermission)) {
            throw new \RuntimeException(sprintf(
                'Permission denied for workflow transition %s -> %s on "%s". Required: "%s".',
                $from,
                $to,
                $bundle,
                $requiredPermission,
            ));
        }

        $node->set('workflow_state', $to);
        $node->set('status', $to === 'published' ? 1 : 0);

        $audit = $node->get('workflow_audit');
        if (!is_array($audit)) {
            $audit = [];
        }
        $audit[] = [
            'from' => $from,
            'to' => $to,
            'uid' => (string) $account->id(),
            'at' => time(),
        ];
        $node->set('workflow_audit', $audit);
    }

    private function stateFromNode(FieldableInterface $node): string
    {
        $state = $node->get('workflow_state');
        if (is_string($state) && trim($state) !== '') {
            return strtolower(trim($state));
        }

        $status = $node->get('status');
        if (is_numeric($status) && (int) $status === 1) {
            return 'published';
        }
        if (is_bool($status) && $status) {
            return 'published';
        }

        return 'draft';
    }
}
