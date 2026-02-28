<?php

declare(strict_types=1);

namespace Aurora\SSR;

final class SsrController
{
    public function __construct(
        private readonly ComponentRenderer $renderer,
    ) {}

    /**
     * Render a named component with props and return an SsrResponse.
     *
     * @param array<string, mixed> $props
     */
    public function render(string $componentName, array $props = []): SsrResponse
    {
        $html = $this->renderer->render($componentName, $props);

        return new SsrResponse(content: $html);
    }

    /**
     * Render a component object and return an SsrResponse.
     */
    public function renderObject(object $component): SsrResponse
    {
        $html = $this->renderer->renderObject($component);

        return new SsrResponse(content: $html);
    }
}
