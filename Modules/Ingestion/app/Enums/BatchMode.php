<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum BatchMode: string
{
    case Pull = 'pull';
    case UploadReplace = 'upload_replace';
    case UploadAppend = 'upload_append';

    public function label(): string
    {
        return match ($this) {
            self::Pull => 'Pulled from source system',
            self::UploadReplace => 'Uploaded (replace)',
            self::UploadAppend => 'Uploaded (append)',
        };
    }

    public function origin(): BatchOrigin
    {
        return $this === self::Pull ? BatchOrigin::SourceSystem : BatchOrigin::Upload;
    }
}
