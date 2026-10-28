<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Data\Account;
use App\Data\Project;
use App\Enums\ImpactLevel;
use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Mom;
use App\Models\RiskIssue;
use App\Models\StatusHistory;
use App\Policies\RiskIssuePolicy;
use App\Services\ProjectGuard;
use App\Services\RiskIssueStatusService;
use App\Services\RiskIssueWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Risk and issue register: list, record (PM), detail and update (PM, TL as PIC) (UI-K05-06). */
class RiskIssuePageController extends Controller
{
    private const MESSAGES = [
        'type.required' => 'Jenis wajib dipilih',
        'type.*' => 'Jenis harus RISK atau ISSUE',
        'title.required' => 'Judul wajib diisi',
        'title.max' => 'Judul maksimal 150 karakter',
        'description.required' => 'Deskripsi wajib diisi',
        'impactLevel.*' => 'Tingkat dampak harus LOW, MEDIUM, atau HIGH',
        'picAccountId.required' => 'PIC wajib dipilih',
        'sourceMomId.*' => 'MoM sumber tidak valid',
        'status.required' => 'Status wajib dipilih',
        'status.*' => 'Status tidak valid',
        'resolutionNote.required_if' => 'Catatan penyelesaian wajib diisi saat status CLOSED',
    ];

    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private RiskIssueWriter $writer,
        private RiskIssueStatusService $statuses,
    ) {}

    public function index(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());
        Gate::authorize('viewAny', RiskIssue::class);

        $all = RiskIssue::where('project_id', $projectId)->orderByDesc('created_at')->get();

        $status = RiskIssueStatus::tryFrom((string) $request->query('status'));
        $type = RiskIssueType::tryFrom((string) $request->query('type'));

        return view('risk-issues.index', [
            'project' => $project,
            'items' => $all
                ->when($status, fn ($items) => $items->where('status', $status))
                ->when($type, fn ($items) => $items->where('type', $type)),
            'all' => $all,
            'status' => $status,
            'type' => $type,
            'people' => collect($this->identity->all())->keyBy('accountId'),
        ]);
    }

    public function create(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());
        Gate::authorize('create', RiskIssue::class);

        return view('risk-issues.create', [
            'project' => $project,
            'members' => $this->members($project),
            'moms' => $this->moms($project),
        ]);
    }

    public function store(Request $request, string $projectId)
    {
        $account = $request->user();
        $project = $this->projects->forActiveMember($projectId, $account);
        Gate::authorize('create', RiskIssue::class);

        $data = $request->validate([
            'type' => ['required', Rule::enum(RiskIssueType::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'impactLevel' => ['nullable', Rule::enum(ImpactLevel::class)],
            'mitigation' => ['nullable', 'string'],
            'picAccountId' => ['required', 'string', 'max:64'],
            'sourceMomId' => ['nullable', 'uuid'],
        ], self::MESSAGES);

        $item = $this->writer->create($data, $project, $account);

        return redirect("/risk-issues/{$item->id}")
            ->with('status', ($item->type === RiskIssueType::RISK ? 'Risiko' : 'Isu').' berhasil dicatat');
    }

    public function show(Request $request, RiskIssue $riskIssue)
    {
        $account = $request->user();
        $project = $this->projects->forMember($riskIssue->project_id, $account);
        Gate::authorize('viewAny', RiskIssue::class);

        $riskIssue->load('sourceMom.meeting');

        $history = StatusHistory::query()
            ->where('entity_type', RiskIssue::HISTORY_TYPE)
            ->where('entity_id', $riskIssue->id)
            ->orderByDesc('changed_at')
            ->get();

        return view('risk-issues.show', [
            'project' => $project,
            'item' => $riskIssue,
            'history' => $history,
            'people' => collect($this->identity->all())->keyBy('accountId'),
            'canEdit' => Gate::allows('update', [$riskIssue, $this->editableFields($account)]),
            'fullEdit' => $account->is(Role::PM),
            'statuses' => [$riskIssue->status, ...$this->statuses->next($riskIssue->status)],
            'members' => $this->members($project),
            'moms' => $this->moms($project),
        ]);
    }

    public function update(Request $request, RiskIssue $riskIssue)
    {
        $account = $request->user();
        $project = $this->projects->forMember($riskIssue->project_id, $account);

        $fields = $this->editableFields($account);
        Gate::authorize('update', [$riskIssue, $fields]);

        $rules = [
            'type' => ['required', Rule::enum(RiskIssueType::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'impactLevel' => ['nullable', Rule::enum(ImpactLevel::class)],
            'mitigation' => ['nullable', 'string'],
            'picAccountId' => ['required', 'string', 'max:64'],
            'sourceMomId' => ['nullable', 'uuid'],
            'status' => ['required', Rule::enum(RiskIssueStatus::class)],
            'resolutionNote' => ['required_if:status,CLOSED', 'nullable', 'string'],
        ];

        // Fields left out of the form are not sent, so they keep their value.
        $data = $request->validate(array_intersect_key($rules, array_flip($fields)), self::MESSAGES);

        $this->writer->update($riskIssue, $data, $project, $account);

        return redirect("/risk-issues/{$riskIssue->id}")->with('status', 'Perubahan berhasil disimpan');
    }

    /** @return list<string> camelCase fields the update form shows to this account (RiskIssuePolicy) */
    private function editableFields(Account $account): array
    {
        return $account->is(Role::PM)
            ? ['type', 'title', 'description', 'impactLevel', 'mitigation', 'picAccountId', 'sourceMomId', 'status', 'resolutionNote']
            : RiskIssuePolicy::TL_FIELDS;
    }

    /** @return list<Account> project members known to F-1 */
    private function members(Project $project): array
    {
        return collect($project->memberAccountIds)
            ->map(fn ($id) => $this->identity->find($id))
            ->filter()
            ->values()
            ->all();
    }

    /** MoMs of the project, offered as the optional source of a risk/issue. */
    private function moms(Project $project)
    {
        return Mom::with('meeting')
            ->whereHas('meeting', fn ($q) => $q->where('project_id', $project->projectId))
            ->orderByDesc('meeting_date')
            ->get();
    }
}
