<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChangeLogRecordResource;
use App\Http\Resources\RiskIssueRecordResource;
use App\Services\ControlDataService;
use App\Services\ProjectGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** API-K05-09 raw project control data for group D-3. */
class ControlDataController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private ControlDataService $controlData,
    ) {}

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

        $this->projects->forMember($projectId, $request->user());

        $startUtc = CarbonImmutable::createFromFormat('!Y-m-d', $request->query('periodStart'), 'Asia/Jakarta')->utc();
        $endUtc = CarbonImmutable::createFromFormat('!Y-m-d', $request->query('periodEnd'), 'Asia/Jakarta')
            ->setTime(23, 59, 59)->utc();
        $records = $this->controlData->forPeriod($projectId, $startUtc, $endUtc);

        return response()->json([
            'projectId' => $projectId,
            'periodStart' => $request->query('periodStart'),
            'periodEnd' => $request->query('periodEnd'),
            'generatedAt' => CarbonImmutable::now('UTC')->toIso8601ZuluString(),
            'riskIssues' => RiskIssueRecordResource::collection($records['riskIssues'])->resolve($request),
            'changeLog' => ChangeLogRecordResource::collection($records['changeLog'])->resolve($request),
        ]);
    }
}
