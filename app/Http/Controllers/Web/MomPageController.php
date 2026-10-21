<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Data\Project;
use App\Enums\MomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMomRequest;
use App\Models\Meeting;
use App\Models\Mom;
use App\Services\MomStatusService;
use App\Services\MomWriter;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Screens 5 and 6: MoM editor for the TW, MoM view and review for the TL (UI-K05-07). */
class MomPageController extends Controller
{
    private const MESSAGES = [
        'meetingDate.required' => 'Tanggal rapat wajib diisi',
        'meetingDate.date_format' => 'Format tanggal rapat tidak valid',
        'discussion.required' => 'Pembahasan wajib diisi',
        'decision.required' => 'Keputusan wajib diisi',
        'attendance.*.distinct' => 'Peserta yang sama tidak boleh dicentang dua kali',
        'actionItems.*.description.required' => 'Deskripsi action item wajib diisi',
        'actionItems.*.picAccountId.required' => 'PIC wajib dipilih',
        'actionItems.*.dueDate.required' => 'Tenggat wajib diisi',
        'actionItems.*.dueDate.date_format' => 'Format tenggat tidak valid',
        'actionItems.*.dueDate.after_or_equal' => 'Tenggat tidak boleh sebelum tanggal rapat',
    ];

    public function __construct(
        private ProjectGuard $projects,
        private IdentityProvider $identity,
        private MomWriter $writer,
        private MomStatusService $statuses,
    ) {}

    public function create(Request $request, Meeting $meeting)
    {
        $project = $this->projects->forMember($meeting->project_id, $request->user());
        Gate::authorize('create', Mom::class);

        if ($meeting->mom) {
            return redirect("/moms/{$meeting->mom->id}");
        }

        $this->writer->assertCanCreate($meeting);

        return view('moms.edit', $this->formData($project, $meeting->load('participants'), null));
    }

    public function store(Request $request, Meeting $meeting)
    {
        $account = $request->user();
        $project = $this->projects->forMember($meeting->project_id, $account);
        Gate::authorize('create', Mom::class);

        $mom = $this->writer->create($meeting, $project, $account, $this->validated($request));

        return $this->finish($request, $mom);
    }

    public function edit(Request $request, Mom $mom)
    {
        $project = $this->projects->forMember($mom->meeting->project_id, $request->user());
        Gate::authorize('update', $mom);

        if (! $this->writer->isEditable($mom)) {
            return redirect("/moms/{$mom->id}");
        }

        return view('moms.edit', $this->formData($project, $mom->meeting->load('participants'), $mom->load('actionItems')));
    }

    public function update(Request $request, Mom $mom)
    {
        $project = $this->projects->forMember($mom->meeting->project_id, $request->user());
        Gate::authorize('update', $mom);

        $this->writer->update($mom, $project, $this->validated($request));

        return $this->finish($request, $mom);
    }

    public function show(Request $request, Mom $mom)
    {
        $project = $this->projects->forMember($mom->meeting->project_id, $request->user());
        Gate::authorize('view', $mom);

        $mom->load(['meeting.participants', 'actionItems']);

        return view('moms.show', [
            'project' => $project,
            'mom' => $mom,
            'meeting' => $mom->meeting,
            'editable' => $this->writer->isEditable($mom),
            'people' => collect($this->identity->all())->keyBy('accountId'),
        ]);
    }

    public function updateStatus(Request $request, Mom $mom)
    {
        $this->projects->forMember($mom->meeting->project_id, $request->user());
        Gate::authorize('review', $mom);

        $data = $request->validate([
            'status' => ['required', 'in:FINAL,REVISION'],
            'reviewNote' => ['required_if:status,REVISION', 'nullable', 'string'],
        ], [
            'status.*' => 'Keputusan review tidak valid',
            'reviewNote.required_if' => 'Catatan revisi wajib diisi',
        ]);

        $this->statuses->change($mom, MomStatus::from($data['status']), $request->user(), $data['reviewNote'] ?? null);

        return redirect("/moms/{$mom->id}")->with('status', $data['status'] === 'FINAL'
            ? 'MoM berhasil difinalkan'
            : 'MoM dikembalikan ke TW untuk direvisi');
    }

    /** Same rules as SaveMomRequest (API), with Indonesian messages. */
    private function validated(Request $request): array
    {
        // Unticked boxes and removed rows send nothing, but the rules want empty arrays.
        $request->merge([
            'attendance' => $request->input('attendance', []),
            'actionItems' => $request->input('actionItems', []),
        ]);

        return $request->validate((new SaveMomRequest)->rules(), self::MESSAGES);
    }

    /** "Ajukan Review" saves, then moves DRAFT/REVISION → IN_REVIEW; "Simpan Draft" only saves. */
    private function finish(Request $request, Mom $mom)
    {
        if ($request->input('action') === 'submit') {
            Gate::authorize('submit', $mom);
            $this->statuses->change($mom, MomStatus::IN_REVIEW, $request->user());

            return redirect("/moms/{$mom->id}")->with('status', 'MoM diajukan untuk review');
        }

        return redirect("/moms/{$mom->id}")->with('status', 'MoM tersimpan sebagai draft');
    }

    private function formData(Project $project, Meeting $meeting, ?Mom $mom): array
    {
        $members = collect($project->memberAccountIds)
            ->map(fn ($id) => $this->identity->find($id))
            ->filter()
            ->values();

        $meetingDate = $mom?->meeting_date->toDateString()
            ?? $meeting->scheduled_at->timezone(config('e5.display_timezone'))->toDateString();

        $items = $mom?->actionItems->map(fn ($item) => [
            'actionItemId' => $item->id,
            'description' => $item->description,
            'picAccountId' => $item->pic_account_id,
            'dueDate' => $item->due_date->toDateString(),
        ])->all() ?? [];

        return [
            'project' => $project,
            'meeting' => $meeting,
            'mom' => $mom,
            'members' => $members,
            'people' => collect($this->identity->all())->keyBy('accountId'),
            'meetingDate' => $meetingDate,
            'items' => $items,
        ];
    }
}
