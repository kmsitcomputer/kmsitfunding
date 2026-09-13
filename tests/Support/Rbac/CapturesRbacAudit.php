<?php

namespace Tests\Support\Rbac;

use App\Models\Audit\AuditRecord;

/**
 * IMP-004: captures canonical `audit_records` rows created during a test
 * (`IMP003-REAUDIT1-m01`, migrated onto the canonical sink per IMP-004's
 * "Migration / Backward Compatibility") so tests can assert on
 * actually-persisted audit evidence instead of only inspecting the emitting
 * code. No fake/mock is substituted for the canonical `AuditWriter` sink
 * itself — this exercises the real `audit_records` table end to end. The
 * legacy `rbac_audit` log channel is no longer canonical (nothing writes to
 * it) and is not used by this trait.
 */
trait CapturesRbacAudit
{
    private ?int $rbacAuditWatermarkId = null;

    private function captureRbacAuditLog(): void
    {
        $this->rbacAuditWatermarkId = (int) (AuditRecord::query()->max('id') ?? 0);
    }

    /**
     * @return array<int, AuditRecord>
     */
    private function readRbacAuditEvents(): array
    {
        return AuditRecord::query()
            ->where('id', '>', $this->rbacAuditWatermarkId ?? 0)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function assertRbacAuditEventLogged(string $canonicalEventType): AuditRecord
    {
        $matching = array_values(array_filter(
            $this->readRbacAuditEvents(),
            fn (AuditRecord $record) => $record->event_type === $canonicalEventType,
        ));

        $this->assertNotEmpty($matching, "Expected a canonical audit record '{$canonicalEventType}' to have been persisted. Persisted events: ".
            implode(', ', array_map(fn (AuditRecord $r) => $r->event_type, $this->readRbacAuditEvents())));

        return $matching[0];
    }

    private function assertRbacAuditEventNotLogged(string $canonicalEventType): void
    {
        $matching = array_filter(
            $this->readRbacAuditEvents(),
            fn (AuditRecord $record) => $record->event_type === $canonicalEventType,
        );

        $this->assertEmpty($matching, "Expected NO canonical audit record '{$canonicalEventType}' to have been persisted, but found one.");
    }

    protected function tearDownRbacAuditCapture(): void
    {
        $this->rbacAuditWatermarkId = null;
    }
}
