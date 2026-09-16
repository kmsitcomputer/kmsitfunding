<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Program;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IMP-007 — Program identity CRUD + simple DRAFT/PUBLISHED/ARCHIVED
 * lifecycle (docs/implementation/IMP-007-campaign-program-fund.md section
 * 8/11). Authorization is a caller-side (HTTP boundary) concern, matching
 * every other service in this codebase.
 */
class ProgramService
{
    public function __construct(
        private readonly CampaignAuditLogger $auditLogger,
        private readonly CampaignContentSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{name:string,slug?:string,summary?:string,description_html?:string}  $payload
     */
    public function create(array $payload, Principal $actor): Program
    {
        return DB::transaction(function () use ($payload, $actor) {
            $slug = $payload['slug'] ?? Str::slug($payload['name']);

            if ($slug === '') {
                throw new CampaignValidationException('invalid_slug', 'A program slug must not be empty.');
            }

            if (Program::where('slug', $slug)->exists()) {
                throw new CampaignValidationException('slug_taken', "Program slug '{$slug}' is already in use.");
            }

            $program = new Program;
            $program->forceFill([
                'name' => $payload['name'],
                'slug' => $slug,
                'summary' => $payload['summary'] ?? null,
                'description_html' => isset($payload['description_html']) ? $this->sanitizer->sanitize($payload['description_html']) : null,
                'status' => 'DRAFT',
                'edit_version' => 0,
                'created_by_principal_id' => $actor->id,
                'updated_by_principal_id' => $actor->id,
            ]);
            $program->save();

            $this->auditLogger->recordProgramCreated($program->id, [
                'name' => $program->name,
                'slug' => $program->slug,
            ], $actor);

            return $program->fresh();
        });
    }

    /**
     * @param  array{name?:string,summary?:string,description_html?:string}  $payload
     */
    public function update(Program $program, array $payload, int $expectedEditVersion, Principal $actor): Program
    {
        return DB::transaction(function () use ($program, $payload, $expectedEditVersion, $actor) {
            $locked = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();

            if ($locked->edit_version !== $expectedEditVersion) {
                throw new CampaignValidationException(
                    'stale_edit_version',
                    'This program was changed by someone else — reload and try again.'
                );
            }

            $fieldsChanged = [];

            if (array_key_exists('description_html', $payload)) {
                $payload['description_html'] = $payload['description_html'] !== null
                    ? $this->sanitizer->sanitize($payload['description_html'])
                    : null;
            }

            foreach (['name', 'summary', 'description_html'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== $locked->{$field}) {
                    $locked->{$field} = $payload[$field];
                    $fieldsChanged[] = $field;
                }
            }

            $locked->edit_version++;
            $locked->updated_by_principal_id = $actor->id;
            $locked->save();

            $this->auditLogger->recordProgramUpdated($locked->id, [
                'fields_changed' => $fieldsChanged,
            ], $actor);

            return $locked;
        });
    }

    public function publish(Program $program, Principal $actor): Program
    {
        return DB::transaction(function () use ($program, $actor) {
            $locked = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['DRAFT', 'PUBLISHED'], true)) {
                throw new CampaignValidationException(
                    'invalid_publish_state',
                    "Program {$locked->id} is status={$locked->status}; only DRAFT may be published."
                );
            }

            $locked->forceFill(['status' => 'PUBLISHED', 'updated_by_principal_id' => $actor->id])->save();
            $this->auditLogger->recordProgramPublished($locked->id, $actor);

            return $locked;
        });
    }

    public function unpublish(Program $program, Principal $actor): Program
    {
        return DB::transaction(function () use ($program, $actor) {
            $locked = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'PUBLISHED') {
                throw new CampaignValidationException(
                    'invalid_unpublish_state',
                    "Program {$locked->id} is status={$locked->status}; only PUBLISHED may be unpublished."
                );
            }

            $locked->forceFill(['status' => 'DRAFT', 'updated_by_principal_id' => $actor->id])->save();
            $this->auditLogger->recordProgramUnpublished($locked->id, $actor);

            return $locked;
        });
    }

    public function archive(Program $program, Principal $actor): Program
    {
        return DB::transaction(function () use ($program, $actor) {
            $locked = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['DRAFT', 'PUBLISHED'], true)) {
                throw new CampaignValidationException(
                    'invalid_archive_state',
                    "Program {$locked->id} is status={$locked->status} and cannot be archived again."
                );
            }

            $fromStatus = $locked->status;
            $locked->forceFill(['status' => 'ARCHIVED', 'updated_by_principal_id' => $actor->id])->save();

            $this->auditLogger->recordProgramArchived($locked->id, ['from_status' => $fromStatus], $actor);

            return $locked;
        });
    }
}
