<?php

namespace Database\Seeders;

use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Enums\MomStatus;
use App\Enums\ParticipantType;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Mom;
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
    private const PROJECT = 'PRJ-2026-014';

    public function run(): void
    {
        $today = Carbon::today(config('e5.display_timezone'));
        $at = fn (int $days, int $hourWib = 9) => $today->copy()->addDays($days)->setTime($hourWib, 0)->utc();

        // 1. Upcoming meeting, no MoM yet
        $this->meeting('11111111-1111-4111-8111-000000000001', 'Weekly Meeting Minggu 4', $at(5), MeetingStatus::SCHEDULED);

        // 2. Finished meeting waiting for the TW to write the MoM
        $this->meeting('11111111-1111-4111-8111-000000000002', 'Weekly Meeting Minggu 3', $at(-2), MeetingStatus::DONE);

        // 3. Finished meeting with a FINAL MoM and three action items
        $m3 = $this->meeting('11111111-1111-4111-8111-000000000003', 'Weekly Meeting Minggu 2', $at(-9), MeetingStatus::DONE, attended: true);

        $mom = Mom::updateOrCreate(['id' => '22222222-2222-4222-8222-000000000003'], [
            'meeting_id' => $m3->id,
            'meeting_date' => $today->copy()->subDays(9)->toDateString(),
            'discussion' => 'Progres modul login 70%, server uji bermasalah.',
            'decision' => 'Server uji dipindah ke lingkungan baru.',
            'change_summary' => null,
            'status' => MomStatus::FINAL,
            'written_by' => 'acc-tw-0091',
            'reviewed_by' => 'acc-tl-0042',
            'finalized_at' => $at(-8),
        ]);

        $items = [
            ['33333333-3333-4333-8333-000000000001', 'Siapkan server uji baru', 'acc-tl-0042', 3, ActionItemStatus::TODO],
            ['33333333-3333-4333-8333-000000000002', 'Perbarui dokumen kebutuhan modul login', 'acc-tw-0091', -1, ActionItemStatus::IN_PROGRESS], // overdue
            ['33333333-3333-4333-8333-000000000003', 'Konfirmasi jadwal demo ke klien', 'acc-pm-0007', -5, ActionItemStatus::DONE],
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
    }

    private function meeting(string $id, string $title, Carbon $scheduledAt, MeetingStatus $status, bool $attended = false): Meeting
    {
        $meeting = Meeting::updateOrCreate(['id' => $id], [
            'project_id' => self::PROJECT,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'location' => 'Ruang Rapat 2',
            'status' => $status,
            'created_by' => 'acc-pm-0007',
        ]);

        $participants = [
            ['acc-pm-0007', null], ['acc-tl-0042', null], ['acc-tw-0091', null], [null, 'Perwakilan Klien'],
        ];

        foreach ($participants as $i => [$accountId, $clientName]) {
            MeetingParticipant::updateOrCreate(['id' => '44444444-4444-4444-8444-'.substr($id, -6).sprintf('%06d', $i + 1)], [
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
