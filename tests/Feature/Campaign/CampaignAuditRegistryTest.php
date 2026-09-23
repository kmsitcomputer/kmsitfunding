<?php

namespace Tests\Feature\Campaign;

use App\Enums\ScopeType;
use App\Models\Audit\AuditRecord;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Audit\AuditReadAuthorizer;
use App\Services\Campaign\FundService;
use App\Services\Campaign\ProgramService;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 section 17 / 16 (HD-IMP007-02): fund.created/fund.updated/
 * fund.archived and campaign.fund_assigned must require
 * AUDIT_READ_FINANCIAL_REFERENCE to read, in addition to the ordinary
 * AUDIT_READ permission every other program/campaign/fund event needs.
 *
 * Regression coverage for a registry-definition defect found during
 * completion-phase review: a positional-argument mistake in
 * CampaignAuditEventRegistrar previously landed `true` on
 * requiresElevatedAssuranceToRead instead of subjectIsFinancialReference,
 * so these four events silently required NO financial-reference permission
 * at all (while imposing an undocumented ELEVATED-assurance requirement
 * instead).
 */
class CampaignAuditRegistryTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private const FINANCIAL_REFERENCE_EVENTS = [
        'fund.created', 'fund.updated', 'fund.archived', 'campaign.fund_assigned',
    ];

    public function test_fund_and_fund_assignment_events_are_flagged_as_financial_reference(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (self::FINANCIAL_REFERENCE_EVENTS as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertTrue($definition->subjectIsFinancialReference, "{$eventType} must be flagged as a financial reference");
            $this->assertFalse($definition->requiresElevatedAssuranceToRead, "{$eventType} does not require elevated assurance");
        }
    }

    public function test_other_program_and_campaign_events_carry_no_financial_reference(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (['program.created', 'campaign.created', 'campaign.published', 'campaign.closed'] as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertFalse($definition->subjectIsFinancialReference, "{$eventType} must not be a financial reference");
        }
    }

    public function test_plain_audit_read_permission_cannot_read_a_fund_archived_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $reader = $this->makeGrantedReader([PermissionRegistry::AUDIT_READ]);

        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f-'.uniqid()], $actor);
        app(FundService::class)->archive($fund, $actor);

        $record = AuditRecord::where('event_type', 'fund.archived')->where('subject_id', $fund->id)->latest('id')->firstOrFail();

        $this->assertFalse(app(AuditReadAuthorizer::class)->canRead($reader, $record));
    }

    public function test_audit_read_financial_reference_permission_can_read_a_fund_archived_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $reader = $this->makeGrantedReader([PermissionRegistry::AUDIT_READ, PermissionRegistry::AUDIT_READ_FINANCIAL_REFERENCE]);

        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f-'.uniqid()], $actor);
        app(FundService::class)->archive($fund, $actor);

        $record = AuditRecord::where('event_type', 'fund.archived')->where('subject_id', $fund->id)->latest('id')->firstOrFail();

        $this->assertTrue(app(AuditReadAuthorizer::class)->canRead($reader, $record));
    }

    public function test_plain_audit_read_permission_can_read_a_non_financial_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $reader = $this->makeGrantedReader([PermissionRegistry::AUDIT_READ]);

        $program = app(ProgramService::class)->create(['name' => 'P'], $actor);

        $record = AuditRecord::where('event_type', 'program.created')->where('subject_id', $program->id)->latest('id')->firstOrFail();

        $this->assertTrue(app(AuditReadAuthorizer::class)->canRead($reader, $record));
    }

    private function makeGrantedReader(array $permissionCodes): Principal
    {
        $reader = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'campaign_audit_reader_'.uniqid(), 'name' => 'Campaign Audit Reader']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $reader->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $reader;
    }
}
