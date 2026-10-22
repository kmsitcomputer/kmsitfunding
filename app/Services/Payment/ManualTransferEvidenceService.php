<?php

namespace App\Services\Payment;

use App\Models\Payment\ManualTransferEvidence;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IMP-009 — Manual Transfer evidence submission
 * (docs/implementation/IMP-009-payment-hub.md "Manual Transfer" /
 * HD-IMP009-06/08, "File Security"). Append-only: a resubmission is
 * always a NEW row, never an UPDATE; permitted only while the owning
 * Payment remains evidence-eligible (PENDING, no recorded
 * review_outcome). Terminal or already-reviewed Payments reject
 * further submission.
 *
 * Storage: private disk only, randomized opaque filename (never the
 * donor-supplied name), server-side MIME allow-list validation (never
 * trusting the client Content-Type alone), explicit size limit —
 * rejected before any storage write. No permanent public URL: the
 * stored path is never served statically.
 *
 * $actor NULL means a guest submission (submitted_by_principal_id
 * NULL, mirroring Donation's guest pattern); ownership is enforced by
 * the caller (controller/policy) for authenticated donors and by
 * Donation-ownership correlation for guests.
 */
class ManualTransferEvidenceService
{
    public function __construct(private readonly PaymentAuditLogger $auditLogger) {}

    public function submit(Payment $payment, UploadedFile $file, array $declared, ?Principal $actor): ManualTransferEvidence
    {
        $this->assertEligible($payment);
        $this->assertValidFile($file);

        return DB::transaction(function () use ($payment, $file, $declared, $actor) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $this->assertEligible($locked);

            $path = $this->store($file);

            $evidence = new ManualTransferEvidence;
            $evidence->forceFill([
                'payment_id' => $locked->id,
                'submitted_by_principal_id' => $actor?->id,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?? 0,
                'declared_amount_minor' => $declared['declared_amount_minor'] ?? null,
                'declared_currency' => $declared['declared_currency'] ?? null,
                'declared_transferred_at' => $declared['declared_transferred_at'] ?? null,
            ]);
            $evidence->save();

            $this->auditLogger->recordEvidenceSubmitted($evidence->id, [
                'payment_ulid' => $locked->ulid,
                'manual_transfer_evidence_ulid' => $evidence->ulid,
            ], $actor);

            return $evidence->fresh();
        });
    }

    private function assertEligible(Payment $payment): void
    {
        if ($payment->provider !== 'manual_transfer') {
            throw new PaymentValidationException(
                'not_manual_transfer',
                'Evidence may only be submitted for a Manual Bank Transfer payment.'
            );
        }

        if ($payment->status !== 'PENDING') {
            throw new PaymentTransitionConflictException(
                'payment_not_eligible',
                'Evidence may only be submitted while the payment is PENDING.'
            );
        }

        $alreadyReviewed = ManualTransferEvidence::query()
            ->where('payment_id', $payment->id)
            ->whereNotNull('review_outcome')
            ->exists();

        if ($alreadyReviewed) {
            throw new PaymentTransitionConflictException(
                'payment_already_reviewed',
                'Evidence may not be resubmitted once a review outcome has been recorded.'
            );
        }
    }

    private function assertValidFile(UploadedFile $file): void
    {
        $allowed = config('payment.evidence_allowed_mimes', ['image/jpeg', 'image/png', 'application/pdf']);
        $maxKilobytes = (int) config('payment.evidence_max_kilobytes', 5120);

        if (! $file->isValid()) {
            throw new PaymentValidationException('invalid_upload', 'The uploaded file is invalid.');
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, $allowed, true)) {
            throw new PaymentValidationException('disallowed_mime_type', 'This file type is not accepted as transfer evidence.');
        }

        if ($file->getSize() > $maxKilobytes * 1024) {
            throw new PaymentValidationException('file_too_large', 'The evidence file exceeds the maximum accepted size.');
        }
    }

    private function store(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $name = 'evidence/'.(string) Str::ulid().'.'.$extension;

        Storage::disk('local')->putFileAs('evidence', $file, basename($name));

        return $name;
    }
}
