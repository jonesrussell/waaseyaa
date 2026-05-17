<?php

declare(strict_types=1);

namespace Waaseyaa\Menu;

/**
 * Value object representing a node in a menu tree.
 *
 * Wraps a MenuLink and holds references to child elements,
 * forming a hierarchical tree structure.
 * @api
 */
final class MenuTreeElement
{
    /** @var MenuTreeElement[] */
    public array $children = [];

    public function __construct(
        public readonly MenuLink $link,
    ) {}

    /**
     * Whether this element has child elements.
     */
    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * Get the maximum depth of the subtree rooted at this element.
     *
     * A leaf node has depth 0. A node with only leaf children has depth 1, etc.
     */
    public function getDepth(): int
    {
        if (!$this->hasChildren()) {
            return 0;
        }

        $maxChildDepth = 0;
        foreach ($this->children as $child) {
            $maxChildDepth = max($maxChildDepth, $child->getDepth());
        }

        return $maxChildDepth + 1;
    }
}
