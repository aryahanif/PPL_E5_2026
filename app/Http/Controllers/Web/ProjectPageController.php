<?php

namespace App\Http\Controllers\Web;

use App\Contracts\IdentityProvider;
use App\Contracts\ProjectDirectory;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;

/** Screens 2 and 3. REFERENCE PAGE: copy this pattern for other pages. */
class ProjectPageController extends Controller
{
    public function index(Request $request, ProjectDirectory $projects, IdentityProvider $identity)
    {
        $projects = $projects->activeForAccount($request->user()->accountId);
        $ids = array_map(fn ($project) => $project->projectId, $projects);

        // Members per project, so each card can show its team (names from F-1).
        $members = collect($projects)->mapWithKeys(fn ($project) => [
            $project->projectId => collect($project->memberAccountIds)
                ->map(fn ($id) => $identity->find($id))
                ->filter()
                ->values(),
        ]);

        $meetings = Meeting::query()->whereIn('project_id', $ids)->orderBy('scheduled_at')->get()->groupBy('project_id');

        return view('projects.index', compact('projects', 'members', 'meetings'));
    }

    public function meetings(Request $request, ProjectGuard $guard, IdentityProvider $identity, string $projectId)
    {
        $project = $guard->forMember($projectId, $request->user());
        $status = MeetingStatus::tryFrom((string) $request->query('status'));

        $all = Meeting::query()
            ->with(['participants', 'mom'])
            ->where('project_id', $projectId)
            ->orderByDesc('scheduled_at')
            ->get();

        $meetings = $status ? $all->where('status', $status)->values() : $all;
        $counts = $all->countBy(fn ($meeting) => $meeting->status->value);
        $people = collect($identity->all())->keyBy('accountId');

        return view('meetings.index', compact('project', 'meetings', 'counts', 'status', 'people'));
    }
}
