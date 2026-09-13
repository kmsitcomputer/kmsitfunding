<?php

namespace App\Services\Audit;

use Illuminate\Support\Str;

/**
 * IMP-004 correlation foundation: request_id (per-HTTP-request/per-job,
 * generated once at the entry point) and correlation_id (may span multiple
 * requests/jobs that logically belong together). Both are plain opaque ULID
 * strings — never an authorization/authentication credential, never derived
 * from or exposing any secret. Resolved as a request-scoped container
 * singleton, mirroring how AssuranceService is resolved today.
 */
final class CorrelationContext
{
    private ?string $requestId = null;

    private ?string $correlationId = null;

    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::ulid();
    }

    public function correlationId(): string
    {
        return $this->correlationId ??= $this->requestId();
    }

    /**
     * Explicitly adopt an upstream correlation identifier (e.g. a future
     * webhook-retry chain). Bounded, opaque, and never trusted as a credential.
     */
    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = mb_substr(trim($correlationId), 0, 64);
    }
}
