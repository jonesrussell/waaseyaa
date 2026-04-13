<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Patch;

final readonly class PatchEntry
{
    public function __construct(
        public string $filePath,
        public string $content,
        public string $diffText,
        public string $contentHash,
        public bool $unsafe,
    ) {}

    /** @return array{file_path: string, content: string, diff_text: string, content_hash: string, unsafe: bool} */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'content' => $this->content,
            'diff_text' => $this->diffText,
            'content_hash' => $this->contentHash,
            'unsafe' => $this->unsafe,
        ];
    }
}
