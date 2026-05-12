<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Gate;

/**
 * Determines whether a user is authorized to perform a given ability on a subject.
 *
 * The Gate resolves the appropriate policy for the subject and delegates
 * the ability check to the matching policy method.
 *
 * @api
 */
interface GateInterface
{
    /**
     * Operation constants for well-known entity access operations.
     *
     * Use these instead of bare strings to avoid typos and enable IDE navigation.
     */
    public const string VIEW = 'view';
    public const string CREATE = 'create';
    public const string UPDATE = 'update';
    public const string DELETE = 'delete';

    /**
     * Per-revision view operation.
     *
     * Policies that declare this op must implement viewRevision(). Policies
     * that do NOT declare it fall back to the entity-level view() check via
     * RevisionAccessRouter — no default-deny. See contracts/revisionable-entity.md §11.2.
     */
    public const string VIEW_REVISION = 'view_revision';

    /**
     * Determine if the given ability is allowed for the user on the subject.
     *
     * @param string  $ability The ability to check (e.g. 'view', 'update', 'delete').
     * @param mixed   $subject The subject being acted upon (typically an entity or entity type string).
     * @param ?object $user    The user performing the action. Null means the current/anonymous user.
     */
    public function allows(string $ability, mixed $subject, ?object $user = null): bool;

    /**
     * Determine if the given ability is denied for the user on the subject.
     *
     * @param string  $ability The ability to check.
     * @param mixed   $subject The subject being acted upon.
     * @param ?object $user    The user performing the action.
     */
    public function denies(string $ability, mixed $subject, ?object $user = null): bool;

    /**
     * Authorize the given ability for the user on the subject, or throw.
     *
     * @param string  $ability The ability to check.
     * @param mixed   $subject The subject being acted upon.
     * @param ?object $user    The user performing the action.
     *
     * @throws AccessDeniedException If the ability is not allowed.
     */
    public function authorize(string $ability, mixed $subject, ?object $user = null): void;
}
