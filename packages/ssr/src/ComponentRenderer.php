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
     * @throws \RuntimeException If component not found.
     */
    public function render(string $name, array $props = []): string
    {
        $metadata = $this->registry->get($name);
        if ($metadata === null) {
            throw new \RuntimeException(sprintf('Component "%s" not found.', $name));
        }

        return $this->twig->render($metadata->template, $props);
    }

    /**
     * Render a component object (extracts public properties as template vars).
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

        // Extract public properties as template variables.
        $props = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $props[$prop->getName()] = $prop->getValue($component);
        }

        return $this->twig->render($attr->template, $props);
    }
}
