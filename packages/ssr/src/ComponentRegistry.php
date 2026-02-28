<?php

declare(strict_types=1);

namespace Aurora\SSR;

final class ComponentRegistry
{
    /** @var array<string, ComponentMetadata> */
    private array $components = [];

    public function register(ComponentMetadata $metadata): void
    {
        if (isset($this->components[$metadata->name])) {
            throw new \LogicException(sprintf(
                'Component "%s" is already registered (class: %s).',
                $metadata->name,
                $this->components[$metadata->name]->className,
            ));
        }

        $this->components[$metadata->name] = $metadata;
    }

    public function get(string $name): ?ComponentMetadata
    {
        return $this->components[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->components[$name]);
    }

    /** @return ComponentMetadata[] */
    public function all(): array
    {
        return array_values($this->components);
    }

    /**
     * Register a component class by reading its #[Component] attribute.
     */
    public function registerClass(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes(Attribute\Component::class);

        if ($attributes === []) {
            throw new \InvalidArgumentException(
                sprintf('Class %s does not have a #[Component] attribute.', $className),
            );
        }

        $attr = $attributes[0]->newInstance();
        $this->register(new ComponentMetadata(
            name: $attr->name,
            template: $attr->template,
            className: $className,
        ));
    }
}
