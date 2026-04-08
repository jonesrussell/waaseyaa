<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

final class EditorLinkGenerator
{
    private readonly string $editorId;

    public function __construct(?string $editor = null)
    {
        $env = getenv('EDITOR');
        $fromEnv = is_string($env) && trim($env) !== '' ? strtolower(trim(explode(' ', $env)[0])) : null;
        $this->editorId = $editor ?? $fromEnv ?? 'vscode';
    }

    public function link(string $absolutePath, int $line): string
    {
        return match ($this->editorId) {
            'phpstorm', 'phpstorm.sh' => sprintf('phpstorm://open?file=%s&line=%d', rawurlencode($absolutePath), $line),
            'subl', 'sublime' => sprintf('subl://open?url=file://%s&line=%d', rawurlencode($absolutePath), $line),
            default => sprintf('vscode://file%s:%d', $absolutePath, $line),
        };
    }
}
