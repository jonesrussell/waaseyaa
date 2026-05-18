<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Enum;

/**
 * Event type stored on each {@see \Waaseyaa\AI\Agent\Entity\AgentAuditLog}
 * row. Mirrors the SSE event vocabulary emitted by the executor.
 *
 * @api
 */
enum EventType: string
{
    case IterationStart = 'iteration_start';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
    case ProviderCall = 'provider_call';
    case ApprovalRequired = 'approval_required';
    case ApprovalGranted = 'approval_granted';
    case ApprovalDenied = 'approval_denied';
    case Error = 'error';
}
