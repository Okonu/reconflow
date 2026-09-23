<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use Illuminate\Http\UploadedFile;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Enums\StagingState;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Services\ActiveDataset;
use Modules\Ingestion\Services\BatchValidator;
use Modules\Ingestion\Services\SpreadsheetReader;
use Modules\Ingestion\Services\UploadGuard;
use Modules\Ingestion\Support\HeaderCheck;
use Modules\Ingestion\Support\Schema\ValidationContext;
use Modules\Users\Models\User;

final class StageUpload
{
    public function __construct(
        private readonly UploadGuard $guard,
        private readonly SpreadsheetReader $reader,
        private readonly BatchValidator $validator,
        private readonly ActiveDataset $dataset,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, SourceType $source, string $date, UploadedFile $file): UploadStaging
    {
        $extension = $this->guard->extensionOf($file);
        $path = (string) $file->getRealPath();
        $checksum = (string) hash_file('sha256', $path);
        $parsed = $this->reader->read($path, $extension, (int) config('ingestion.upload.max_rows'));
        $header = HeaderCheck::compare($source->schema(), $parsed->headers);

        $outcome = $header['ok'] ? $this->validator->validate($source, $parsed->rows, ValidationContext::forDate($date)) : null;
        $duplicate = $this->dataset->batchWithChecksum($source, $date, $checksum);

        $staging = UploadStaging::query()->create([
            'source' => $source,
            'business_date' => $date,
            'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum' => $checksum,
            'uploaded_by' => $user->id,
            'header_check' => $header,
            'rows' => $outcome?->toArray(),
            'rows_read' => count($parsed->rows),
            'rows_valid' => $outcome === null ? 0 : count($outcome->valid()),
            'rows_invalid' => $outcome === null ? 0 : count($outcome->invalid()),
            'duplicate_of_batch_id' => $duplicate?->id,
            'date_has_data' => $this->dataset->hasData($source, $date),
            'state' => StagingState::Staged,
            'expires_at' => now()->addHours((int) config('ingestion.upload.staging_ttl_hours')),
        ]);

        $this->audit->record(IngestionAuditAction::UploadStaged, $user, 'upload', $staging->id, [
            'source' => $source->value,
            'business_date' => $date,
            'filename' => $staging->filename,
            'checksum' => $checksum,
            'rows_read' => $staging->rows_read,
            'rows_valid' => $staging->rows_valid,
            'rows_invalid' => $staging->rows_invalid,
            'header_ok' => $header['ok'],
            'duplicate_of_batch_id' => $duplicate?->id,
        ]);

        return $staging;
    }
}
