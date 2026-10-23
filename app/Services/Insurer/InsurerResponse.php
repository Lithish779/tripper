<?php

namespace App\Services\Insurer;

readonly class InsurerResponse
{
    public function __construct(
        public bool $successful,
        public int $httpStatus,
        public ?string $status = null,
        public ?string $policyNumber = null,
        public ?string $providerReference = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public ?array $rawPayload = null,
        public bool $isTimeout = false,
        public bool $isRetryable = false,
    ) {}

    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'http_status' => $this->httpStatus,
            'status' => $this->status,
            'policy_number' => $this->policyNumber,
            'provider_reference' => $this->providerReference,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'is_timeout' => $this->isTimeout,
            'is_retryable' => $this->isRetryable,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
