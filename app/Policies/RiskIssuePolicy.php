<?php

namespace App\Policies;

use App\Data\Account;
use App\Enums\Role;
use App\Models\RiskIssue;
use Illuminate\Auth\Access\Response;

/**
 * Role rules for risks and issues (PRD §6, Hak Akses per Role: Risiko dan isu).
 * Project membership is checked separately by App\Services\ProjectGuard.
 * Used by API-K05-07.
 */
class RiskIssuePolicy
{
    /** Fields a TL may change, and only on items where they are the PIC (Q-09). */
    public const TL_FIELDS = ['status', 'mitigation', 'resolutionNote'];

    /** Any project member can list risks and issues. */
    public function viewAny(Account $account): Response
    {
        return Response::allow();
    }

    /** BL-10: only a PM records risks and issues. */
    public function create(Account $account): Response
    {
        return $account->is(Role::PM)
            ? Response::allow()
            : Response::deny('Hanya PM yang dapat mencatat risiko/isu');
    }

    /**
     * BL-11: a PM changes every field, including the PIC. A TL changes only
     * TL_FIELDS, and only when they are the PIC. A TW changes nothing.
     *
     * @param  list<string>  $fields  camelCase fields sent in the request body
     */
    public function update(Account $account, RiskIssue $riskIssue, array $fields = []): Response
    {
        if ($account->is(Role::PM)) {
            return Response::allow();
        }

        if (! $account->is(Role::TL)) {
            return Response::deny('Hanya PM atau TL yang dapat mengubah risiko/isu');
        }

        if ($riskIssue->pic_account_id !== $account->accountId) {
            return Response::deny('TL hanya dapat mengubah risiko/isu yang PIC-nya dirinya');
        }

        return array_diff($fields, self::TL_FIELDS)
            ? Response::deny('TL hanya dapat mengubah status, mitigasi, dan catatan penyelesaian')
            : Response::allow();
    }
}
