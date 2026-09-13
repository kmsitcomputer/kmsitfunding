<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditPersistenceStrategy;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Models\Audit\AuditRecord;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Audit\AuditQueryFilter;
use App\Services\Audit\AuditQueryService;
use App\Services\Audit\AuditReadAuthorizer;
use App\Services\Audit\AuditRetentionFoundation;
use App\Services\Audit\AuditWriter;
use App\Services\Audit\Exceptions\AuditActorAttributionException;
use App\Services\Audit\Exceptions\AuditIdempotencyConflictException;
use App\Services\Audit\Exceptions\AuditMetadataViolationException;
use App\Services\Audit\Exceptions\AuditRecordImmutableException;
use App\Services\Audit\Exceptions\AuditSourceEventException;
use App\Services\Audit\Exceptions\TransactionOwnershipViolationException;
use App\Services\Audit\Exceptions\UnregisteredAuditEventException;
use App\Services\Identity\IdentityAuditLogger;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RbacAuditLogger;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\Support\TruncatesInMemorySqlite;
use Tests\TestCase;

/**
 * IMP-004 — Audit + Governance Foundation. Proves, against the real
 * canonical `audit_records` sink (no fake/mock of AuditWriter itself), the
 * required behavior from docs/implementation/IMP-004-audit-governance-foundation.md:
 * event inventory, registry-owned criticality/persistence-strategy,
 * MUTATION_ATOMIC and DENIAL_DURABLE semantics, the restored Transaction
 * Ownership Invariant, pre-principal actor attribution, allow-list
 * redaction, append-only immutability, registry-derived read authorization,
 * and source_event_id idempotency.
 *
 * Uses TruncatesInMemorySqlite, not RefreshDatabase: several tests exercise
 * RolePermissionService::grant(), which enforces the Transaction Ownership
 * Invariant (IMP004-IMPL-M01) and must genuinely be the outermost
 * transaction — RefreshDatabase's per-test wrapper transaction would
 * otherwise trip that check on every such call. See that trait's docblock
 * for why plain DatabaseTruncation does not work against this repository's
 * `:memory:` SQLite test connection.
 */
class AuditFoundationTest extends TestCase
{
    use RbacTestActors;
    use TruncatesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTruncatedDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownTruncatedDatabase();
        parent::tearDown();
    }

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

    public function test_nested_associative_array_metadata_value_is_rejected(): void
    {
        // IMP004-IMPL-M02: HARD_PROHIBITED_METADATA_KEYS is only ever
        // checked against the top-level allow-list's OWN keys — an
        // 'array'-typed field must never accept a nested associative
        // structure, since that would let a prohibited key (e.g.
        // 'password') be smuggled inside an otherwise-permitted field. The
        // fix requires a flat, sequential list of scalars only. This
        // asserts the rejection WITHOUT logging the actual secret value in
        // any test diagnostic.
        $this->expectException(AuditMetadataViolationException::class);

        try {
            app(AuditWriter::class)->record(new AuditEventInput(
                eventType: 'rbac.super_admin.canonically_authorized',
                actor: null,
                subjectType: 'principal',
                subjectId: null,
                metadata: [
                    'role' => 'super_admin',
                    'scope_type' => 'GLOBAL_PLATFORM',
                    'granted' => [['password' => 'x']],
                    'not_granted' => [],
                ],
            ));
        } finally {
            $this->assertNull(
                AuditRecord::where('event_type', 'rbac.super_admin.canonically_authorized')->first(),
                'A rejected metadata payload must never reach canonical storage.'
            );
        }
    }

    public function test_associative_array_metadata_value_is_rejected(): void
    {
        // Even without a prohibited key present, an associative (non-list)
        // array is never a valid 'array'-typed value — only a flat,
        // sequential list of scalars is permitted.
        $this->expectException(AuditMetadataViolationException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'rbac.super_admin.canonically_authorized',
            actor: null,
            subjectType: 'principal',
            subjectId: null,
            metadata: [
                'role' => 'super_admin',
                'scope_type' => 'GLOBAL_PLATFORM',
                'granted' => ['label' => 'role: super_admin'],
                'not_granted' => [],
            ],
        ));
    }

    public function test_deeply_nested_array_metadata_value_is_rejected(): void
    {
        $this->expectException(AuditMetadataViolationException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'rbac.super_admin.canonically_authorized',
            actor: null,
            subjectType: 'principal',
            subjectId: null,
            metadata: [
                'role' => 'super_admin',
                'scope_type' => 'GLOBAL_PLATFORM',
                'granted' => [['nested', ['still_nested']]],
                'not_granted' => [],
            ],
        ));
    }

    public function test_flat_scalar_list_array_metadata_value_is_accepted(): void
    {
        // The legitimate shape (a flat sequential list of scalars) must
        // still be accepted — this is the non-regression counterpart to the
        // rejection tests above.
        $subject = $this->makePlainPrincipal();

        $record = app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'rbac.super_admin.canonically_authorized',
            actor: null,
            subjectType: 'principal',
            subjectId: $subject->id,
            metadata: [
                'role' => 'super_admin',
                'scope_type' => 'GLOBAL_PLATFORM',
                'granted' => ['role: super_admin'],
                'not_granted' => ['financial_authority', 'business_authority'],
            ],
        ));

        $this->assertNotNull($record);
        $this->assertSame(['role: super_admin'], $record->metadata['granted']);
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

    public function test_grant_rejects_ambient_transaction_with_ownership_violation(): void
    {
        // IMP004-IMPL-M01: grant() must own the outermost transaction. An
        // ambient (already-open) transaction on the connection is a
        // calling-contract violation, rejected BEFORE any lock, mutation, or
        // authorization evaluation runs — never treated as, or persisted as,
        // an authorization-denial outcome. This suite uses DatabaseTruncation
        // (not RefreshDatabase) precisely so this test can open its own
        // ambient transaction deliberately and still start from level 0.
        $this->assertSame(0, DB::connection()->transactionLevel());

        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_found_ownership_violation', 'name' => 'Audit Found Ownership Violation']);
        $permission = Permission::create(['code' => 'audit.found.ownership_violation', 'description' => 'test']);

        DB::beginTransaction();

        try {
            try {
                app(RolePermissionService::class)->grant($actor, $role, $permission);
                $this->fail('Expected TransactionOwnershipViolationException under an ambient transaction.');
            } catch (TransactionOwnershipViolationException $e) {
                // expected
            }
        } finally {
            DB::rollBack();
        }

        $this->assertSame(
            0,
            DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->count(),
            'No business mutation may occur when the invariant rejects the call.'
        );
        $this->assertNull(
            AuditRecord::where('event_type', 'security.authorization.denied')
                ->where('actor_principal_id', $actor->id)
                ->first(),
            'An ownership-contract violation must never be persisted as an authorization-denial outcome.'
        );
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

    public function test_invitation_issued_with_no_issuer_is_rejected_fail_closed(): void
    {
        // IMP004-IMPL-M03: the approved actor catalog is NOT expanded to
        // cover invitation issue/revoke — PrePrincipalSystem remains reserved
        // for the specific, named Q25 bootstrap CLI path only. A null issuer
        // (an intentional, locked IMP-002 "Invitation Boundary" possibility —
        // "records who revoked it, where available") has no approved
        // canonical attribution under IMP-004 and is REJECTED fail-closed
        // here, rather than silently attributed to a fabricated or expanded
        // actor kind. This is the flagged IMP-002/IMP-004 contradiction — see
        // docs/audits/IMP-004-OWNERSHIP-HANDOFF.md.
        $this->expectException(AuditActorAttributionException::class);

        app(IdentityAuditLogger::class)->record('invitation_issued', null, [
            'invitation_id' => 999999,
            'invitation_public_id' => 'test-public-id',
            'invited_actor' => 'partner_representative',
        ]);
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

    /**
     * IMP004-IMPL-M04: no currently-migrated event declares a source_domain
     * policy, so exercising the full idempotency-comparison contract
     * requires a throwaway, test-only registry entry that DOES — registered
     * into its OWN AuditEventRegistry instance and bound into the container
     * only for the duration of one test (never touching the canonical
     * 29-active/6-reserved inventory any other test asserts against).
     */
    private function bindRegistryWithSourceDomainProbeEvent(): void
    {
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

        $this->app->instance(AuditEventRegistry::class, $registry);
    }

    public function test_idempotent_replay_with_identical_immutable_fields_returns_existing_record(): void
    {
        $this->bindRegistryWithSourceDomainProbeEvent();
        $actor = $this->makePlainPrincipal();

        $first = app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe',
            actor: $actor,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['label' => 'same'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-1',
        ));

        $second = app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe',
            actor: $actor,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['label' => 'same'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-1',
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            AuditRecord::where('event_type', 'test.idempotency.probe')->where('source_event_id', 'probe-key-1')->count()
        );
    }

    public function test_idempotency_key_reuse_with_changed_metadata_is_rejected(): void
    {
        $this->bindRegistryWithSourceDomainProbeEvent();
        $actor = $this->makePlainPrincipal();

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe',
            actor: $actor,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['label' => 'original'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-2',
        ));

        $this->expectException(AuditIdempotencyConflictException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe',
            actor: $actor,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['label' => 'changed'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-2',
        ));
    }

    public function test_idempotency_key_reuse_with_changed_actor_attribution_is_rejected(): void
    {
        $this->bindRegistryWithSourceDomainProbeEvent();
        $actorA = $this->makePlainPrincipal();
        $actorB = $this->makePlainPrincipal();

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe',
            actor: $actorA,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['label' => 'same'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-3',
        ));

        $this->expectException(AuditIdempotencyConflictException::class);

        app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe',
            actor: $actorB,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['label' => 'same'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-3',
        ));
    }

    public function test_idempotent_replay_is_insensitive_to_metadata_key_order(): void
    {
        // The canonical comparison must be over CONTENT, not raw JSON byte
        // ordering — key order is never a legitimate basis for a conflict.
        $this->bindRegistryWithSourceDomainProbeEvent();

        $registry = app(AuditEventRegistry::class);
        $registry->register(new AuditEventDefinition(
            eventType: 'test.idempotency.probe.multi',
            eventVersion: 1,
            criticality: AuditCriticality::NonCritical,
            persistenceStrategy: null,
            visibilityClass: AuditVisibilityClass::General,
            subjectType: 'probe',
            subjectIdNullable: true,
            metadataAllowList: ['a' => 'string', 'b' => 'string'],
            actorKinds: [AuditActorKind::Human],
            executionContext: null,
            requiresElevatedAssuranceToRead: false,
            scopeType: ScopeType::GlobalPlatform,
            sourceDomain: 'test_probe_domain',
        ));

        $actor = $this->makePlainPrincipal();

        $first = app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe.multi',
            actor: $actor,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['a' => '1', 'b' => '2'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-4',
        ));

        $second = app(AuditWriter::class)->record(new AuditEventInput(
            eventType: 'test.idempotency.probe.multi',
            actor: $actor,
            subjectType: 'probe',
            subjectId: null,
            metadata: ['b' => '2', 'a' => '1'],
            sourceDomain: 'test_probe_domain',
            sourceEventId: 'probe-key-4',
        ));

        $this->assertSame($first->id, $second->id);
    }

    public function test_concurrent_same_source_key_insertion_yields_exactly_one_canonical_record(): void
    {
        // MySQL only: SQLite's single-writer-connection model cannot
        // exercise genuine concurrent insertion. Two truly independent OS
        // processes (not merely two sequential calls in this same process,
        // which could never actually race the pre-insert check) each race
        // the composite (source_domain, source_event_id, event_type)
        // DB-level uniqueness constraint — the loser must fall back to the
        // QueryException/isUniqueViolation path, re-select, and converge on
        // the winner's row, never silently produce a second one.
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concurrency evidence requires MySQL; SQLite has no real concurrent writers.');
        }

        // Committed and visible to the child processes' own connections —
        // only true because this suite uses TruncatesInMemorySqlite (no
        // wrapping test transaction), never RefreshDatabase.
        $actor = $this->makePlainPrincipal();

        $script = base_path('tests/Support/scripts/audit_idempotency_probe_race.php');
        $sourceEventId = 'probe-key-concurrent-'.bin2hex(random_bytes(6));
        $barrierFile = storage_path('framework/testing/audit_probe_barrier_'.bin2hex(random_bytes(6)));

        if (file_exists($barrierFile)) {
            unlink($barrierFile);
        }

        $env = array_filter(
            array_merge($_ENV, $_SERVER, ['APP_ENV' => 'testing']),
            static fn ($value) => is_scalar($value)
        );
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = [PHP_BINARY, $script, $sourceEventId, (string) $actor->id, $barrierFile];

        $procA = proc_open($cmd, $descriptors, $pipesA, base_path(), $env);
        $procB = proc_open($cmd, $descriptors, $pipesB, base_path(), $env);

        $this->assertIsResource($procA);
        $this->assertIsResource($procB);

        usleep(100_000);
        touch($barrierFile);

        $outA = stream_get_contents($pipesA[1]);
        $errA = stream_get_contents($pipesA[2]);
        foreach ($pipesA as $pipe) {
            fclose($pipe);
        }
        $statusA = proc_close($procA);

        $outB = stream_get_contents($pipesB[1]);
        $errB = stream_get_contents($pipesB[2]);
        foreach ($pipesB as $pipe) {
            fclose($pipe);
        }
        $statusB = proc_close($procB);

        if (file_exists($barrierFile)) {
            unlink($barrierFile);
        }

        $this->assertSame(0, $statusA, "Probe process A failed: {$errA}");
        $this->assertSame(0, $statusB, "Probe process B failed: {$errB}");
        $this->assertStringStartsWith('OK:', trim($outA), "Process A: {$outA} {$errA}");
        $this->assertStringStartsWith('OK:', trim($outB), "Process B: {$outB} {$errB}");

        $idA = (int) substr(trim($outA), 3);
        $idB = (int) substr(trim($outB), 3);

        $this->assertSame(
            $idA,
            $idB,
            'Two genuinely concurrent OS processes racing the same idempotency key must converge on exactly one canonical row.'
        );
        $this->assertSame(
            1,
            AuditRecord::where('event_type', 'test.idempotency.probe')->where('source_event_id', $sourceEventId)->count()
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

    /**
     * A reader granted ONLY audit.read.security (never audit.read) —
     * SECURITY-visibility events are readable, GENERAL-visibility events are
     * not, for the exact same reader in the exact same result set. Mirrors
     * makeAuthorizedActor()'s internal-setup bypass pattern rather than
     * routing through RolePermissionService, to keep the grant scoped to
     * precisely one permission.
     */
    private function makeSecurityOnlyReader(): Principal
    {
        $user = User::create([
            'email' => 'audit-found-security-reader-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create(['code' => 'audit_found_security_reader_role_'.uniqid(), 'name' => 'Security-Only Reader']);
        $permission = Permission::firstOrCreate(
            ['code' => PermissionRegistry::AUDIT_READ_SECURITY],
            ['description' => 'test']
        );
        $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $principal;
    }

    public function test_query_service_pagination_never_returns_unauthorized_records_and_is_deterministic(): void
    {
        // IMP004-IMPL-m01: interleave GENERAL-visibility (identity.user.created,
        // requires audit.read) and SECURITY-visibility (rbac.role_permission.granted,
        // requires audit.read.security) records for the SAME reader, who holds
        // ONLY audit.read.security — so within one ordered result set some
        // records are authorized and some are not, and a naive
        // skip()/take()-then-filter implementation would silently shrink or
        // skip pages instead of always filling perPage with authorized rows
        // (until genuinely exhausted).
        $reader = $this->makeSecurityOnlyReader();
        $writer = app(AuditWriter::class);
        $identityAuditLogger = app(IdentityAuditLogger::class);

        $expectedVisibleIds = [];

        for ($i = 0; $i < 12; $i++) {
            $subjectUser = User::create([
                'email' => 'audit-found-query-subject-'.uniqid().'@example.com',
                'password' => Hash::make('correct-horse-battery-staple'),
            ]);

            // Unauthorized to this reader (GENERAL visibility).
            $identityAuditLogger->record('identity_created', $subjectUser);

            // Authorized to this reader (SECURITY visibility).
            $actor = $this->makePlainPrincipal();
            $record = $writer->record(new AuditEventInput(
                eventType: 'rbac.role_permission.granted',
                actor: $actor,
                subjectType: 'role_permission',
                subjectId: null,
                metadata: ['role_id' => $i + 1, 'permission_id' => $i + 1],
            ));
            $expectedVisibleIds[] = $record->id;
        }

        $service = app(AuditQueryService::class);
        $filter = new AuditQueryFilter(eventType: null, perPage: 5, page: 1);

        $seenIds = [];
        $page = 1;

        while (true) {
            $result = $service->search($reader, new AuditQueryFilter(eventType: null, perPage: 5, page: $page));

            if ($result['records'] === []) {
                break;
            }

            foreach ($result['records'] as $record) {
                // Never an unauthorized (GENERAL) record.
                $this->assertSame('rbac.role_permission.granted', $record->event_type);
                $this->assertNotContains($record->id, $seenIds, 'No record may appear on more than one page.');
                $seenIds[] = $record->id;
            }

            $page++;

            if ($page > 20) {
                $this->fail('Pagination did not terminate — possible infinite loop.');
            }
        }

        // Every authorized record was eventually surfaced, across however
        // many pages it took, none skipped because unauthorized rows
        // occupied the same raw-offset window.
        sort($seenIds);
        $sortedExpected = $expectedVisibleIds;
        sort($sortedExpected);
        $this->assertSame($sortedExpected, $seenIds);

        // Re-running the identical first-page query is stable/deterministic.
        $again = $service->search($reader, $filter);
        $this->assertSame(
            array_map(fn ($r) => $r->id, $again['records']),
            array_map(fn ($r) => $r->id, $service->search($reader, $filter)['records'])
        );
    }

    public function test_query_service_authorized_count_never_leaks_unauthorized_total(): void
    {
        // The `authorized_count` field is scoped to records actually
        // returned on THIS page — never a hint about how many additional
        // (unauthorized) rows exist in the underlying table.
        $reader = $this->makeSecurityOnlyReader();
        $identityAuditLogger = app(IdentityAuditLogger::class);

        for ($i = 0; $i < 5; $i++) {
            $subjectUser = User::create([
                'email' => 'audit-found-count-subject-'.uniqid().'@example.com',
                'password' => Hash::make('correct-horse-battery-staple'),
            ]);
            $identityAuditLogger->record('identity_created', $subjectUser);
        }

        $result = app(AuditQueryService::class)->search($reader, new AuditQueryFilter(eventType: 'identity.user.created'));

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
