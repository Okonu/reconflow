<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Model;

final class MatchReview extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'recon_match_reviews';

    protected $fillable = ['business_date', 'transaction_id', 'payment_identity', 'decision', 'result_id', 'decided_by', 'reason'];

    protected $casts = ['business_date' => 'immutable_date'];
}
