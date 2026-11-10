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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Manual-testing data where every record sits at the start of its flow, so each role can
 * walk it forward again. Fictional data (PRD §10), fixed IDs, dates relative to today (WIB).
 * The automated tests keep using DatabaseSeeder.
 *
 *   php artisan migrate:fresh --seed --seeder=FreshStartSeeder
 */
class FreshStartSeeder extends Seeder
{
    private const PROJECT = 'PRJ-2026-014';

    private const PM = 'acc-pm-0007';

    private const TL = 'acc-tl-0042';

    private const TW = 'acc-tw-0091';

    public function run(): void
    {
        $today = Carbon::today(config('e5.display_timezone'));
        $at = fn (int $days, int $hourWib = 9) => $today->copy()->addDays($days)->setTime($hourWib, 0)->utc();

        // 1. PM: upcoming meetings to mark DONE or CANCELLED
        $this->meeting('a1111111-1111-4111-8111-000000000001', self::PROJECT, 'Weekly Meeting Minggu 3', $at(2), MeetingStatus::SCHEDULED, $at(-1));
        $this->meeting('a1111111-1111-4111-8111-000000000002', self::PROJECT, 'Demo Prototipe ke Klien', $at(6, 13), MeetingStatus::SCHEDULED, $at(-1));
        $this->meeting('a1111111-1111-4111-8111-000000000005', 'PRJ-2026-016', 'Weekly Meeting Minggu 1', $at(3), MeetingStatus::SCHEDULED, $at(-1));

        // 2. TW: finished meeting with no MoM yet
        $this->meeting('a1111111-1111-4111-8111-000000000003', self::PROJECT, 'Weekly Meeting Minggu 2', $at(-1), MeetingStatus::DONE, $at(-6));

        // 3. Kickoff with a FINAL MoM: source for action items, CRs and risks
        $kickoff = $this->meeting('a1111111-1111-4111-8111-000000000004', self::PROJECT, 'Kickoff Proyek', $at(-7), MeetingStatus::DONE, $at(-12), attended: true);

        $mom = Mom::updateOrCreate(['id' => 'a2222222-2222-4222-8222-000000000004'], [
            'meeting_id' => $kickoff->id,
            'meeting_date' => $today->copy()->subDays(7)->toDateString(),
            'discussion' => 'Penyampaian ruang lingkup ERP tahap 1, pembagian peran tim, dan jadwal rapat mingguan.',
            'decision' => 'Tahap 1 fokus pada modul autentikasi dan laporan. Rapat mingguan setiap Senin pukul 09.00 WIB.',
            'change_summary' => 'Klien meminta modul laporan dapat diekspor ke Excel.',
            'status' => MomStatus::FINAL,
            'written_by' => self::TW,
            'reviewed_by' => self::TL,
            'review_note' => null,
            'finalized_at' => $at(-6, 15),
        ]);
        $this->history($mom, null, 'DRAFT', self::TW, $at(-7, 14));
        $this->history($mom, 'DRAFT', 'IN_REVIEW', self::TW, $at(-7, 16));
        $this->history($mom, 'IN_REVIEW', 'FINAL', self::TL, $at(-6, 15));

        // 4. Every PIC has one TODO action item due in the future
        $items = [
            ['a3333333-3333-4333-8333-000000000001', 'Kirim jadwal rapat mingguan ke seluruh peserta', self::PM, 5],
            ['a3333333-3333-4333-8333-000000000002', 'Siapkan lingkungan server uji', self::TL, 7],
            ['a3333333-3333-4333-8333-000000000003', 'Susun dokumen kebutuhan modul autentikasi', self::TW, 10],
        ];

        foreach ($items as [$id, $description, $pic, $dueInDays]) {
            ActionItem::updateOrCreate(['id' => $id], [
                'mom_id' => $mom->id,
                'description' => $description,
                'pic_account_id' => $pic,
                'due_date' => $today->copy()->addDays($dueInDays)->toDateString(),
                'status' => ActionItemStatus::TODO,
                'completed_at' => null,
            ]);
        }

        // 5. One formal CR waiting for the TL's analysis
        $cr = ChangeRequest::updateOrCreate(['id' => 'a6666666-6666-4666-8666-000000000001'], [
            'project_id' => self::PROJECT,
            'mom_id' => $mom->id,
            'change_description' => 'Tambah fitur ekspor laporan ke Excel',
            'source' => ChangeSource::USER,
            'change_type' => ChangeType::SCOPE,
            'is_formal_cr' => true,
            'baseline_item_id' => null,
            'impact' => null,
            'analyzed_by' => null,
            'status' => ChangeRequestStatus::PENDING_ANALYSIS,
            'decision_note' => null,
            'decided_by' => null,
            'decided_at' => null,
            'created_by' => self::PM,
        ]);
        $cr->forceFill(['created_at' => $at(-6, 16), 'updated_at' => $at(-6, 16)])->save();
        $cr->affectedTasks()->delete();
        $this->history($cr, null, 'PENDING_ANALYSIS', self::PM, $at(-6, 16));

        // 6. Open risk (PIC TL, so the TL can update it) and open issue (PIC PM)
        $risks = [
            ['a5555555-5555-4555-8555-000000000001', RiskIssueType::RISK, 'Data uji dari klien bisa terlambat', 'Klien belum mengirim contoh data, pengujian modul laporan bisa mundur.', ImpactLevel::MEDIUM, self::TL],
            ['a5555555-5555-4555-8555-000000000002', RiskIssueType::ISSUE, 'Akses VPN klien belum diberikan', 'Tim belum bisa mengakses server klien untuk instalasi awal.', ImpactLevel::HIGH, self::PM],
        ];

        foreach ($risks as [$id, $type, $title, $description, $impact, $pic]) {
            $item = RiskIssue::updateOrCreate(['id' => $id], [
                'project_id' => self::PROJECT,
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'impact_level' => $impact,
                'mitigation' => null,
                'pic_account_id' => $pic,
                'source_mom_id' => $mom->id,
                'status' => RiskIssueStatus::OPEN,
                'resolution_note' => null,
                'closed_at' => null,
                'reported_by' => self::PM,
            ]);
            $item->forceFill(['created_at' => $at(-5), 'updated_at' => $at(-5)])->save();
            $this->history($item, null, 'OPEN', self::PM, $at(-5));
        }
    }

    private function meeting(string $id, string $projectId, string $title, Carbon $scheduledAt, MeetingStatus $status, Carbon $createdAt, bool $attended = false): Meeting
    {
        $meeting = Meeting::updateOrCreate(['id' => $id], [
            'project_id' => $projectId,
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'location' => 'Ruang Rapat 2',
            'status' => $status,
            'created_by' => self::PM,
        ]);
        $meeting->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $participants = [[self::PM, null], [self::TL, null], [self::TW, null], [null, 'Perwakilan Klien']];

        foreach ($participants as $i => [$accountId, $clientName]) {
            MeetingParticipant::updateOrCreate(['id' => 'a4444444-4444-4444-8444-'.substr($id, -6).sprintf('%06d', $i + 1)], [
                'meeting_id' => $meeting->id,
                'participant_type' => $accountId ? ParticipantType::INTERNAL : ParticipantType::CLIENT,
                'account_id' => $accountId,
                'display_name' => $clientName,
                'attended' => $attended,
            ]);
        }

        $this->history($meeting, null, 'SCHEDULED', self::PM, $createdAt);

        if ($status !== MeetingStatus::SCHEDULED) {
            $this->history($meeting, 'SCHEDULED', $status->value, self::PM, $scheduledAt->copy()->addHours(2));
        }

        return $meeting;
    }

    private function history(Model $entity, ?string $from, string $to, string $by, Carbon $at): void
    {
        StatusHistory::firstOrCreate([
            'entity_type' => $entity::HISTORY_TYPE,
            'entity_id' => $entity->getKey(),
            'from_status' => $from,
            'to_status' => $to,
        ], ['changed_by' => $by, 'changed_at' => $at, 'note' => null]);
    }
}
