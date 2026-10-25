@extends('layouts.app')

@section('title', 'Proyek')

@section('content')
    <div class="page-head">
        <div>
            <h1>Proyek</h1>
            <p class="muted">Pantau proyek aktif tempat Anda menjadi anggota.</p>
        </div>
    </div>

    @if (empty($projects))
        <div class="card empty">
            <span class="ring"><x-icon name="folder" size="24" /></span>
            <h2>Tidak ada proyek aktif</h2>
            <p class="muted">Tidak ada proyek aktif untuk akun ini.</p>
        </div>
    @else
        <div class="grid">
            @foreach ($projects as $item)
                @php
                    $team = $members[$item->projectId];
                    $list = $meetings[$item->projectId] ?? collect();
                    $next = $list->first(fn ($m) => $m->status === App\Enums\MeetingStatus::SCHEDULED && $m->scheduled_at->isFuture());
                    $pm = $team->firstWhere('role', App\Enums\Role::PM);
                @endphp
                <div class="card">
                    <div class="card-head">
                        <span class="kpi-icon tone-accent"><x-icon name="folder" size="20" /></span>
                        <x-status-badge value="ACTIVE" />
                    </div>
                    <span class="muted mono">{{ $item->projectId }}</span>
                    <h2 style="font-size:17px; margin:2px 0 14px">{{ $item->name }}</h2>

                    <div class="card-head" style="margin-bottom:14px">
                        <div class="stack-avatars">
                            @foreach ($team as $person)
                                <x-avatar :name="$person->displayName" size="30" />
                            @endforeach
                        </div>
                        <span class="muted" style="font-size:12px">PM: <strong style="color:var(--ink)">{{ $pm?->displayName ?? '—' }}</strong></span>
                    </div>

                    <div class="sub" style="margin-bottom:6px"><x-icon name="calendar" size="14" />
                        {{ $list->count() }} rapat · {{ $list->where('status', App\Enums\MeetingStatus::DONE)->count() }} selesai
                    </div>
                    <div class="sub"><x-icon name="clock" size="14" />
                        Rapat berikutnya:
                        <strong style="color:var(--ink)">{{ $next ? $next->scheduled_at->timezone(config('e5.display_timezone'))->locale('id')->translatedFormat('d M Y H:i').' WIB' : '—' }}</strong>
                    </div>

                    @if ($reading = $indicators[$item->projectId] ?? null)
                        <div class="sub" style="margin-top:6px"><x-icon name="layers" size="14" />
                            Indikator:
                            <x-status-badge :value="$reading->indicator?->value ?? '—'" />
                            <span class="muted" style="font-size:12px">
                                @if ($reading->indicator)
                                    progres {{ rtrim(rtrim(number_format($reading->actualProgress, 1), '0'), '.') }}% dari target {{ rtrim(rtrim(number_format($reading->targetProgress, 1), '0'), '.') }}%
                                @else
                                    belum ada target atau tugas
                                @endif
                            </span>
                        </div>
                    @endif

                    <div class="card-foot" style="justify-content:space-between">
                        <span class="muted">{{ $team->count() }} anggota</span>
                        <a href="/projects/{{ $item->projectId }}/meetings">Buka rapat &rarr;</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
