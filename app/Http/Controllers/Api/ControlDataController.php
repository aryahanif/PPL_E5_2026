<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;

/**
 * API-K05-09 for group D-3. SLICE-1 STUB: returns examples/success.json with
 * the requested projectId and period, so D-3 can mock against the contract.
 * The real query (risks/issues + decided change requests) comes in slice 4.
 */
class ControlDataController extends Controller
{
    public function __construct(private ProjectGuard $projects) {}

    /** GET /api/v1/projects/{projectId}/control-data  (operationId: getProjectControlData) */
    public function show(Request $request, string $projectId)
    {
        $validator = validator($request->query(), [
            'periodStart' => ['required', 'date_format:Y-m-d'],
            'periodEnd' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails() || $request->query('periodEnd') < $request->query('periodStart')) {
            return response()->json([
                'code' => 'INVALID_PERIOD',
                'message' => 'periodStart dan periodEnd wajib berformat YYYY-MM-DD, dan periodEnd tidak boleh sebelum periodStart',
            ], 400);
        }

        // TODO slice 4: allow the D-3 service token as well (OPEN-05).
        $this->projects->forMember($projectId, $request->user());

        $example = json_decode(file_get_contents(base_path('examples/success.json')), true);

        return response()->json(array_merge($example, [
            'projectId' => $projectId,
            'periodStart' => $request->query('periodStart'),
            'periodEnd' => $request->query('periodEnd'),
            'generatedAt' => now()->toIso8601ZuluString(),
        ]));
    }
}
