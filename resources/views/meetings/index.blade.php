@extends('layouts.app')

@section('title', 'Rapat')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $tabs = [
            '' => ['Semua', $counts->sum()],
            'SCHEDULED' => ['Terjadwal', $counts['SCHEDULED'] ?? 0],
            'DONE' => ['Selesai', $counts['DONE'] ?? 0],
            'CANCELLED' => ['Dibatalkan', $counts['CANCELLED'] ?? 0],
        ];
    @endphp

    <section class="hero">
        <div>
            <span class="tag"><i></i> ACTIVE · {{ $project->projectId }}</span>
            <h1 style="margin-top:10px">Rapat · {{ $project->name }}</h1>
            <p>Jadwal rapat mingguan proyek dan status MoM-nya.</p>
        </div>
        <div class="hero-side">
            @can('create', App\Models\Meeting::class)
                <form method="GET" action="/projects/{{ $project->projectId }}/meetings/create">
                    <button type="submit" class="primary"><x-icon name="plus" size="16" /> Jadwalkan Rapat</button>
                </form>
            @endcan
        </div>
    </section>

    <div class="kpis">
        <div class="kpi"><span class="kpi-icon tone-accent"><x-icon name="calendar" size="20" /></span><div><strong>{{ $counts->sum() }}</strong><span>Total rapat</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-info"><x-icon name="clock" size="20" /></span><div><strong>{{ $counts['SCHEDULED'] ?? 0 }}</strong><span>Terjadwal</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-success"><x-icon name="check" size="20" /></span><div><strong>{{ $counts['DONE'] ?? 0 }}</strong><span>Selesai</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-danger"><x-icon name="x" size="20" /></span><div><strong>{{ $counts['CANCELLED'] ?? 0 }}</strong><span>Dibatalkan</span></div></div>
    </div>

    <div class="card-head">
        <nav class="tabs">
            @foreach ($tabs as $value => [$label, $total])
                <a href="/projects/{{ $project->projectId }}/meetings{{ $value ? '?status='.$value : '' }}"
                   @class(['active' => ($status?->value ?? '') === $value])>{{ $label }} <span class="count">{{ $total }}</span></a>
            @endforeach
        </nav>
    </div>

    @if ($meetings->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="calendar" size="24" /></span>
            <h2>Belum ada rapat</h2>
            <p class="muted">Belum ada rapat.</p>
        </div>
    @else
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>Jadwal (WIB)</th><th>Judul rapat</th><th>Peserta</th><th>Status rapat</th><th>MoM</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($meetings as $meeting)
                        @php
                            $at = $meeting->scheduled_at->timezone($tz)->locale('id');
                            $names = $meeting->participants->map(fn ($p) => $p->account_id ? ($people[$p->account_id]->displayName ?? $p->account_id) : $p->display_name);
                        @endphp
                        <tr>
                            <td>
                                <div class="date-chip">
                                    <b>{{ $at->format('d') }}<small>{{ $at->translatedFormat('M') }}</small></b>
                                    <div><strong>{{ $at->translatedFormat('l') }}</strong><div class="sub">{{ $at->translatedFormat('d M Y H:i') }}</div></div>
                                </div>
                            </td>
                            <td>
                                <a href="/meetings/{{ $meeting->id }}" style="color:var(--ink)"><strong>{{ $meeting->title }}</strong></a>
                                <div class="sub"><x-icon name="pin" size="13" /> {{ $meeting->location ?? '—' }}</div>
                            </td>
                            <td>
                                <div class="stack-avatars">
                                    @foreach ($names->take(3) as $name)
                                        <x-avatar :name="$name" size="28" />
                                    @endforeach
                                    @if ($names->count() > 3)
                                        <span class="more">+{{ $names->count() - 3 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td><x-status-badge :value="$meeting->status->value" /></td>
                            <td>
                                @if ($meeting->mom)
                                    <x-status-badge :value="$meeting->mom->status->value" />
                                @elseif ($meeting->status === App\Enums\MeetingStatus::DONE)
                                    <span class="muted">Menunggu MoM</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td><a href="/meetings/{{ $meeting->id }}">Detail &rarr;</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
