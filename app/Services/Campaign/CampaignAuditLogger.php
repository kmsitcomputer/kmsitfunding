<?php

namespace App\Services\Campaign;

use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;

/**
 * IMP-007 — thin sink wrapper over the canonical AuditWriter, mirroring
 * ThemeAuditLogger exactly. One method per event registered in
 * CampaignAuditEventRegistrar.
 */
class CampaignAuditLogger
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function recordProgramCreated(int $programId, array $metadata, Principal $actor): void
    {
        $this->emit('program.created', 'program', $programId, $metadata, $actor);
    }

    public function recordProgramUpdated(int $programId, array $metadata, Principal $actor): void
    {
        $this->emit('program.updated', 'program', $programId, $metadata, $actor);
    }

    public function recordProgramPublished(int $programId, Principal $actor): void
    {
        $this->emit('program.published', 'program', $programId, [], $actor);
    }

    public function recordProgramUnpublished(int $programId, Principal $actor): void
    {
        $this->emit('program.unpublished', 'program', $programId, [], $actor);
    }

    public function recordProgramArchived(int $programId, array $metadata, Principal $actor): void
    {
        $this->emit('program.archived', 'program', $programId, $metadata, $actor);
    }

    public function recordProgramMediaUploaded(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('program.media.uploaded', 'program_media_asset', $assetId, $metadata, $actor);
    }

    public function recordProgramMediaArchived(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('program.media.archived', 'program_media_asset', $assetId, $metadata, $actor);
    }

    public function recordCampaignCreated(int $campaignId, array $metadata, Principal $actor): void
    {
        $this->emit('campaign.created', 'campaign', $campaignId, $metadata, $actor);
    }

    public function recordCampaignUpdated(int $campaignId, array $metadata, Principal $actor): void
    {
        $this->emit('campaign.updated', 'campaign', $campaignId, $metadata, $actor);
    }

    public function recordCampaignSubmitted(int $campaignId, Principal $actor): void
    {
        $this->emit('campaign.submitted', 'campaign', $campaignId, [], $actor);
    }

    public function recordCampaignRejected(int $campaignId, string $reason, Principal $actor): void
    {
        $this->emit('campaign.rejected', 'campaign', $campaignId, ['reason' => $reason], $actor);
    }

    public function recordCampaignApproved(int $campaignId, Principal $actor): void
    {
        $this->emit('campaign.approved', 'campaign', $campaignId, [], $actor);
    }

    public function recordCampaignPublished(int $campaignId, array $metadata, Principal $actor): void
    {
        $this->emit('campaign.published', 'campaign', $campaignId, $metadata, $actor);
    }

    public function recordCampaignClosed(int $campaignId, Principal $actor): void
    {
        $this->emit('campaign.closed', 'campaign', $campaignId, [], $actor);
    }

    public function recordCampaignFundAssigned(int $campaignId, array $metadata, Principal $actor): void
    {
        $this->emit('campaign.fund_assigned', 'campaign', $campaignId, $metadata, $actor);
    }

    public function recordCampaignMediaUploaded(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('campaign.media.uploaded', 'campaign_media_asset', $assetId, $metadata, $actor);
    }

    public function recordCampaignMediaArchived(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('campaign.media.archived', 'campaign_media_asset', $assetId, $metadata, $actor);
    }

    public function recordFundCreated(int $fundId, array $metadata, Principal $actor): void
    {
        $this->emit('fund.created', 'fund', $fundId, $metadata, $actor);
    }

    public function recordFundUpdated(int $fundId, array $metadata, Principal $actor): void
    {
        $this->emit('fund.updated', 'fund', $fundId, $metadata, $actor);
    }

    public function recordFundArchived(int $fundId, Principal $actor): void
    {
        $this->emit('fund.archived', 'fund', $fundId, [], $actor);
    }

    private function emit(string $eventType, string $subjectType, int $subjectId, array $metadata, Principal $actor): void
    {
        $this->writer->record(new AuditEventInput(
            eventType: $eventType,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: $metadata,
        ));
    }
}
