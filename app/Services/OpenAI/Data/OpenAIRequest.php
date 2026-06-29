<?php

namespace App\Services\OpenAI\Data;

class OpenAIRequest
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $capability,
        public readonly string $operation,
        public readonly array $payload,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'operation' => $this->operation,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
        ];
    }
}
