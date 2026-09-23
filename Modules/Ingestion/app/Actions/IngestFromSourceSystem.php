<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Contracts\AuditActor;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Connectors\SourceConnector;
use Modules\Ingestion\DTOs\BatchMeta;
use Modules\Ingestion\Enums\BatchOrigin;
use Modules\Ingestion\Enums\ImportMode;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SourceBatch;
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

    public function handle(SourceType $source, string $businessDate, AuditActor|string|null $actor = null): SourceBatch
    {
        $extract = $this->connector->fetch($source, $businessDate);
        $outcome = $this->validator->validate($source, $extract->numberedRows(), ValidationContext::forDate($businessDate));

        return DB::transaction(function () use ($source, $businessDate, $extract, $outcome, $actor): SourceBatch {
            $superseded = $this->dataset->activeBatchIds($source, $businessDate);
            $batch = $this->writer->write(new BatchMeta(
                source: $source,
                businessDate: $businessDate,
                origin: BatchOrigin::SourceSystem,
                mode: ImportMode::Replace,
                checksum: $extract->checksum(),
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
                'superseded_batch_ids' => $superseded,
            ]);

            return $batch;
        });
    }
}
