<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRiskIssueRequest;
use App\Http\Requests\ListRiskIssuesRequest;
use App\Http\Requests\UpdateRiskIssueRequest;
use App\Http\Resources\RiskIssueResource;
use App\Models\RiskIssue;
use App\Services\ProjectGuard;
use App\Services\RiskIssueWriter;
use Illuminate\Support\Facades\Gate;

/** API-K05-07 */
class RiskIssueController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private RiskIssueWriter $writer,
    ) {}

    /** GET /api/v1/projects/{projectId}/risk-issues  (operationId: listRiskIssues) */
    public function index(ListRiskIssuesRequest $request, string $projectId)
    {
        $this->projects->forMember($projectId, $request->user());
        Gate::authorize('viewAny', RiskIssue::class);

        $items = RiskIssue::query()
            ->where('project_id', $projectId)
            ->when($request->validated('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->validated('impactLevel'), fn ($q, $level) => $q->where('impact_level', $level))
            ->orderByDesc('created_at')
            ->get();

        return RiskIssueResource::collection($items);
    }

    /** POST /api/v1/projects/{projectId}/risk-issues  (operationId: createRiskIssue) */
    public function store(CreateRiskIssueRequest $request, string $projectId)
    {
        $account = $request->user();
        $project = $this->projects->forActiveMember($projectId, $account);
        Gate::authorize('create', RiskIssue::class);

        $item = $this->writer->create($request->validated(), $project, $account);

        return (new RiskIssueResource($item))
            ->response()->setStatusCode(201);
    }

    /** PATCH /api/v1/risk-issues/{riskIssueId}  (operationId: updateRiskIssue) */
    public function update(UpdateRiskIssueRequest $request, RiskIssue $riskIssue)
    {
        $account = $request->user();
        $project = $this->projects->forMember($riskIssue->project_id, $account);
        Gate::authorize('update', [$riskIssue, $request->fields()]);

        $item = $this->writer->update($riskIssue, $request->validated(), $project, $account);

        return new RiskIssueResource($item);
    }
}
