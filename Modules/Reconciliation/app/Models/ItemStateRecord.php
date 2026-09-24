<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reconciliation\Enums\ItemState;
use Modules\Reconciliation\Enums\ReconStatus;

final class ItemStateRecord extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $table = 'recon_item_states';

    protected $primaryKey = 'result_id';

    protected $fillable = ['result_id', 'business_date', 'state', 'effective_status', 'resolved_by_run_id', 'resolved_by_result_id', 'reason'];

    protected $casts = [
        'business_date' => 'immutable_date',
        'state' => ItemState::class,
        'effective_status' => ReconStatus::class,
    ];
}
