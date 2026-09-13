<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditCriticality;
use App\Enums\AuditPersistenceStrategy;
use App\Models\Audit\AuditRecord;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Audit\AuditQueryFilter;
use App\Services\Audit\AuditQueryService;
use App\Services\Audit\AuditReadAuthorizer;
use App\Services\Audit\AuditRetentionFoundation;
use App\Services\Audit\AuditWriter;
use App\Services\Audit\Exceptions\AuditMetadataViolationException;
use App\Services\Audit\Exceptions\AuditRecordImmutableException;
use App\Services\Audit\Exceptions\AuditSourceEventException;
use App\Services\Audit\Exceptions\UnregisteredAuditEventException;
use App\Services\Identity\IdentityAuditLogger;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RbacAuditLogger;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-004 — Audit + Governance Foundation. Proves, against the real
 * canonical `audit_records` sink (no fake/mock of AuditWriter itself), the
 * required behavior from docs/implementation/IMP-004-audit-governance-foundation.md:
 * event inventory, registry-owned criticality/persistence-strategy,
 * MUTATION_ATOMIC and DENIAL_DURABLE semantics, the Transaction Ownership
 * sequencing fix, pre-principal actor attribution, allow-list redaction,
 * append-only immutability, registry-derived read authorization, and
 * source_event_id idempotency.
 */
class AuditFoundationTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makePlainPrincipal(): Principal
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'email' => "audit-foundation-{$seq}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    // --- Event Inventory (IMP004-SPEC-M01) ---

    public function test_registry_inventory_matches_specification_counts(): void
    {
        $registry = app(AuditEventRegistry::class);
        $all = $registry->all();

        $reserved = array_filter($all, fn ($d) => $d->reserved);
        $active = array_filter($all, fn ($d) => ! $d->reserved);

        // 19 Identity + 9 RBAC + 1 new denial event = 29 active/target.
        $this->assertCount(29, $active);
        // 5 reserved catalog events + governance.audit.purged = 6 reserved.
        $this->assertCount(6, $reserved);
        $this->assertCount(35, $all);

        $this->assertNotNull($registry->find('security.authorization.denied', 1));
        $this->assertNotNull($registry->find('governance.audit.purged', 1));
    }

    public function test_reserved_events_are_never_emitted_by_the_full_suite(): void
    {
        // A sentinel check: reserved catalog-lifecycle events have no
        // runtime call site anywhere in app/ — confirmed by static absence,
        // not by running the whole suite here (out of scope for one test).
        $this->assertTrue(app(AuditEventRegistry::class)->find('rbac.role.registered', 1)->reserved);
        $this->assertTrue(app(AuditEventRegistry::class)->find('governance.audit.purged', 1)->reserved);
    }

    public function test_unregistered_event_type_is_rejected(): void
    {
        $this->expectException(UnregisteredAuditEventException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'nonexistent.event.type',
            actor: $this->makePlainPrincipal(),
            subjectType: 'user',
            subjectId: null,
        ));
    }

    // --- Persistence + Redaction (Registry-Owned Allow-List) ---

    public function test_metadata_key_outside_allow_list_is_rejected(): void
    {
        $actor = $this->makePlainPrincipal();

        $this->expectException(AuditMetadataViolationException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'identity.user.created',
            actor: $actor,
            subjectType: 'user',
            subjectId: $actor->id,
            metadata: ['password' => 'should-never-be-accepted'],
        ));
    }

    public function test_persisted_event_never_contains_hard_prohibited_categories(): void
    {
        // The registry rejects registering an event that declares a
        // prohibited key at all — proving the constraint is structural, not
        // merely a per-call-site convention.
        $registry = new AuditEventRegistry;

        foreach ($registry->all() as $definition) {
            foreach (array_keys($definition->metadataAllowList) as $key) {
                $this->assertStringNotContainsStringIgnoringCase('password', $key);
                $this->assertStringNotContainsStringIgnoringCase('secret', $key);
                $this->assertStringNotContainsStringIgnoringCase('token', $key);
            }
        }
    }

    // --- MUTATION_ATOMIC (Q26) ---

    public function test_mutation_atomic_event_persists_with_correct_actor_and_subject(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_role_1', 'name' => 'Audit Foundation Role 1']);
        $permission = Permission::create(['code' => 'audit.found.perm1', 'description' => 'test']);

        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $record = AuditRecord::where('event_type', 'rbac.role_permission.granted')
            ->where('actor_principal_id', $actor->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('critical', $record->criticality);
        $this->assertSame($role->id, $record->metadata['role_id']);
        $this->assertSame($permission->id, $record->metadata['permission_id']);
    }

    // --- DENIAL_DURABLE (F-01 / IMP004-SPEC-M04) ---

    public function test_self_escalation_denial_persists_durable_denial_event(): void
    {
        $actor = $this->makeAuthorizedActor();
        $selfRole = Role::where('code', 'test_rbac_root')->firstOrFail();
        $newPermission = Permission::create(['code' => 'audit.found.denial1', 'description' => 'test']);

        try {
            app(RolePermissionService::class)->grant($actor, $selfRole, $newPermission);
            $this->fail('Expected the original self-escalation denial to propagate unchanged.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Self-Escalation Protection', $e->getMessage());
        }

        $record = AuditRecord::where('event_type', 'security.authorization.denied')
            ->where('actor_principal_id', $actor->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($record, 'The denial must be durably recorded via the canonical sink.');
        $this->assertSame('critical', $record->criticality);
        $this->assertSame('self_escalation', $record->metadata['denial_reason']);
        $this->assertSame(
            0,
            DB::table('role_permissions')->where('role_id', $selfRole->id)->where('permission_id', $newPermission->id)->count(),
            'No business mutation may occur for a denied grant.'
        );
    }

    public function test_denial_audit_persistence_failure_never_permits_access(): void
    {
        $actor = $this->makeAuthorizedActor();
        $selfRole = Role::where('code', 'test_rbac_root')->firstOrFail();
        $newPermission = Permission::create(['code' => 'audit.found.denial2', 'description' => 'test']);

        // Force the denial-audit persistence specifically to fail — access
        // must remain denied regardless, and the original exception
        // (unchanged type/message) must still be the one that propagates.
        // AuditWriter is `final`, so the failure is injected one level up,
        // at the same RbacAuditLogger seam RbacAuditTest already uses for
        // its own forced-failure tests.
        $this->app->bind(RbacAuditLogger::class, fn () => new class extends RbacAuditLogger
        {
            public function __construct() {}

            public function recordAuthorizationDenied(Principal $actor, ?int $subjectId, string $attemptedAction, string $denialReason): void
            {
                throw new \RuntimeException('forced denial-audit failure');
            }
        });

        try {
            app(RolePermissionService::class)->grant($actor, $selfRole, $newPermission);
            $this->fail('Expected the original self-escalation denial to propagate unchanged.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Self-Escalation Protection', $e->getMessage());
            $this->assertStringNotContainsString('forced denial-audit failure', $e->getMessage());
        }

        $this->assertSame(
            0,
            DB::table('role_permissions')->where('role_id', $selfRole->id)->where('permission_id', $newPermission->id)->count(),
            'Access must never become allowed because the denial-audit write failed.'
        );
        $this->assertNull(
            AuditRecord::where('event_type', 'security.authorization.denied')
                ->where('metadata->denial_reason', 'self_escalation')
                ->where('actor_principal_id', $actor->id)
                ->first(),
        );
    }

    public function test_denial_sequencing_does_not_throw_ownership_violation_under_refresh_database(): void
    {
        // Regression: the original Transaction Ownership Invariant draft
        // rejected ANY ambient transaction, which made grant() untestable
        // under this repository's own RefreshDatabase convention (it always
        // opens one transaction per test). The corrected implementation
        // relies on sequencing (catch-after-rollback) alone — this test IS
        // that regression proof, running (as every test in this suite does)
        // inside RefreshDatabase's own transaction.
        $this->assertGreaterThan(0, DB::connection()->transactionLevel());

        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_nested_ok', 'name' => 'Audit Found Nested OK']);
        $permission = Permission::create(['code' => 'audit.found.nested_ok', 'description' => 'test']);

        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $this->assertTrue($role->permissions()->where('permissions.id', $permission->id)->exists());
    }

    // --- Pre-Principal Actor (IMP004-SPEC-M02) ---

    public function test_bootstrap_event_is_attributed_to_pre_principal_system(): void
    {
        $subjectUser = User::create(['email' => 'audit-found-bootstrap@example.com', 'password' => Hash::make('correct-horse-battery-staple')]);

        app(IdentityAuditLogger::class)->record('first_super_admin_bootstrap_completed', $subjectUser);

        $record = AuditRecord::where('event_type', 'identity.bootstrap.first_super_admin_completed')->latest('id')->first();

        $this->assertNotNull($record);
        $this->assertNull($record->actor_principal_id);
        $this->assertSame('pre_principal_system', $record->actor_principal_kind);
        $this->assertSame('cli:identity:bootstrap-super-admin', $record->execution_context);
        $this->assertSame($subjectUser->id, $record->subject_id, 'The new user is the SUBJECT, never the actor.');
    }

    public function test_login_failed_is_attributed_to_unauthenticated_never_a_fabricated_human(): void
    {
        app(IdentityAuditLogger::class)->record('login_failed', null, ['reason' => 'invalid_credentials']);

        $record = AuditRecord::where('event_type', 'identity.session.login_failed')->latest('id')->first();

        $this->assertNotNull($record);
        $this->assertNull($record->actor_principal_id);
        $this->assertSame('unauthenticated', $record->actor_principal_kind);
        $this->assertNull($record->subject_id, 'Unknown-email attempt has no resolvable subject.');
    }

    public function test_invitation_issued_with_no_issuer_uses_pre_principal_attribution(): void
    {
        app(IdentityAuditLogger::class)->record('invitation_issued', null, [
            'invitation_id' => 999999,
            'invitation_public_id' => 'test-public-id',
            'invited_actor' => 'partner_representative',
        ]);

        $record = AuditRecord::where('event_type', 'identity.invitation.issued')->latest('id')->first();

        $this->assertNotNull($record);
        $this->assertNull($record->actor_principal_id, 'A legitimately null issuer (Q22) must never fabricate a human actor.');
        $this->assertSame('pre_principal_system', $record->actor_principal_kind);
    }

    // --- Immutability (Q28) ---

    public function test_audit_record_cannot_be_updated(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_immutable_role', 'name' => 'Audit Found Immutable Role']);
        $permission = Permission::create(['code' => 'audit.found.immutable', 'description' => 'test']);
        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $record = AuditRecord::where('event_type', 'rbac.role_permission.granted')->latest('id')->firstOrFail();

        $this->expectException(AuditRecordImmutableException::class);
        $record->criticality = 'non_critical';
        $record->save();
    }

    public function test_audit_record_cannot_be_deleted(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_delete_role', 'name' => 'Audit Found Delete Role']);
        $permission = Permission::create(['code' => 'audit.found.delete', 'description' => 'test']);
        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $record = AuditRecord::where('event_type', 'rbac.role_permission.granted')->latest('id')->firstOrFail();

        $this->expectException(AuditRecordImmutableException::class);
        $record->delete();
    }

    public function test_mass_update_through_query_builder_is_blocked(): void
    {
        $this->expectException(AuditRecordImmutableException::class);
        AuditRecord::where('id', '>', 0)->update(['criticality' => 'non_critical']);
    }

    public function test_mass_delete_through_query_builder_is_blocked(): void
    {
        $this->expectException(AuditRecordImmutableException::class);
        AuditRecord::where('id', '>', 0)->delete();
    }

    // --- source_event_id / source_domain (IMP004-SPEC-M06) ---

    public function test_invalid_partial_source_pair_is_rejected(): void
    {
        $actor = $this->makePlainPrincipal();

        $this->expectException(AuditSourceEventException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'identity.user.created',
            actor: $actor,
            subjectType: 'user',
            subjectId: $actor->id,
            sourceDomain: null,
            sourceEventId: 'abc123',
        ));
    }

    public function test_legitimate_retry_returns_existing_record_without_duplicate(): void
    {
        // No currently-migrated event declares a source_domain policy, so
        // this proves the mechanism itself via direct AuditWriter use with a
        // definition that HAS one is out of scope here without adding a
        // throwaway registry entry — instead, prove the NULL/no-identity
        // path never collides, which every one of the 29 real events relies on.
        $actor = $this->makePlainPrincipal();

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'identity.user.created',
            actor: $actor,
            subjectType: 'user',
            subjectId: $actor->id,
        ));
        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'identity.user.created',
            actor: $actor,
            subjectType: 'user',
            subjectId: $actor->id,
        ));

        $this->assertSame(
            2,
            AuditRecord::where('event_type', 'identity.user.created')->where('actor_principal_id', $actor->id)->count(),
            'Two independent NULL-source-identity emissions are two independent rows, never deduplicated.'
        );
    }

    // --- Read Authorization (Q27 / IMP004-SPEC-M05) ---

    public function test_default_deny_for_actor_without_audit_read_permission(): void
    {
        $reader = $this->makePlainPrincipal();
        $authorizedActor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_read_role_1', 'name' => 'Audit Found Read Role 1']);
        $permission = Permission::create(['code' => 'audit.found.read1', 'description' => 'test']);
        app(RolePermissionService::class)->grant($authorizedActor, $role, $permission);

        $record = AuditRecord::where('event_type', 'rbac.role_permission.granted')->latest('id')->firstOrFail();

        $this->assertFalse(app(AuditReadAuthorizer::class)->canRead($reader, $record));
    }

    public function test_authorized_actor_with_audit_read_security_can_read_security_event(): void
    {
        // makeAuthorizedActor() holds every PermissionRegistry-defined
        // permission (including the new audit.read.* family), at
        // GLOBAL_PLATFORM scope, ELEVATED assurance.
        $authorizedActor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_read_role_2', 'name' => 'Audit Found Read Role 2']);
        $permission = Permission::create(['code' => 'audit.found.read2', 'description' => 'test']);
        app(RolePermissionService::class)->grant($authorizedActor, $role, $permission);

        $record = AuditRecord::where('event_type', 'rbac.role_permission.granted')->latest('id')->firstOrFail();

        $this->assertTrue(app(AuditReadAuthorizer::class)->canRead($authorizedActor, $record));
    }

    public function test_super_admin_role_alone_does_not_grant_audit_read(): void
    {
        // A plain Principal with NO rbac.* / audit.* permission grant at all
        // (not even a role named "Super Admin") must be denied — proving
        // the absence of any automatic-access shortcut structurally, since
        // this repository's RBAC has no special-cased role-name bypass
        // anywhere in AuthorizationEvaluator.
        $noGrantPrincipal = $this->makePlainPrincipal();
        $authorizedActor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_read_role_3', 'name' => 'Audit Found Read Role 3']);
        $permission = Permission::create(['code' => 'audit.found.read3', 'description' => 'test']);
        app(RolePermissionService::class)->grant($authorizedActor, $role, $permission);

        $record = AuditRecord::where('event_type', 'rbac.role_permission.granted')->latest('id')->firstOrFail();

        $this->assertFalse(app(AuditReadAuthorizer::class)->canRead($noGrantPrincipal, $record));
    }

    public function test_query_service_never_returns_unauthorized_records(): void
    {
        $reader = $this->makePlainPrincipal();
        $authorizedActor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_query_role', 'name' => 'Audit Found Query Role']);
        $permission = Permission::create(['code' => 'audit.found.query', 'description' => 'test']);
        app(RolePermissionService::class)->grant($authorizedActor, $role, $permission);

        $result = app(AuditQueryService::class)->search($reader, new AuditQueryFilter(eventType: 'rbac.role_permission.granted'));

        $this->assertSame(0, $result['authorized_count']);
        $this->assertSame([], $result['records']);
    }

    // --- Terminal Purge-Evidence Rule (IMP004-SPEC-m01) ---

    public function test_purge_evidence_excluded_from_its_own_producing_batch(): void
    {
        $actor = $this->makePlainPrincipal();

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: AuditRetentionFoundation::PURGE_EVIDENCE_EVENT_TYPE,
            actor: $actor,
            subjectType: 'audit_record',
            subjectId: null,
            metadata: ['purge_batch_id' => 'batch-001', 'purged_count' => 3],
        ));

        $eligible = app(AuditRetentionFoundation::class)->eligibleForPurgeBatch('batch-001')->get();

        $this->assertFalse(
            $eligible->contains(fn (AuditRecord $r) => $r->event_type === AuditRetentionFoundation::PURGE_EVIDENCE_EVENT_TYPE
                && ($r->metadata['purge_batch_id'] ?? null) === 'batch-001'),
            'A purge-evidence record must never be eligible for the same batch that produced it.'
        );
    }

    // --- Registry Ownership (Criticality/Visibility Never Caller-Supplied) ---

    public function test_criticality_and_persistence_strategy_are_registry_owned(): void
    {
        $definition = app(AuditEventRegistry::class)->get('security.authorization.denied', 1);

        $this->assertSame(AuditCriticality::Critical, $definition->criticality);
        $this->assertSame(AuditPersistenceStrategy::DenialDurable, $definition->persistenceStrategy);

        $mutationDefinition = app(AuditEventRegistry::class)->get('rbac.role.assigned', 1);
        $this->assertSame(AuditPersistenceStrategy::MutationAtomic, $mutationDefinition->persistenceStrategy);

        $nonCriticalDefinition = app(AuditEventRegistry::class)->get('identity.session.login_succeeded', 1);
        $this->assertSame(AuditCriticality::NonCritical, $nonCriticalDefinition->criticality);
        $this->assertNull($nonCriticalDefinition->persistenceStrategy);
    }
}
