<?php

namespace App\Mcp\Data;

use Carbon\CarbonInterface;

/**
 * One active OAuth token a user granted to an MCP client (e.g. Claude).
 */
final readonly class McpConnectionData
{
    public function __construct(
        public string $id,
        public string $clientName,
        public ?string $workspaceName,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $expiresAt,
    ) {}

    /**
     * @return array{id: string, clientName: string, workspaceName: string|null, createdAt: string|null, expiresAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'clientName' => $this->clientName,
            'workspaceName' => $this->workspaceName,
            'createdAt' => $this->createdAt?->toIso8601String(),
            'expiresAt' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
