<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * Optional streaming extension to {@see ProviderInterface}.
 *
 * Implementations forward incremental {@see StreamChunk} payloads to the
 * supplied callback as the LLM emits them. The full
 * {@see MessageResponse} is returned at stream end so callers can use
 * the same accumulator as the non-streaming path.
 *
 * @api
 */
interface StreamingProviderInterface extends ProviderInterface
{
    /**
     * Stream a message, calling $onChunk for each partial result.
     *
     * Returns the complete MessageResponse after the stream ends.
     *
     * @param callable(StreamChunk): void $onChunk
     *
     * @api
     */
    public function streamMessage(MessageRequest $request, callable $onChunk): MessageResponse;
}
