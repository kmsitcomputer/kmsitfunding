<?php

namespace App\Services\Audit;

/**
 * Backend-only filter for the IMP-004 audit query foundation. eventType may
 * be an exact canonical identifier or a namespace prefix ending in '.*'
 * (e.g. 'identity.*'). All fields optional; page size is bounded.
 */
final class AuditQueryFilter
{
    public const MAX_PER_PAGE = 100;

    public function __construct(
        public readonly ?string $eventType = null,
        public readonly ?int $actorPrincipalId = null,
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        public readonly ?\DateTimeInterface $occurredFrom = null,
        public readonly ?\DateTimeInterface $occurredTo = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $criticality = null,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {}
}
