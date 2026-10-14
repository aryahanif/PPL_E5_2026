<?php

namespace App\Http\Controllers\Web;

use App\Contracts\ProjectDirectory;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\ProjectGuard;
use Illuminate\Http\Request;

/** Screens 2 and 3. REFERENCE PAGE: copy this pattern for other pages. */
class ProjectPageController extends Controller
{
    public function index(Request $request, ProjectDirectory $projects)
    {
        return view('projects.index', [
            'projects' => $projects->activeForAccount($request->user()->accountId),
        ]);
    }

    public function meetings(Request $request, ProjectGuard $guard, string $projectId)
    {
        $project = $guard->forMember($projectId, $request->user());

        $meetings = Meeting::query()
            ->with('mom')
            ->where('project_id', $projectId)
            ->orderByDesc('scheduled_at')
            ->get();

        return view('meetings.index', compact('project', 'meetings'));
    }
}
