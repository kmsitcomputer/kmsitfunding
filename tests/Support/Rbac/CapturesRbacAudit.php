<?php

namespace Tests\Support\Rbac;

/**
 * Redirects the `rbac_audit` log channel to a private, per-test file so
 * tests can assert on actually-emitted audit events (`IMP003-REAUDIT1-m01`)
 * instead of only inspecting the emitting code. No fake/mock is
 * substituted for `RbacAuditLogger` itself — this exercises the real
 * `Log::channel('rbac_audit')` sink end to end.
 */
trait CapturesRbacAudit
{
    private ?string $rbacAuditLogPath = null;

    private function captureRbacAuditLog(): void
    {
        $this->rbacAuditLogPath = storage_path('logs/rbac-audit-test-'.uniqid('', true).'.log');

        config(['logging.channels.rbac_audit.path' => $this->rbacAuditLogPath]);
        app('log')->forgetChannel('rbac_audit');
    }

    /**
     * @return array<int, array{event: string, context: array}>
     */
    private function readRbacAuditEvents(): array
    {
        if ($this->rbacAuditLogPath === null || ! file_exists($this->rbacAuditLogPath)) {
            return [];
        }

        $events = [];

        foreach (file($this->rbacAuditLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (! preg_match('/\.INFO: (\S+) (\{.*\})\s*$/', $line, $matches)) {
                continue;
            }

            $events[] = [
                'event' => $matches[1],
                'context' => json_decode($matches[2], true) ?? [],
            ];
        }

        return $events;
    }

    private function assertRbacAuditEventLogged(string $eventName): array
    {
        $events = $this->readRbacAuditEvents();
        $matching = array_values(array_filter($events, fn ($e) => $e['event'] === $eventName));

        $this->assertNotEmpty($matching, "Expected an audit event '{$eventName}' to have been logged. Logged events: ".
            implode(', ', array_column($events, 'event')));

        return $matching[0]['context'];
    }

    private function assertRbacAuditEventNotLogged(string $eventName): void
    {
        $events = $this->readRbacAuditEvents();
        $matching = array_filter($events, fn ($e) => $e['event'] === $eventName);

        $this->assertEmpty($matching, "Expected NO audit event '{$eventName}' to have been logged, but found one.");
    }

    protected function tearDownRbacAuditCapture(): void
    {
        if ($this->rbacAuditLogPath !== null && file_exists($this->rbacAuditLogPath)) {
            @unlink($this->rbacAuditLogPath);
        }
    }
}
