<?php

declare(strict_types=1);

namespace Aurora\SSR;

use Twig\Environment;

final class ComponentRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ComponentRegistry $registry,
    ) {}

    /**
     * Render a component by name with given properties.
     *
     * @param string $name Component name (from #[Component] attribute).
     * @param array<string, mixed> $props Template variables.
     * @return string Rendered HTML.
     * @throws \RuntimeException If component not found or rendering fails.
     */
    public function render(string $name, array $props = []): string
    {
        $metadata = $this->registry->get($name);
        if ($metadata === null) {
            throw new \RuntimeException(sprintf('Component "%s" not found.', $name));
        }

        try {
            return $this->twig->render($metadata->template, $props);
        } catch (\Twig\Error\Error $e) {
            throw new \RuntimeException(sprintf(
                'Failed to render component "%s" (template: %s): %s',
                $name,
                $metadata->template,
                $e->getMessage(),
            ), previous: $e);
        }
    }

    /**
     * Render a component object (extracts public properties as template vars).
     *
     * Uses the registry to resolve the template, ensuring overrides are respected.
     */
    public function renderObject(object $component): string
    {
        $reflection = new \ReflectionClass($component);
        $attributes = $reflection->getAttributes(Attribute\Component::class);

        if ($attributes === []) {
            throw new \RuntimeException(sprintf(
                'Object of class %s does not have a #[Component] attribute.',
                $component::class,
            ));
        }

        $attr = $attributes[0]->newInstance();

        // Use the registry to resolve the template, not the attribute directly.
        $metadata = $this->registry->get($attr->name);
        if ($metadata === null) {
            throw new \RuntimeException(sprintf(
                'Component "%s" not found in registry.',
                $attr->name,
            ));
        }

        // Extract public properties as template variables.
        $props = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $props[$prop->getName()] = $prop->getValue($component);
        }

        try {
            return $this->twig->render($metadata->template, $props);
        } catch (\Twig\Error\Error $e) {
            throw new \RuntimeException(sprintf(
                'Failed to render component "%s" (template: %s): %s',
                $attr->name,
                $metadata->template,
                $e->getMessage(),
            ), previous: $e);
        }
    }
}
