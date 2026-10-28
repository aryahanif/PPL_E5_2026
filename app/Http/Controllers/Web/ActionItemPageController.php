<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Contracts\ProjectDirectory;
use App\Data\Project;
use App\Enums\ActionItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\StatusHistory;
use App\Services\ActionItemLister;
use App\Services\ActionItemStatusService;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Screen 7: action items, "Milik saya" and "Semua di proyek" tabs (UI-K05-04).
 * Per project (/projects/{id}/action-items) or across the caller's projects (/action-items),
 * plus the history of one item.
 */
class ActionItemPageController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private ProjectDirectory $directory,
        private IdentityProvider $identity,
        private ActionItemLister $lister,
        private ActionItemStatusService $statuses,
    ) {}

    public function index(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());

        return $this->list($request, [$project], $project);
    }

    /** All action items across the caller's active projects, filterable by project and status. */
    public function all(Request $request)
    {
        $account = $request->user();
        $projects = $this->directory->activeForAccount($account->accountId);

        $filter = $request->query('project')
            ? [$this->projects->forMember((string) $request->query('project'), $account)]
            : $projects;

        return $this->list($request, $filter, null, $projects);
    }

    /** One item with its status history; the "Riwayat" link of the list. */
    public function show(Request $request, ActionItem $actionItem)
    {
        $actionItem->load('mom.meeting');
        $project = $this->projects->forMember($actionItem->mom->meeting->project_id, $request->user());

        $this->statuses->assertMomFinal($actionItem);
        Gate::authorize('view', $actionItem);

        $history = StatusHistory::query()
            ->where('entity_type', ActionItem::HISTORY_TYPE)
            ->where('entity_id', $actionItem->id)
            ->orderByDesc('changed_at')
            ->get();

        return view('action-items.show', [
            'project' => $project,
            'item' => $actionItem,
            'history' => $history,
            'next' => $this->statuses->next($actionItem->status)?->value,
            'people' => collect($this->identity->all())->keyBy('accountId'),
        ]);
    }

    public function updateStatus(Request $request, ActionItem $actionItem)
    {
        $actionItem->load('mom.meeting');
        $projectId = $actionItem->mom->meeting->project_id;
        $this->projects->forMember($projectId, $request->user());

        $this->statuses->assertMomFinal($actionItem);
        Gate::authorize('updateStatus', $actionItem);

        $data = $request->validate(
            ['status' => ['required', 'in:IN_PROGRESS,DONE']],
            ['status.*' => 'Status tidak valid, pilih IN_PROGRESS atau DONE'],
        );

        $this->statuses->change($actionItem, ActionItemStatus::from($data['status']), $request->user());

        return redirect()->back(fallback: "/projects/{$projectId}/action-items")
            ->with('status', "Status action item diubah menjadi {$data['status']}");
    }

    /**
     * KPIs count the active tab (and project filter); the status filter only narrows the table.
     *
     * @param  list<Project>  $scope  projects whose items are listed
     * @param  list<Project>  $choices  projects offered in the project filter (cross-project page only)
     */
    private function list(Request $request, array $scope, ?Project $project, array $choices = [])
    {
        $account = $request->user();

        $canSeeAll = Gate::allows('viewAll', ActionItem::class);
        $tab = $canSeeAll && $request->query('tab') === 'all' ? 'all' : 'mine';
        $status = ActionItemStatus::tryFrom((string) $request->query('status'));

        $ids = array_map(fn (Project $p) => $p->projectId, $scope);
        $tabItems = $this->lister->query($account, $ids, mine: $tab === 'mine')->get();
        $items = $status ? $tabItems->where('status', $status)->values() : $tabItems;

        return view('action-items.index', [
            'project' => $project,
            'items' => $items,
            'tab' => $tab,
            'status' => $status,
            'canSeeAll' => $canSeeAll,
            'counts' => $tabItems->countBy(fn ($item) => $item->status->value),
            'total' => $tabItems->count(),
            'overdue' => $tabItems->filter->isOverdue()->count(),
            'next' => $items->mapWithKeys(fn ($item) => [$item->id => $this->statuses->next($item->status)?->value]),
            'people' => collect($this->identity->all())->keyBy('accountId'),
            'projectNames' => collect($choices ?: $scope)->mapWithKeys(fn (Project $p) => [$p->projectId => $p->name]),
            'projectFilter' => $project ? null : $request->query('project'),
            'choices' => $choices,
        ]);
    }
}
