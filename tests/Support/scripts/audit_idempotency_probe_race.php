<?php

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Audit\AuditWriter;
use Illuminate\Contracts\Console\Kernel;

/**
 * IMP004-IMPL-M04 concurrency evidence probe — a standalone CLI process
 * (never invoked in production) used only by
 * AuditFoundationTest::test_concurrent_same_source_key_insertion_yields_exactly_one_canonical_record()
 * to prove that two genuinely independent OS processes racing the SAME
 * idempotency key against a shared MySQL database converge on exactly one
 * canonical audit_records row via the real AuditWriter — never via a
 * pre-insert check alone (SQLite's single-writer-connection model cannot
 * provide this evidence, which is why this test is MySQL-only).
 *
 * Usage: php audit_idempotency_probe_race.php <source_event_id> <principal_id> <barrier_file>
 * Prints "OK:<record_id>" or "ERR:<exception_class>:<message>" to stdout.
 */

require __DIR__.'/../../../vendor/autoload.php';

[$sourceEventId, $principalId, $barrierFile] = [$argv[1] ?? null, $argv[2] ?? null, $argv[3] ?? null];

if ($sourceEventId === null || $principalId === null || $barrierFile === null) {
    fwrite(STDERR, "usage: audit_idempotency_probe_race.php <source_event_id> <principal_id> <barrier_file>\n");
    exit(2);
}

$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$registry = new AuditEventRegistry;
$registry->register(new AuditEventDefinition(
    eventType: 'test.idempotency.probe',
    eventVersion: 1,
    criticality: AuditCriticality::NonCritical,
    persistenceStrategy: null,
    visibilityClass: AuditVisibilityClass::General,
    subjectType: 'probe',
    subjectIdNullable: true,
    metadataAllowList: ['label' => 'string'],
    actorKinds: [AuditActorKind::Human],
    executionContext: null,
    requiresElevatedAssuranceToRead: false,
    scopeType: ScopeType::GlobalPlatform,
    sourceDomain: 'test_probe_domain',
));
$app->instance(AuditEventRegistry::class, $registry);

$principal = Principal::findOrFail((int) $principalId);

$deadline = microtime(true) + 5.0;
while (! file_exists($barrierFile)) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "barrier wait timed out\n");
        exit(3);
    }
    usleep(500);
}

try {
    $record = $app->make(AuditWriter::class)->record(new AuditEventInput(
        eventType: 'test.idempotency.probe',
        actor: $principal,
        subjectType: 'probe',
        subjectId: null,
        metadata: ['label' => 'race'],
        sourceDomain: 'test_probe_domain',
        sourceEventId: $sourceEventId,
    ));
    echo 'OK:'.$record->id."\n";
} catch (Throwable $e) {
    echo 'ERR:'.get_class($e).':'.$e->getMessage()."\n";
}
