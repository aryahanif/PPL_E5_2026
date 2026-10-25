@extends('layouts.app')

@section('title', 'Action item')

@section('content')
    @php
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $base = '/projects/'.$project->projectId.'/action-items';
    @endphp

    <section class="hero">
        <div>
            <span class="tag"><i></i> ACTIVE · {{ $project->projectId }}</span>
            <h1 style="margin-top:10px">Action item · {{ $project->name }}</h1>
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
        <div class="kpi"><span class="kpi-icon tone-accent"><x-icon name="check-square" size="20" /></span><div><strong>{{ $items->count() }}</strong><span>Total</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-warning"><x-icon name="clock" size="20" /></span><div><strong>{{ $counts['TODO'] ?? 0 }}</strong><span>TODO</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-info"><x-icon name="arrow-right" size="20" /></span><div><strong>{{ $counts['IN_PROGRESS'] ?? 0 }}</strong><span>IN_PROGRESS</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-success"><x-icon name="check" size="20" /></span><div><strong>{{ $counts['DONE'] ?? 0 }}</strong><span>DONE</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-danger"><x-icon name="alert" size="20" /></span><div><strong>{{ $overdue }}</strong><span>Overdue</span></div></div>
    </div>

    <div class="card-head">
        <nav class="tabs">
            <a href="{{ $base }}" @class(['active' => $tab === 'mine'])>Milik saya</a>
            @if ($canSeeAll)
                <a href="{{ $base }}?tab=all" @class(['active' => $tab === 'all'])>Semua di proyek</a>
            @endif
        </nav>
    </div>

    @if ($items->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="check-square" size="24" /></span>
            <h2>Belum ada action item</h2>
            <p class="muted">Action item muncul setelah MoM difinalkan oleh TL.</p>
        </div>
    @else
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>Deskripsi</th><th>Sumber</th><th>PIC</th><th>Tenggat</th><th>Status</th><th>Ubah status</th></tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item->description }}</strong></td>
                            <td>
                                <a href="/moms/{{ $item->mom_id }}">MoM</a>
                                <div class="sub">{{ $item->mom->meeting->title }}</div>
                            </td>
                            <td><div class="sub" style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->pic_account_id)" size="28" /> {{ $name($item->pic_account_id) }}</div></td>
                            <td>{{ $item->due_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>
                                <x-status-badge :value="$item->status->value" />
                                @if ($item->isOverdue()) <x-status-badge value="OVERDUE" /> @endif
                            </td>
                            <td>
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
                                    @else
                                        <span class="muted">Selesai</span>
                                    @endif
                                @else
                                    <span class="muted">Hanya PIC</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
