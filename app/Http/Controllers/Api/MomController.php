<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectGuard;

/**
 * API-K05-02 and API-K05-03: MoM endpoints. Shared file: each owner adds only
 * their own methods below. Follow the reference endpoint
 * (Api\MeetingController::index) and DESIGN.md §4.
 *
 * Guard a bound MoM's project with:
 *   $this->projects->forMember($mom->meeting->project_id, $request->user());
 */
class MomController extends Controller
{
    public function __construct(private ProjectGuard $projects) {}

    // TODO API-K05-02 (Iqbal): store()  POST /meetings/{meeting}/mom  (operationId: createMom)
    // TODO API-K05-02 (Iqbal): show()   GET  /moms/{mom}              (operationId: getMom)
    // TODO API-K05-02 (Iqbal): update() PUT  /moms/{mom}              (operationId: updateMom)

    // TODO API-K05-03 (Harits): updateStatus()  PATCH /moms/{mom}/status  (operationId: updateMomStatus)
}
