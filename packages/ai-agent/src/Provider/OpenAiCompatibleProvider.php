<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * OpenAI Chat Completions–compatible HTTP provider (OpenRouter, Azure OpenAI, local gateways, etc.).
 *
 * Implements {@see ProviderInterface} only (no streaming). Text-in / text-out; tool loops should
 * continue to use {@see AnthropicProvider} until tool schema bridging is implemented.
 */
final class OpenAiCompatibleProvider implements ProviderInterface
{
    private const string DEFAULT_BASE = 'https://api.openai.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = self::DEFAULT_BASE,
        private readonly string $model = 'gpt-4o-mini',
    ) {}

    public function sendMessage(MessageRequest $request): MessageResponse
    {
        $url = \rtrim($this->baseUrl, '/') . '/chat/completions';
        $fragment = $request->conversation()->toOpenAiChatFragment();
        $body = \array_merge($fragment, ['model' => $this->model]);
        $data = $this->httpPost($url, $body);

        return self::parseChatCompletionResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function parseChatCompletionResponse(array $data): MessageResponse
    {
        $choice = $data['choices'][0] ?? [];
        if (!\is_array($choice)) {
            $choice = [];
        }
        $message = $choice['message'] ?? [];
        if (!\is_array($message)) {
            $message = [];
        }
        $content = $message['content'] ?? '';
        if (!\is_string($content)) {
            $content = \is_array($content) ? \json_encode($content, \JSON_THROW_ON_ERROR) : '';
        }

        $finish = $choice['finish_reason'] ?? 'stop';
        $stopReason = $finish === 'tool_calls' ? 'tool_use' : 'end_turn';

        $usage = $data['usage'] ?? [];
        $usageIn = \is_array($usage) ? (int) ($usage['prompt_tokens'] ?? 0) : 0;
        $usageOut = \is_array($usage) ? (int) ($usage['completion_tokens'] ?? 0) : 0;

        return new MessageResponse(
            content: [['type' => 'text', 'text' => $content]],
            stopReason: $stopReason,
            usage: [
                'input_tokens' => $usageIn,
                'output_tokens' => $usageOut,
            ],
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function httpPost(string $url, array $body): array
    {
        $jsonBody = \json_encode($body, \JSON_THROW_ON_ERROR);

        $ch = \curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL.');
        }

        \curl_setopt_array($ch, [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $jsonBody,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            \CURLOPT_TIMEOUT => 120,
        ]);

        $responseBody = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        if ($responseBody === false) {
            $error = \curl_error($ch);
            \curl_close($ch);
            throw new \RuntimeException("cURL error: {$error}");
        }

        \curl_close($ch);

        if (!\is_string($responseBody)) {
            throw new \RuntimeException('Unexpected cURL response type.');
        }

        /** @var array<string, mixed> $data */
        $data = \json_decode($responseBody, true, 512, \JSON_THROW_ON_ERROR);

        if ($httpCode >= 400) {
            $errorMessage = self::extractOpenAiErrorMessage($data, $httpCode);
            throw new \RuntimeException("OpenAI-compatible API error: {$errorMessage}");
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractOpenAiErrorMessage(array $data, int $httpCode): string
    {
        $err = $data['error'] ?? null;
        if (\is_array($err) && isset($err['message'])) {
            return (string) $err['message'];
        }
        if (\is_string($err)) {
            return $err;
        }

        return "HTTP {$httpCode}";
    }
}
