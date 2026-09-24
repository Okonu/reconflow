<?php

declare(strict_types=1);

namespace Modules\DataProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Services\AuditLogger;
use Modules\DataProtection\Enums\DataProtectionAuditAction;
use Modules\DataProtection\Support\FieldInventory;

final class UnmaskController extends Controller
{
    private const TABLES = ['sales' => 'sales_records', 'payments' => 'payment_records'];

    public function __invoke(Request $request, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('unmaskPersonalData');
        $data = $request->validate([
            'dataset' => ['required', 'in:'.implode(',', array_keys(self::TABLES))],
            'record_id' => ['required', 'integer'],
            'purpose' => ['required', 'string', 'min:5', 'max:300'],
        ]);
        $fields = FieldInventory::personalFields($data['dataset']);
        $record = DB::table(self::TABLES[$data['dataset']])->where('id', $data['record_id'])->first($fields);
        abort_if($record === null, 404);
        $audit->record(DataProtectionAuditAction::Unmasked, $request->user(), self::TABLES[$data['dataset']], (int) $data['record_id'], [
            'fields' => $fields,
            'purpose' => trim($data['purpose']),
        ]);

        return response()->json(['values' => (array) $record]);
    }
}
