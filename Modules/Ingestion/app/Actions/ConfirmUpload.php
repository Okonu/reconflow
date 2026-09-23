<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\DTOs\BatchMeta;
use Modules\Ingestion\Enums\BatchOrigin;
use Modules\Ingestion\Enums\ImportMode;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\StagingState;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Services\ActiveDataset;
use Modules\Ingestion\Services\BatchValidator;
use Modules\Ingestion\Services\BatchWriter;
use Modules\Ingestion\Support\Schema\RowResult;
use Modules\Ingestion\Support\Schema\ValidationContext;
use Modules\Users\Models\User;

final class ConfirmUpload
{
    public function __construct(
        private readonly ActiveDataset $dataset,
        private readonly BatchValidator $validator,
        private readonly BatchWriter $writer,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, UploadStaging $staging, ?ImportMode $mode): SourceBatch
    {
        $this->assertConfirmable($staging);
        $source = $staging->source;
        $date = $staging->business_date->toDateString();
        $hasData = $this->dataset->hasData($source, $date);

        if ($hasData && $mode === null) {
            throw DomainException::invalid('Data already exists for this date. Choose Replace or Append.');
        }
        $mode ??= ImportMode::Replace;

        $raw = [];
        foreach ((array) $staging->rows as $row) {
            $result = RowResult::fromArray($row);
            $raw[$result->rowNumber] = $result->raw;
        }
        $existingKeys = $mode === ImportMode::Append ? $this->dataset->existingKeys($source, $date) : [];
        $outcome = $this->validator->validate($source, $raw, ValidationContext::forDate($date), $existingKeys);

        return DB::transaction(function () use ($user, $staging, $source, $date, $mode, $outcome, $hasData): SourceBatch {
            $superseded = $mode === ImportMode::Replace ? $this->dataset->activeBatchIds($source, $date) : [];
            $batch = $this->writer->write(new BatchMeta(
                source: $source,
                businessDate: $date,
                origin: BatchOrigin::Upload,
                mode: $mode,
                checksum: $staging->checksum,
                filename: $staging->filename,
                createdBy: $user->id,
            ), $outcome);

            $staging->update(['state' => StagingState::Confirmed, 'mode' => $mode, 'batch_id' => $batch->id, 'rows' => null]);

            $this->audit->record(IngestionAuditAction::UploadConfirmed, $user, 'upload', $staging->id, [
                'batch_id' => $batch->id,
                'batch_version' => $batch->version,
                'source' => $source->value,
                'business_date' => $date,
                'mode' => $mode->value,
                'replaced_existing_data' => $hasData && $mode === ImportMode::Replace,
                'rows_loaded' => $batch->rows_loaded,
                'rows_quarantined' => $batch->rows_quarantined,
            ]);
            if ($superseded !== []) {
                $this->audit->record(IngestionAuditAction::BatchSuperseded, $user, 'source_batch', $batch->id, [
                    'superseded_batch_ids' => $superseded,
                ]);
            }

            return $batch;
        });
    }

    private function assertConfirmable(UploadStaging $staging): void
    {
        if ($staging->state !== StagingState::Staged) {
            throw DomainException::conflict('This upload has already been '.$staging->state->value.'.');
        }
        if ($staging->expires_at->isPast()) {
            throw DomainException::conflict('This upload has expired. Upload the file again.');
        }
        if ($staging->isBlocked()) {
            throw DomainException::invalid('The file columns do not match the template, so it cannot be imported.');
        }
    }
}
