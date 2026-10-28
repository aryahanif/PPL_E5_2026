@extends('layouts.app')

@section('title', 'Action item')

@section('content')
    @php
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $base = $project ? '/projects/'.$project->projectId.'/action-items' : '/action-items';
        $url = fn (array $params) => $base.(($query = http_build_query(array_filter($params))) ? '?'.$query : '');
        $current = ['tab' => $tab === 'all' ? 'all' : null, 'project' => $projectFilter, 'status' => $status?->value];
    @endphp

    <section class="hero">
        <div>
            @if ($project)
                <span class="tag"><i></i> ACTIVE · {{ $project->projectId }}</span>
                <h1 style="margin-top:10px">Action item · {{ $project->name }}</h1>
            @else
                <span class="tag"><i></i> {{ count($choices) }} proyek aktif</span>
                <h1 style="margin-top:10px">Action item · Semua proyek</h1>
            @endif
            <p>Tindak lanjut dari MoM berstatus FINAL.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert danger">
            <x-icon name="alert" />
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="kpis">
        <div class="kpi"><span class="kpi-icon tone-accent"><x-icon name="check-square" size="20" /></span><div><strong>{{ $total }}</strong><span>Total</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-warning"><x-icon name="clock" size="20" /></span><div><strong>{{ $counts['TODO'] ?? 0 }}</strong><span>TODO</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-info"><x-icon name="arrow-right" size="20" /></span><div><strong>{{ $counts['IN_PROGRESS'] ?? 0 }}</strong><span>IN_PROGRESS</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-success"><x-icon name="check" size="20" /></span><div><strong>{{ $counts['DONE'] ?? 0 }}</strong><span>DONE</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-danger"><x-icon name="alert" size="20" /></span><div><strong>{{ $overdue }}</strong><span>Overdue</span></div></div>
    </div>

    <div class="card-head" style="flex-wrap:wrap">
        <nav class="tabs">
            <a href="{{ $url(['tab' => null] + $current) }}" @class(['active' => $tab === 'mine'])>Milik saya</a>
            @if ($canSeeAll)
                <a href="{{ $url(['tab' => 'all'] + $current) }}" @class(['active' => $tab === 'all'])>Semua di proyek</a>
            @endif
        </nav>

        <form method="GET" action="{{ $base }}" class="actions" style="flex-wrap:nowrap">
            @if ($tab === 'all') <input type="hidden" name="tab" value="all"> @endif
            @unless ($project)
                <select name="project" aria-label="Filter proyek" onchange="this.form.submit()">
                    <option value="">Semua proyek</option>
                    @foreach ($choices as $choice)
                        <option value="{{ $choice->projectId }}" @selected($projectFilter === $choice->projectId)>{{ $choice->name }}</option>
                    @endforeach
                </select>
            @endunless
            <select name="status" aria-label="Filter status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                @foreach (App\Enums\ActionItemStatus::cases() as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->value }}</option>
                @endforeach
            </select>
            <noscript><button type="submit">Terapkan</button></noscript>
        </form>
    </div>

    @if ($items->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="check-square" size="24" /></span>
            <h2>Belum ada action item</h2>
            <p class="muted">
                @if ($status)
                    Tidak ada action item berstatus {{ $status->value }} di tab ini.
                @else
                    Action item muncul setelah MoM difinalkan oleh TL.
                @endif
            </p>
        </div>
    @else
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>Action item</th><th>Proyek</th><th>Sumber MoM</th><th>PIC</th><th>Deadline</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php $meeting = $item->mom->meeting; @endphp
                        <tr>
                            <td><strong>{{ $item->description }}</strong></td>
                            <td><span class="sub">{{ $projectNames[$meeting->project_id] ?? $meeting->project_id }}</span></td>
                            <td>
                                <a href="/moms/{{ $item->mom_id }}">{{ $meeting->title }}</a>
                                <div class="sub">{{ $item->mom->meeting_date->locale('id')->translatedFormat('d M Y') }}</div>
                            </td>
                            <td><div class="sub" style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->pic_account_id)" size="28" /> {{ $name($item->pic_account_id) }}</div></td>
                            <td>{{ $item->due_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>
                                <x-status-badge :value="$item->status->value" />
                                @if ($item->isOverdue()) <x-status-badge value="OVERDUE" /> @endif
                            </td>
                            <td>
                                <div class="actions" style="flex-wrap:nowrap; align-items:center">
                                    @can('updateStatus', $item)
                                        @if ($next[$item->id])
                                            <form method="POST" action="/action-items/{{ $item->id }}/status" class="actions" style="flex-wrap:nowrap">
                                                @method('PATCH')
                                                @csrf
                                                <select name="status" aria-label="Status baru">
                                                    <option disabled>{{ $item->status->value }}</option>
                                                    <option value="{{ $next[$item->id] }}" selected>{{ $next[$item->id] }}</option>
                                                </select>
                                                <button type="submit" class="primary">Simpan</button>
                                            </form>
                                        @endif
                                    @endcan
                                    <a href="/action-items/{{ $item->id }}"><x-icon name="clock" size="14" /> Riwayat</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
