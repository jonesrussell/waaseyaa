<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Waaseyaa\AI\Agent\Event\ToolCallCompleted;
use Waaseyaa\AI\Agent\Event\ToolCallStarted;
use Waaseyaa\AI\Observability\Handle\SpanHandle;
use Waaseyaa\AI\Observability\Recorder\TraceRecorderInterface;
use Waaseyaa\AI\Observability\TraceContext;

final class ToolCallListener implements EventSubscriberInterface
{
    /** @var array<string, SpanHandle> keyed by toolCallId */
    private array $openSpans = [];

    public function __construct(
        private readonly TraceContext $context,
        private readonly TraceRecorderInterface $recorder,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'Waaseyaa\\AI\\Agent\\Event\\ToolCallStarted' => 'onToolCallStarted',
            'Waaseyaa\\AI\\Agent\\Event\\ToolCallCompleted' => 'onToolCallCompleted',
        ];
    }

    public function onToolCallStarted(ToolCallStarted $event): void
    {
        $handle = $this->context->get($event->traceUuid);
        if ($handle === null) {
            return;
        }
        $this->openSpans[$event->callId] = $this->recorder->span(
            $handle,
            'tool_call',
            $event->toolName,
        );
    }

    public function onToolCallCompleted(ToolCallCompleted $event): void
    {
        if (!isset($this->openSpans[$event->callId])) {
            return;
        }
        $span = $this->openSpans[$event->callId];
        unset($this->openSpans[$event->callId]);
        $status = $event->error === null ? 'ok' : 'error';
        $this->recorder->endSpan($span, ['tool' => $span->kind], $status);
    }
}
