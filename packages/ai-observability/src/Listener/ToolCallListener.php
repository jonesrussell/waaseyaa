<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

    public function onToolCallStarted(object $event): void
    {
        $traceUuid = $this->readProp($event, 'traceUuid');
        $callId = $this->readProp($event, 'callId');
        if (!is_string($traceUuid) || !is_string($callId)) {
            return;
        }
        $handle = $this->context->get($traceUuid);
        if ($handle === null) {
            return;
        }
        $this->openSpans[$callId] = $this->recorder->span(
            $handle,
            'tool_call',
            (string) ($this->readProp($event, 'toolName') ?? 'unknown'),
        );
    }

    public function onToolCallCompleted(object $event): void
    {
        $callId = $this->readProp($event, 'callId');
        if (!is_string($callId) || !isset($this->openSpans[$callId])) {
            return;
        }
        $span = $this->openSpans[$callId];
        unset($this->openSpans[$callId]);
        $status = ($this->readProp($event, 'error') === null) ? 'ok' : 'error';
        $this->recorder->endSpan($span, ['tool' => $span->kind], $status);
    }

    private function readProp(object $obj, string $name): mixed
    {
        if (!property_exists($obj, $name)) {
            return null;
        }

        /** @phpstan-ignore property.dynamicName */
        return $obj->{$name};
    }
}
