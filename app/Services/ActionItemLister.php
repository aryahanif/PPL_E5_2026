<?php

namespace App\Services;

use App\Data\Account;
use App\Enums\MomStatus;
use App\Models\ActionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Which action items a caller may list (PRD §5 US-4): only items of FINAL MoMs,
 * and a TW (or "mine") only sees items where they are the PIC.
 * Shared by Api\ActionItemController and Web\ActionItemPageController.
 *
 * @param list<string> $projectIds
 */
class ActionItemLister
{
    public function query(Account $account, array $projectIds, bool $mine = false): Builder
    {
        $mine = $mine || Gate::forUser($account)->denies('viewAll', ActionItem::class);

        return ActionItem::query()
            ->with('mom.meeting')
            ->whereHas('mom', fn ($q) => $q->where('status', MomStatus::FINAL)
                ->whereHas('meeting', fn ($m) => $m->whereIn('project_id', $projectIds)))
            ->when($mine, fn ($q) => $q->where('pic_account_id', $account->accountId))
            ->orderBy('due_date');
    }
}
