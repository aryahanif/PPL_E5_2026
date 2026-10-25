<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ProjectDirectory;
use App\Enums\ActionItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListActionItemsRequest;
use App\Http\Requests\UpdateActionItemStatusRequest;
use App\Http\Resources\ActionItemResource;
use App\Models\ActionItem;
use App\Services\ActionItemLister;
use App\Services\ActionItemStatusService;
use App\Services\ProjectGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** API-K05-04 */
class ActionItemController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private ProjectDirectory $directory,
        private ActionItemStatusService $statuses,
        private ActionItemLister $lister,
    ) {}

    /** GET /api/v1/action-items  (operationId: listActionItems) */
    public function index(ListActionItemsRequest $request)
    {
        $account = $request->user();

        $projectIds = $request->validated('projectId')
            ? [$this->projects->forMember($request->validated('projectId'), $account)->projectId]
            : array_map(fn ($p) => $p->projectId, $this->directory->activeForAccount($account->accountId));

        $items = $this->lister->query($account, $projectIds, $request->boolean('mine'))
            ->when($request->boolean('overdue'),
                fn ($q) => $q->whereDate('due_date', '<', Carbon::today(config('e5.display_timezone'))->toDateString())
                    ->where('status', '!=', ActionItemStatus::DONE))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->get();

        return ActionItemResource::collection($items);
    }

    /** PATCH /api/v1/action-items/{actionItemId}/status  (operationId: updateActionItemStatus) */
    public function updateStatus(UpdateActionItemStatusRequest $request, ActionItem $actionItem)
    {
        $actionItem->load('mom.meeting');
        $this->projects->forMember($actionItem->mom->meeting->project_id, $request->user());

        $this->statuses->assertMomFinal($actionItem);

        Gate::authorize('updateStatus', $actionItem);

        $actionItem = $this->statuses->change(
            $actionItem,
            ActionItemStatus::from($request->validated('status')),
            $request->user(),
        );

        return new ActionItemResource($actionItem);
    }
}
