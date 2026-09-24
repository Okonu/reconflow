<?php

declare(strict_types=1);

namespace Modules\DataProtection\Http\Controllers;

use App\Contracts\AuditActor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\DataProtection\Actions\EraseSubject;
use Modules\DataProtection\Http\Requests\EraseSubjectRequest;

final class ErasureController extends Controller
{
    public function __invoke(EraseSubjectRequest $request, EraseSubject $erase): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof AuditActor, 401);
        $counts = $erase->handle($actor, (string) $request->validated('phone'), trim((string) $request->validated('reason')), (string) $request->validated('request_reference'));

        return response()->json(['records_anonymised' => $counts, 'total' => array_sum($counts)]);
    }
}
