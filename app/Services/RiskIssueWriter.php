<?php

namespace App\Services;

use App\Contracts\IdentityProvider;
use App\Data\Account;
use App\Data\Project;
use App\Enums\RiskIssueStatus;
use App\Models\Mom;
use App\Models\RiskIssue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates risks/issues (BL-10, BL-11). Shared by the API (API-K05-07) and
 * the pages (UI-K05-06). Input uses the camelCase field names of openapi.yaml.
 */
class RiskIssueWriter
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
        private IdentityProvider $identity,
        private StatusHistoryRecorder $history,
        private RiskIssueStatusService $statuses,
    ) {}

    public function create(array $data, Project $project, Account $by): RiskIssue
    {
        $this->checkReferences($data, $project);

        return DB::transaction(function () use ($data, $project, $by) {
            $item = RiskIssue::create([
                'project_id' => $project->projectId,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'],
                'impact_level' => $data['impactLevel'] ?? null,
                'mitigation' => $data['mitigation'] ?? null,
                'pic_account_id' => $data['picAccountId'],
                'source_mom_id' => $data['sourceMomId'] ?? null,
                'status' => RiskIssueStatus::OPEN,
                'reported_by' => $by->accountId,
            ]);

            $this->history->record($item, null, RiskIssueStatus::OPEN, $by);

            return $item->refresh();
        });
    }

    /** Only the fields present in $data are changed. */
    public function update(RiskIssue $item, array $data, Project $project, Account $by): RiskIssue
    {
        $this->checkReferences($data, $project);

        DB::transaction(function () use ($item, $data, $by) {
            $columns = [];

            foreach (self::COLUMNS as $field => $column) {
                if (array_key_exists($field, $data)) {
                    $columns[$column] = $data[$field];
                }
            }

            $item->update($columns);

            if (isset($data['picAccountId'])) {
                $this->statuses->changePic($item, $data['picAccountId'], $by);
            }

            if (isset($data['status'])) {
                $this->statuses->change($item, RiskIssueStatus::from($data['status']), $by, $data['resolutionNote'] ?? null);
            }
        });

        return $item->refresh();
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
