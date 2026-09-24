<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Database\Eloquent\Model;

final class SettingVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['section', 'version', 'values', 'comment', 'created_by'];

    protected $casts = ['values' => 'array'];
}
