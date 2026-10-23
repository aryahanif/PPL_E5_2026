<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IdentityProvider;
use App\Data\Project;
use App\Enums\RiskIssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRiskIssueRequest;
use App\Http\Requests\ListRiskIssuesRequest;
use App\Http\Requests\UpdateRiskIssueRequest;
use App\Http\Resources\RiskIssueResource;
use App\Models\Mom;
use App\Models\RiskIssue;
use App\Services\ProjectGuard;
use App\Services\RiskIssueStatusService;
use App\Services\StatusHistoryRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** API-K05-07 */
class RiskIssueController extends Controller
{
    /** Body fields of updateRiskIssue saved as they are. Status and PIC go through RiskIssueStatusService. */
    private const COLUMNS = [
        'type' => 'type',
        'title' => 'title',
        'description' => 'description',
        'impactLevel' => 'impact_level',
        'mitigation' => 'mitigation',
        'sourceMomId' => 'source_mom_id',
        'resolutionNote' => 'resolution_note',
    ];

    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private StatusHistoryRecorder $history,
        private RiskIssueStatusService $statuses,
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

        $data = $request->validated();
        $this->checkReferences($data, $project);

        $item = DB::transaction(function () use ($data, $account, $projectId) {
            $item = RiskIssue::create([
                'project_id' => $projectId,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'],
                'impact_level' => $data['impactLevel'] ?? null,
                'mitigation' => $data['mitigation'] ?? null,
                'pic_account_id' => $data['picAccountId'],
                'source_mom_id' => $data['sourceMomId'] ?? null,
                'status' => RiskIssueStatus::OPEN,
                'reported_by' => $account->accountId,
            ]);

            $this->history->record($item, null, RiskIssueStatus::OPEN, $account);

            return $item;
        });

        return (new RiskIssueResource($item->refresh()))
            ->response()->setStatusCode(201);
    }

    /** PATCH /api/v1/risk-issues/{riskIssueId}  (operationId: updateRiskIssue) */
    public function update(UpdateRiskIssueRequest $request, RiskIssue $riskIssue)
    {
        $account = $request->user();
        $project = $this->projects->forMember($riskIssue->project_id, $account);
        Gate::authorize('update', [$riskIssue, $request->fields()]);

        $data = $request->validated();
        $this->checkReferences($data, $project);

        DB::transaction(function () use ($riskIssue, $data, $account) {
            $columns = [];

            foreach (self::COLUMNS as $field => $column) {
                if (array_key_exists($field, $data)) {
                    $columns[$column] = $data[$field];
                }
            }

            $riskIssue->update($columns);

            if (isset($data['picAccountId'])) {
                $this->statuses->changePic($riskIssue, $data['picAccountId'], $account);
            }

            if (isset($data['status'])) {
                $this->statuses->change($riskIssue, RiskIssueStatus::from($data['status']), $account, $data['resolutionNote'] ?? null);
            }
        });

        return new RiskIssueResource($riskIssue->refresh());
    }

    /** Checks that need F-1 / D-1 or MoM data. Failures are VALIDATION_ERROR. */
    private function checkReferences(array $data, Project $project): void
    {
        $pic = $data['picAccountId'] ?? null;

        if ($pic !== null && (! $this->identity->find($pic) || ! $project->hasMember($pic))) {
            throw ValidationException::withMessages(['picAccountId' => "Akun {$pic} bukan anggota proyek"]);
        }

        $momId = $data['sourceMomId'] ?? null;

        if ($momId !== null && ! Mom::whereKey($momId)
            ->whereHas('meeting', fn ($q) => $q->where('project_id', $project->projectId))
            ->exists()) {
            throw ValidationException::withMessages(['sourceMomId' => 'MoM bukan milik proyek ini']);
        }
    }
}
