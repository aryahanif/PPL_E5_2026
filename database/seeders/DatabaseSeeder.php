<?php

namespace Database\Seeders;

use App\Enums\ActionItemStatus;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use App\Enums\ImpactLevel;
use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Enums\ParticipantType;
use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use App\Models\ActionItem;
use App\Models\ChangeRequest;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Mom;
use App\Models\RiskIssue;
use App\Models\StatusHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Fictional data (PRD §10). IDs are fixed so every run gives the same records.
 * Dates are relative to today (WIB) so "upcoming" and "overdue" stay true.
 *
 *   php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    private const PROJECT = '019a0000-d101-7000-8000-000000000014';

    public function run(): void
    {
        $today = Carbon::today(config('e5.display_timezone'));
        $at = fn (int $days, int $hourWib = 9) => $today->copy()->addDays($days)->setTime($hourWib, 0)->utc();

        // 1. Upcoming meeting, no MoM yet
        $this->meeting('019a0000-1111-7111-8111-000000000001', 'Weekly Meeting Minggu 4', $at(5), MeetingStatus::SCHEDULED);

        // 2. Finished meeting waiting for the TW to write the MoM
        $this->meeting('019a0000-1111-7111-8111-000000000002', 'Weekly Meeting Minggu 3', $at(-2), MeetingStatus::DONE);

        // 3. Finished meeting with a FINAL MoM and three action items
        $m3 = $this->meeting('019a0000-1111-7111-8111-000000000003', 'Weekly Meeting Minggu 2', $at(-9), MeetingStatus::DONE, attended: true);

        $mom = Mom::updateOrCreate(['id' => '019a0000-2222-7222-8222-000000000003'], [
            'meeting_id' => $m3->id,
            'meeting_date' => $today->copy()->subDays(9)->toDateString(),
            'discussion' => 'Progres modul login 70%, server uji bermasalah.',
            'decision' => 'Server uji dipindah ke lingkungan baru.',
            'change_summary' => null,
            'status' => MomStatus::FINAL,
            'written_by' => '019a0000-f101-7000-8000-000000000091',
            'reviewed_by' => '019a0000-f101-7000-8000-000000000042',
            'finalized_at' => $at(-8),
        ]);

        $changeRequests = [
            ['019a0000-6666-7666-8666-000000000001', 'Penyesuaian label tombol login', ChangeType::TASK, false, ChangeRequestStatus::RECORDED],
            ['019a0000-6666-7666-8666-000000000002', 'Penambahan validasi autentikasi', ChangeType::SCOPE, true, ChangeRequestStatus::PENDING_ANALYSIS],
        ];

        foreach ($changeRequests as [$id, $description, $type, $formal, $status]) {
            $changeRequest = ChangeRequest::updateOrCreate(['id' => $id], [
                'project_id' => self::PROJECT,
                'mom_id' => $mom->id,
                'change_description' => $description,
                'source' => ChangeSource::USER,
                'change_type' => $type,
                'is_formal_cr' => $formal,
                'status' => $status,
                'created_by' => '019a0000-f101-7000-8000-000000000007',
            ]);

            StatusHistory::firstOrCreate([
                'entity_type' => ChangeRequest::HISTORY_TYPE,
                'entity_id' => $changeRequest->id,
                'to_status' => $status->value,
            ], [
                'from_status' => null,
                'changed_by' => '019a0000-f101-7000-8000-000000000007',
                'changed_at' => $at(-8),
                'note' => null,
            ]);
        }

        $items = [
            ['019a0000-3333-7333-8333-000000000001', 'Siapkan server uji baru', '019a0000-f101-7000-8000-000000000042', 3, ActionItemStatus::TODO],
            ['019a0000-3333-7333-8333-000000000002', 'Perbarui dokumen kebutuhan modul login', '019a0000-f101-7000-8000-000000000091', -1, ActionItemStatus::IN_PROGRESS], // overdue
            ['019a0000-3333-7333-8333-000000000003', 'Konfirmasi jadwal demo ke klien', '019a0000-f101-7000-8000-000000000007', -5, ActionItemStatus::DONE],
        ];

        foreach ($items as [$id, $description, $pic, $dueInDays, $status]) {
            ActionItem::updateOrCreate(['id' => $id], [
                'mom_id' => $mom->id,
                'description' => $description,
                'pic_account_id' => $pic,
                'due_date' => $today->copy()->addDays($dueInDays)->toDateString(),
                'status' => $status,
                'completed_at' => $status === ActionItemStatus::DONE ? $at(-6) : null,
            ]);
        }

        // 4. Risk register: one OPEN risk (PIC PM) and one IN_PROGRESS issue from the MoM above (PIC TL)
        $risks = [
            ['019a0000-5555-7555-8555-000000000001', RiskIssueType::RISK, 'Klien terlambat memberi data uji', 'Data uji dari klien belum diterima, pengujian modul laporan bisa mundur.', ImpactLevel::MEDIUM, null, '019a0000-f101-7000-8000-000000000007', null, RiskIssueStatus::OPEN, -7],
            ['019a0000-5555-7555-8555-000000000002', RiskIssueType::ISSUE, 'Server uji tidak dapat diakses', 'Developer tidak bisa menjalankan testing sejak Senin.', ImpactLevel::HIGH, 'Pindah ke server cadangan', '019a0000-f101-7000-8000-000000000042', $mom->id, RiskIssueStatus::IN_PROGRESS, -3],
        ];

        foreach ($risks as [$id, $type, $title, $description, $impact, $mitigation, $pic, $momId, $status, $createdInDays]) {
            $item = RiskIssue::updateOrCreate(['id' => $id], [
                'project_id' => self::PROJECT,
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'impact_level' => $impact,
                'mitigation' => $mitigation,
                'pic_account_id' => $pic,
                'source_mom_id' => $momId,
                'status' => $status,
                'reported_by' => '019a0000-f101-7000-8000-000000000007',
            ]);

            $item->forceFill(['created_at' => $at($createdInDays)])->save();
        }
    }

    private function meeting(string $id, string $title, Carbon $scheduledAt, MeetingStatus $status, bool $attended = false): Meeting
    {
        $meeting = Meeting::updateOrCreate(['id' => $id], [
            'project_id' => self::PROJECT,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'location' => 'Ruang Rapat 2',
            'status' => $status,
            'created_by' => '019a0000-f101-7000-8000-000000000007',
        ]);

        $participants = [
            ['019a0000-f101-7000-8000-000000000007', null], ['019a0000-f101-7000-8000-000000000042', null], ['019a0000-f101-7000-8000-000000000091', null], [null, 'Perwakilan Klien'],
        ];

        foreach ($participants as $i => [$accountId, $clientName]) {
            MeetingParticipant::updateOrCreate(['id' => '019a0000-4444-7444-8444-'.substr($id, -6).sprintf('%06d', $i + 1)], [
                'meeting_id' => $meeting->id,
                'participant_type' => $accountId ? ParticipantType::INTERNAL : ParticipantType::CLIENT,
                'account_id' => $accountId,
                'display_name' => $clientName,
                'attended' => $attended,
            ]);
        }

        return $meeting;
    }
}
