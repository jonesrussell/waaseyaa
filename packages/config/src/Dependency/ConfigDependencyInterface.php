<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Dependency;

/**
 * Declares hard preconditions between config entities for sync import ordering.
 *
 * Implementations are typically config-entity classes (extending
 * `ConfigEntityBase`, when available) that need other config entities to exist
 * before they can be applied to the active store.
 *
 * Examples of a hard dependency:
 *  - A `menu` entity depends on a `taxonomy_vocabulary` it references.
 *  - A `permission` entity depends on the `role` it applies to.
 *  - An `oauth_client` entity depends on a `scope` definition.
 *
 * Soft references (UI hints, search-indexer config, etc.) MUST NOT be returned
 * here; only entities that must already exist when the dependent is imported.
 *
 * Stability scope (charter §5.5): the interface FQCN
 * `Waaseyaa\Config\Dependency\ConfigDependencyInterface`, the method name
 * `configDependencies()`, and its `list<string>` return type are on the stable
 * surface. Adding methods to this interface is a breaking change and requires
 * the charter §4 deprecation cycle.
 *
 * Default behaviour: when a `ConfigEntityBase` ships in a later WP it will
 * provide a no-op default returning `[]` so existing entity classes compile
 * unchanged. Implementations override only when they actually have
 * dependencies.
 *
 * @api
 *
 * @see \Waaseyaa\Config\Dependency\DependencyResolver
 * @see \Waaseyaa\Config\Dependency\DependencyGraph
 */
interface ConfigDependencyInterface
{
    /**
     * Declare config entities this entity depends on.
     *
     * Each entry is a `<entity_type>.<entity_id>` reference to another config
     * entity that MUST exist (in the sync store or the active store) before
     * this one can be imported.
     *
     * The default implementation in `ConfigEntityBase` returns an empty list;
     * entity classes with real dependencies override this method.
     *
     * @return list<string> List of `<entity_type>.<entity_id>` refs.
     *                      Each entry must match `/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/`.
     */
    public function configDependencies(): array;
}
