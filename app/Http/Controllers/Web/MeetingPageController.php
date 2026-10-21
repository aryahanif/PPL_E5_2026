<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Data\Account;
use App\Data\Project;
use App\Enums\MeetingStatus;
use App\Enums\ParticipantType;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\StatusHistory;
use App\Services\MeetingStatusService;
use App\Services\ProjectGuard;
use App\Services\StatusHistoryRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Screens 3 and 4 for the PM: schedule a meeting, meeting detail, mark done/cancelled (UI-K05-01). */
class MeetingPageController extends Controller
{
    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private StatusHistoryRecorder $history,
        private MeetingStatusService $statuses,
    ) {}

    public function create(Request $request, string $projectId)
    {
        $project = $this->projects->forMember($projectId, $request->user());
        Gate::authorize('create', Meeting::class);

        return view('meetings.create', [
            'project' => $project,
            'members' => $this->members($project),
        ]);
    }

    public function store(Request $request, string $projectId)
    {
        $account = $request->user();
        $project = $this->projects->forActiveMember($projectId, $account);
        Gate::authorize('create', Meeting::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:200'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*' => ['string', 'distinct', Rule::in($project->memberAccountIds)],
            'clientNames' => ['nullable', 'string', 'max:500'],
        ], [
            'title.required' => 'Judul rapat wajib diisi',
            'title.max' => 'Judul rapat maksimal 150 karakter',
            'date.required' => 'Tanggal rapat wajib diisi',
            'date.date_format' => 'Format tanggal rapat tidak valid',
            'time.required' => 'Jam rapat wajib diisi',
            'time.date_format' => 'Format jam rapat tidak valid',
            'location.max' => 'Lokasi maksimal 200 karakter',
            'participants.required' => 'Minimal satu peserta wajib dipilih',
            'participants.*.in' => 'Peserta harus anggota proyek',
            'participants.*.distinct' => 'Peserta yang sama tidak boleh dipilih dua kali',
        ]);

        // Client names are free text (no F-1 account), separated by commas.
        $clients = collect(explode(',', $data['clientNames'] ?? ''))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        $meeting = DB::transaction(function () use ($data, $clients, $account, $projectId) {
            $meeting = Meeting::create([
                'project_id' => $projectId,
                'title' => $data['title'],
                'scheduled_at' => Carbon::parse("{$data['date']} {$data['time']}", config('e5.display_timezone'))->utc(),
                'location' => $data['location'] ?? null,
                'status' => MeetingStatus::SCHEDULED,
                'created_by' => $account->accountId,
            ]);

            foreach ($data['participants'] as $accountId) {
                $meeting->participants()->create([
                    'participant_type' => ParticipantType::INTERNAL,
                    'account_id' => $accountId,
                ]);
            }

            foreach ($clients as $name) {
                $meeting->participants()->create([
                    'participant_type' => ParticipantType::CLIENT,
                    'display_name' => mb_substr($name, 0, 150),
                ]);
            }

            $this->history->record($meeting, null, MeetingStatus::SCHEDULED, $account);

            return $meeting;
        });

        return redirect("/meetings/{$meeting->id}")->with('status', 'Rapat berhasil dijadwalkan');
    }

    public function show(Request $request, Meeting $meeting)
    {
        $project = $this->projects->forMember($meeting->project_id, $request->user());
        Gate::authorize('view', $meeting);

        $meeting->load(['participants', 'mom']);

        $history = StatusHistory::query()
            ->where('entity_type', Meeting::HISTORY_TYPE)
            ->where('entity_id', $meeting->id)
            ->orderByDesc('changed_at')
            ->get();

        return view('meetings.show', [
            'project' => $project,
            'meeting' => $meeting,
            'history' => $history,
            'people' => collect($this->identity->all())->keyBy('accountId'),
        ]);
    }

    public function updateStatus(Request $request, Meeting $meeting)
    {
        $this->projects->forMember($meeting->project_id, $request->user());
        Gate::authorize('updateStatus', $meeting);

        $data = $request->validate(['status' => ['required', 'in:DONE,CANCELLED']]);

        $this->statuses->change($meeting, MeetingStatus::from($data['status']), $request->user());

        return redirect("/meetings/{$meeting->id}")->with('status', "Status rapat diubah menjadi {$data['status']}");
    }

    /** @return list<Account> project members known to F-1 */
    private function members(Project $project): array
    {
        return collect($project->memberAccountIds)
            ->map(fn ($id) => $this->identity->find($id))
            ->filter()
            ->values()
            ->all();
    }
}
