<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Contracts\AuditActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Connectors\SourceConnector;
use Modules\Ingestion\DTOs\BatchMeta;
use Modules\Ingestion\DTOs\PullOutcome;
use Modules\Ingestion\Enums\BatchMode;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\ActiveDataset;
use Modules\Ingestion\Services\BatchValidator;
use Modules\Ingestion\Services\BatchWriter;
use Modules\Ingestion\Support\Schema\ValidationContext;

final class IngestFromSourceSystem
{
    public function __construct(
        private readonly SourceConnector $connector,
        private readonly BatchValidator $validator,
        private readonly BatchWriter $writer,
        private readonly ActiveDataset $dataset,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(SourceType $source, string $businessDate, AuditActor|string|null $actor = null, bool $replaceManual = false): PullOutcome
    {
        $active = $this->dataset->activeBatch($source, $businessDate);
        if ($active !== null && $active->manual && ! $replaceManual) {
            Log::info('manual upload in effect', ['source' => $source->value, 'business_date' => $businessDate, 'batch_id' => $active->id]);

            return new PullOutcome($active, PullOutcome::MANUAL_IN_EFFECT);
        }

        $extract = $this->connector->fetch($source, $businessDate);
        $checksum = $extract->checksum();
        if ($active !== null && ! $active->manual && hash_equals($active->checksum, $checksum)) {
            return new PullOutcome($active, PullOutcome::UNCHANGED);
        }

        $outcome = $this->validator->validate($source, $extract->numberedRows(), ValidationContext::forDate($businessDate));

        return DB::transaction(function () use ($source, $businessDate, $extract, $checksum, $outcome, $actor, $active): PullOutcome {
            $batch = $this->writer->write(new BatchMeta(
                source: $source,
                businessDate: $businessDate,
                mode: BatchMode::Pull,
                checksum: $checksum,
                extractedAt: $extract->extractedAt,
            ), $outcome);

            $this->audit->record(IngestionAuditAction::BatchIngested, $actor, 'source_batch', $batch->id, [
                'source' => $source->value,
                'business_date' => $businessDate,
                'system' => $extract->system,
                'version' => $batch->version,
                'rows_received' => $batch->rows_received,
                'rows_loaded' => $batch->rows_loaded,
                'rows_quarantined' => $batch->rows_quarantined,
                'superseded_batch_id' => $active?->id,
                'replaced_manual_upload' => $active !== null && $active->manual,
            ]);

            return new PullOutcome($batch, PullOutcome::CREATED);
        });
    }
}
