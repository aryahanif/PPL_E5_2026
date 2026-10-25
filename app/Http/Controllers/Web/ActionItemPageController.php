<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Enums\ActionItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Services\ActionItemLister;
use App\Services\ActionItemStatusService;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Screen 7: action items of a project, "Milik saya" and "Semua di proyek" tabs (UI-K05-04). */
class ActionItemPageController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private ActionItemLister $lister,
        private ActionItemStatusService $statuses,
    ) {}

    public function index(Request $request, string $projectId)
    {
        $account = $request->user();
        $project = $this->projects->forMember($projectId, $account);

        $canSeeAll = Gate::allows('viewAll', ActionItem::class);
        $tab = $canSeeAll && $request->query('tab') === 'all' ? 'all' : 'mine';

        $items = $this->lister->query($account, [$projectId], mine: $tab === 'mine')->get();

        return view('action-items.index', [
            'project' => $project,
            'items' => $items,
            'tab' => $tab,
            'canSeeAll' => $canSeeAll,
            'counts' => $items->countBy(fn ($item) => $item->status->value),
            'overdue' => $items->filter->isOverdue()->count(),
            'next' => $items->mapWithKeys(fn ($item) => [$item->id => $this->statuses->next($item->status)?->value]),
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
}
