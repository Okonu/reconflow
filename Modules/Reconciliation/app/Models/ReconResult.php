<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;

final class ReconResult extends Model
{
    public $timestamps = false;

    protected $table = 'recon_results';

    protected $guarded = ['id'];

    protected $casts = [
        'business_date' => 'immutable_date',
        'prior_date' => 'immutable_date',
        'section' => ResultSection::class,
        'status' => ReconStatus::class,
        'payment_ids' => 'array',
        'payment_record_ids' => 'array',
        'payment_identities' => 'array',
        'flags' => 'array',
        'expected_amount' => MoneyCast::class,
        'actual_amount' => MoneyCast::class,
        'posted_amount' => MoneyCast::class,
        'variance' => MoneyCast::class,
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconRun::class, 'run_id');
    }

    public function itemState(): HasOne
    {
        return $this->hasOne(ItemStateRecord::class, 'result_id');
    }
}
