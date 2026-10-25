<?php

namespace App\Policies;

use App\Data\Account;
use App\Enums\Role;
use App\Models\ActionItem;
use Illuminate\Auth\Access\Response;

/**
 * Role rules for action items (PRD §6). Used by API-K05-04.
 * Who may *list* which items (PM/TL all, TW own) is a query filter in the
 * controller, not a policy method.
 */
class ActionItemPolicy
{
    /** PM and TL list every item of their projects; a TW only lists own items (see ActionItemLister). */
    public function viewAll(Account $account): Response
    {
        return $account->is(Role::TW)
            ? Response::deny('TW hanya dapat melihat action item miliknya')
            : Response::allow();
    }

    /** Only the PIC changes the status of an action item, PM and TL included. */
    public function updateStatus(Account $account, ActionItem $actionItem): Response
    {
        return $actionItem->pic_account_id === $account->accountId
            ? Response::allow()
            : Response::deny('Hanya PIC yang dapat mengubah status action item');
    }
}
