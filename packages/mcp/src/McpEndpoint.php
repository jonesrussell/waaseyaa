<?php

declare(strict_types=1);

namespace Aurora\Mcp;

use Aurora\Mcp\Auth\McpAuthInterface;
use Aurora\Mcp\Bridge\ToolExecutorInterface;
use Aurora\Mcp\Bridge\ToolRegistryInterface;

final readonly class McpEndpoint
{
    public function __construct(
        private McpAuthInterface $auth,
        private ToolRegistryInterface $registry,
        private ToolExecutorInterface $executor,
    ) {}

    public function handle(
        string $method,
        string $body,
        ?string $authorizationHeader,
    ): McpResponse {
        // Authenticate.
        $account = $this->auth->authenticate($authorizationHeader);
        if ($account === null) {
            return new McpResponse(
                body: \json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Unauthorized'],
                    'id' => null,
                ], \JSON_THROW_ON_ERROR),
                statusCode: 401,
            );
        }

        // Parse JSON-RPC request.
        try {
            $request = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->jsonRpcError(-32700, 'Parse error', null);
        }

        if (!\is_array($request) || !isset($request['method'])) {
            return $this->jsonRpcError(-32600, 'Invalid Request', $request['id'] ?? null);
        }

        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        return match ($request['method']) {
            'initialize' => $this->handleInitialize($id),
            'ping' => $this->handlePing($id),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params),
            default => $this->jsonRpcError(-32601, "Method not found: {$request['method']}", $id),
        };
    }

    private function handleInitialize(mixed $id): McpResponse
    {
        return $this->jsonRpcResult($id, [
            'protocolVersion' => '2025-03-26',
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'Aurora CMS',
                'version' => '0.1.0',
            ],
        ]);
    }

    private function handlePing(mixed $id): McpResponse
    {
        return $this->jsonRpcResult($id, []);
    }

    private function handleToolsList(mixed $id): McpResponse
    {
        $tools = [];
        foreach ($this->registry->getTools() as $tool) {
            $tools[] = $tool->toArray();
        }

        return $this->jsonRpcResult($id, ['tools' => $tools]);
    }

    private function handleToolsCall(mixed $id, array $params): McpResponse
    {
        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if ($toolName === null) {
            return $this->jsonRpcError(-32602, 'Missing required parameter: name', $id);
        }

        $tool = $this->registry->getTool($toolName);
        if ($tool === null) {
            return $this->jsonRpcError(-32602, "Unknown tool: {$toolName}", $id);
        }

        $result = $this->executor->execute($toolName, $arguments);

        return $this->jsonRpcResult($id, $result);
    }

    private function jsonRpcResult(mixed $id, mixed $result): McpResponse
    {
        return new McpResponse(
            body: \json_encode([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ], \JSON_THROW_ON_ERROR),
        );
    }

    private function jsonRpcError(int $code, string $message, mixed $id): McpResponse
    {
        return new McpResponse(
            body: \json_encode([
                'jsonrpc' => '2.0',
                'error' => ['code' => $code, 'message' => $message],
                'id' => $id,
            ], \JSON_THROW_ON_ERROR),
        );
    }
}
