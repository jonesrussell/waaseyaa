<?php

declare(strict_types=1);

namespace Waaseyaa\Workflows;

final class AuthoringRoleMatrix
{
    /**
     * @param list<string> $coreBundles
     */
    public function __construct(
        private readonly array $coreBundles,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function matrix(): array
    {
        $contributorPermissions = ['access content', 'view own unpublished content'];
        foreach ($this->coreBundles as $bundle) {
            $contributorPermissions[] = "create {$bundle} content";
            $contributorPermissions[] = "edit own {$bundle} content";
            $contributorPermissions[] = "delete own {$bundle} content";
            $contributorPermissions[] = "submit {$bundle} for review";
        }

        $editorPermissions = $contributorPermissions;
        foreach ($this->coreBundles as $bundle) {
            $editorPermissions[] = "edit any {$bundle} content";
            $editorPermissions[] = "delete any {$bundle} content";
            $editorPermissions[] = "publish {$bundle} content";
            $editorPermissions[] = "return {$bundle} to draft";
            $editorPermissions[] = "revert {$bundle} to draft";
        }
        array_push($editorPermissions, 'create relationship content', 'edit any relationship content', 'delete any relationship content');

        $reviewerPermissions = ['access content'];
        foreach ($this->coreBundles as $bundle) {
            $reviewerPermissions[] = "view {$bundle} moderation queue";
            $reviewerPermissions[] = "publish {$bundle} content";
            $reviewerPermissions[] = "return {$bundle} to draft";
            $reviewerPermissions[] = "revert {$bundle} to draft";
        }

        return [
            'contributor' => [
                'label' => 'Contributor',
                'permissions' => array_values(array_unique($contributorPermissions)),
            ],
            'editor' => [
                'label' => 'Editor',
                'permissions' => array_values(array_unique($editorPermissions)),
            ],
            'reviewer' => [
                'label' => 'Reviewer',
                'permissions' => array_values(array_unique($reviewerPermissions)),
            ],
            'administrator' => [
                'label' => 'Administrator',
                'permissions' => ['administer nodes'],
            ],
        ];
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public function permissionsForRoles(array $roles): array
    {
        $matrix = $this->matrix();
        $permissions = [];

        foreach ($roles as $role) {
            if (!isset($matrix[$role]) || !is_array($matrix[$role]['permissions'] ?? null)) {
                continue;
            }
            array_push($permissions, ...$matrix[$role]['permissions']);
        }

        $permissions = array_values(array_unique(array_map(
            static fn(string $permission): string => trim($permission),
            array_filter($permissions, static fn(mixed $permission): bool => is_string($permission) && trim($permission) !== ''),
        )));
        sort($permissions);

        return $permissions;
    }
}
