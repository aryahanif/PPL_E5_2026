<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ProjectDirectory;
use App\Data\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Helper endpoints backed by the F-1 / D-1 adapters. */
class ContextController extends Controller
{
    public function __construct(private ProjectDirectory $projects) {}

    /** GET /api/v1/me  (operationId: getCurrentAccount) */
    public function me(Request $request)
    {
        $account = $request->user();

        return response()->json([
            'accountId' => $account->accountId,
            'displayName' => $account->displayName,
            'role' => $account->role->value,
            'projectIds' => array_map(
                fn (Project $p) => $p->projectId,
                $this->projects->activeForAccount($account->accountId),
            ),
        ]);
    }

    /** GET /api/v1/projects  (operationId: listMyProjects) */
    public function projects(Request $request)
    {
        return response()->json(array_map(fn (Project $p) => [
            'projectId' => $p->projectId,
            'name' => $p->name,
            'active' => $p->active,
        ], $this->projects->activeForAccount($request->user()->accountId)));
    }
}
