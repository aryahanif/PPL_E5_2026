<?php

namespace App\Http\Controllers\Web;

use App\Contracts\BaselineDirectory;
use App\Contracts\IdentityProvider;
use App\Contracts\TaskDirectory;
use App\Data\Project;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use App\Enums\MomStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Mom;
use App\Models\StatusHistory;
use App\Services\ChangeRequestStatusService;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Change requests and change log: list, record (PM, TL), analyze (TL), decide (PM, TL) (UI-K05-05). */
class ChangeRequestPageController extends Controller
{
    private const MESSAGES = [
        'momId.required' => 'MoM sumber wajib dipilih',
        'momId.in' => 'Perubahan hanya dapat dicatat dari MoM berstatus FINAL di proyek ini',
        'changeDescription.required' => 'Deskripsi perubahan wajib diisi',
        'source.required' => 'Sumber perubahan wajib dipilih',
        'source.*' => 'Sumber perubahan harus USER atau INTERNAL',
        'changeType.required' => 'Jenis perubahan wajib dipilih',
        'changeType.*' => 'Jenis perubahan tidak valid',
        'isFormalCr.required' => 'Pilih apakah perubahan ini CR formal',
        'isFormalCr.*' => 'Pilihan CR formal tidak valid',
        'impact.required' => 'Analisis dampak wajib diisi',
        'affectedTaskIds.*' => 'Task terdampak tidak valid',
        'decision.required' => 'Keputusan wajib dipilih',
        'decision.*' => 'Keputusan harus APPROVED atau REJECTED',
        'decisionNote.required' => 'Alasan keputusan wajib diisi',
    ];

    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private BaselineDirectory $baselines,
        private TaskDirectory $tasks,
        private ChangeRequestStatusService $statuses,
    ) {}

    public function index(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());

        $all = ChangeRequest::with('mom.meeting')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get();

        $status = ChangeRequestStatus::tryFrom((string) $request->query('status'));

        return view('change-requests.index', [
            'project' => $project,
            'items' => $all->when($status, fn ($items) => $items->where('status', $status)),
            'all' => $all,
            'status' => $status,
            'people' => collect($this->identity->all())->keyBy('accountId'),
        ]);
    }

    public function create(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());
        Gate::authorize('create', ChangeRequest::class);

        return view('change-requests.create', [
            'project' => $project,
            'moms' => $this->finalMoms($project),
            'selectedMomId' => $request->query('momId'),
        ]);
    }

    public function store(Request $request, string $projectId)
    {
        $account = $request->user();
        $project = $this->projects->forActiveMember($projectId, $account);
        Gate::authorize('create', ChangeRequest::class);

        $data = $request->validate([
            'momId' => ['required', Rule::in($this->finalMoms($project)->pluck('id'))],
            'changeDescription' => ['required', 'string'],
            'source' => ['required', Rule::enum(ChangeSource::class)],
            'changeType' => ['required', Rule::enum(ChangeType::class)],
            'isFormalCr' => ['required', 'boolean'],
        ], self::MESSAGES);

        $data['isFormalCr'] = (bool) $data['isFormalCr'];
        $item = $this->statuses->record($data, $project->projectId, $account);

        return redirect("/change-requests/{$item->id}")
            ->with('status', $item->is_formal_cr ? 'CR berhasil dicatat dan menunggu analisis TL' : 'Penyesuaian berhasil dicatat di change log');
    }

    public function show(Request $request, ChangeRequest $changeRequest)
    {
        $project = $this->projects->forMember($changeRequest->project_id, $request->user());

        $changeRequest->load(['mom.meeting', 'affectedTasks']);

        $history = StatusHistory::query()
            ->where('entity_type', ChangeRequest::HISTORY_TYPE)
            ->where('entity_id', $changeRequest->id)
            ->orderByDesc('changed_at')
            ->get();

        return view('change-requests.show', [
            'project' => $project,
            'item' => $changeRequest,
            'history' => $history,
            'people' => collect($this->identity->all())->keyBy('accountId'),
            'baselineItems' => $this->baselines->forProject($project->projectId),
            'baseline' => $changeRequest->baseline_item_id ? $this->baselines->find($changeRequest->baseline_item_id) : null,
            'projectTasks' => collect($this->tasks->forProject($project->projectId))->keyBy('taskId'),
            'canAnalyze' => $changeRequest->status === ChangeRequestStatus::PENDING_ANALYSIS && Gate::allows('analyze', $changeRequest),
            'canDecide' => $changeRequest->status === ChangeRequestStatus::ANALYZED && Gate::allows('decide', $changeRequest),
        ]);
    }

    public function analyze(Request $request, ChangeRequest $changeRequest)
    {
        $this->projects->forMember($changeRequest->project_id, $request->user());
        Gate::authorize('analyze', $changeRequest);

        $data = $request->validate([
            'impact' => ['required', 'string'],
            'baselineItemId' => ['nullable', 'string', 'max:64'],
            'affectedTaskIds' => ['nullable', 'array'],
            'affectedTaskIds.*' => ['required', 'string', 'max:64', 'distinct'],
        ], self::MESSAGES);

        $data['affectedTaskIds'] ??= [];
        $this->statuses->analyze($changeRequest, $data, $request->user());

        return redirect("/change-requests/{$changeRequest->id}")->with('status', 'Analisis dampak berhasil disimpan');
    }

    public function decide(Request $request, ChangeRequest $changeRequest)
    {
        $this->projects->forMember($changeRequest->project_id, $request->user());
        Gate::authorize('decide', $changeRequest);

        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'decisionNote' => ['required', 'string'],
        ], self::MESSAGES);

        $to = ChangeRequestStatus::from($data['decision']);
        $this->statuses->decide($changeRequest, $to, $data['decisionNote'], $request->user());

        return redirect("/change-requests/{$changeRequest->id}")
            ->with('status', $to === ChangeRequestStatus::APPROVED ? 'CR disetujui' : 'CR ditolak');
    }

    /** FINAL MoMs of the project: the only valid source of a change (MOM_NOT_FINAL). */
    private function finalMoms(Project $project)
    {
        return Mom::with('meeting')
            ->where('status', MomStatus::FINAL)
            ->whereHas('meeting', fn ($q) => $q->where('project_id', $project->projectId))
            ->orderByDesc('meeting_date')
            ->get();
    }
}
