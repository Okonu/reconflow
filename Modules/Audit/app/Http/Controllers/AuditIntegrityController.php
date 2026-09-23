<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Audit\Actions\VerifyAuditChain;
use Modules\Audit\Http\Resources\ChainVerificationResource;
use Modules\Audit\Models\AuditEvent;

final class AuditIntegrityController extends Controller
{
    public function __invoke(Request $request, VerifyAuditChain $verify): ChainVerificationResource
    {
        $this->authorize('verify', AuditEvent::class);

        return new ChainVerificationResource($verify->handle($request->user()));
    }
}
