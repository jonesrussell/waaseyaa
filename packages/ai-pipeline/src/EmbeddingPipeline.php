<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Pipeline;

use Waaseyaa\AI\Vector\EmbeddingProviderInterface;
use Waaseyaa\AI\Vector\EmbeddingStorageInterface;
use Waaseyaa\AI\Vector\OllamaEmbeddingProvider;
use Waaseyaa\AI\Vector\OpenAiEmbeddingProvider;
use Waaseyaa\Entity\EntityInterface;

final class EmbeddingPipeline
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly EmbeddingStorageInterface $storage,
        private readonly array $config = [],
        private readonly ?EmbeddingProviderInterface $provider = null,
    ) {}

    public function processEntity(EntityInterface $entity): void
    {
        $entityId = $entity->id();
        if ($entityId === null || $entityId === '') {
            return;
        }

        $provider = $this->provider ?? $this->resolveProvider();
        if ($provider === null) {
            error_log('[Waaseyaa] EmbeddingPipeline: no embedding provider configured; skipping.');
            return;
        }

        $text = $this->extractText($entity);
        if (trim($text) === '') {
            return;
        }

        $vector = $provider->embed($text);
        $this->storage->store(
            $entity->getEntityTypeId(),
            (string) $entityId,
            $vector,
        );
    }

    private function resolveProvider(): ?EmbeddingProviderInterface
    {
        $ai = is_array($this->config['ai'] ?? null) ? $this->config['ai'] : [];
        $provider = strtolower((string) ($ai['embedding_provider'] ?? ''));

        return match ($provider) {
            'ollama' => new OllamaEmbeddingProvider(
                endpoint: is_string($ai['ollama_endpoint'] ?? null)
                    ? $ai['ollama_endpoint']
                    : 'http://127.0.0.1:11434/api/embeddings',
                model: is_string($ai['ollama_model'] ?? null)
                    ? $ai['ollama_model']
                    : 'nomic-embed-text',
            ),
            'openai' => new OpenAiEmbeddingProvider(
                apiKey: (string) ($ai['openai_api_key'] ?? getenv('OPENAI_API_KEY') ?: ''),
                model: is_string($ai['openai_embedding_model'] ?? null)
                    ? $ai['openai_embedding_model']
                    : 'text-embedding-3-small',
            ),
            default => null,
        };
    }

    private function extractText(EntityInterface $entity): string
    {
        $values = $entity->toArray();
        $fields = $this->configuredFieldsForEntityType($entity->getEntityTypeId());
        if ($fields === []) {
            $fields = ['title', 'name', 'body'];
        }

        $parts = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = $values[$field];
            if (is_string($value) || is_int($value) || is_float($value)) {
                $string = trim((string) $value);
                if ($string !== '') {
                    $parts[] = $string;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function configuredFieldsForEntityType(string $entityTypeId): array
    {
        $ai = is_array($this->config['ai'] ?? null) ? $this->config['ai'] : [];
        $map = is_array($ai['embedding_fields'] ?? null) ? $ai['embedding_fields'] : [];
        $fields = $map[$entityTypeId] ?? null;
        if (!is_array($fields)) {
            return [];
        }

        $output = [];
        foreach ($fields as $field) {
            if (is_string($field) && $field !== '') {
                $output[] = $field;
            }
        }

        return $output;
    }
}
