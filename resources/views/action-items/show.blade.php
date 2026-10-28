@extends('layouts.app')

@section('title', 'Riwayat Action item')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $meeting = $item->mom->meeting;
        $steps = ['TODO', 'IN_PROGRESS', 'DONE'];
    @endphp

    <a class="back" href="/projects/{{ $project->projectId }}/action-items"><x-icon name="arrow-left" size="16" /> Action item</a>

    <div class="page-head">
        <div>
            <div class="actions" style="align-items:center; margin-bottom:8px">
                <x-status-badge :value="$item->status->value" />
                @if ($item->isOverdue()) <x-status-badge value="OVERDUE" /> @endif
                <span class="muted mono">{{ $project->projectId }}</span>
            </div>
            <h1>{{ $item->description }}</h1>
            <div class="actions muted" style="margin-top:8px; gap:16px">
                <span class="sub"><x-icon name="user" size="14" /> PIC {{ $name($item->pic_account_id) }}</span>
                <span class="sub"><x-icon name="calendar" size="14" /> Deadline {{ $item->due_date->locale('id')->translatedFormat('d M Y') }}</span>
            </div>
        </div>

        @can('updateStatus', $item)
            @if ($next)
                <form method="POST" action="/action-items/{{ $item->id }}/status" class="actions">
                    @method('PATCH')
                    @csrf
                    <button type="submit" name="status" value="{{ $next }}" class="primary"><x-icon name="arrow-right" size="16" /> Ubah ke {{ $next }}</button>
                </form>
            @endif
        @endcan
    </div>

    <div class="split">
        <div class="card">
            <div class="card-head">
                <div class="card-title"><span class="step">01</span><h2>Riwayat Status</h2><span class="count">{{ $history->count() }}</span></div>
            </div>
            <div class="stepper" style="margin-bottom:16px">
                @foreach ($steps as $step)
                    <span @class(['on' => $item->status->value === $step])>{{ $step }}</span>
                    @if (! $loop->last) <x-icon name="chevron-right" size="16" /> @endif
                @endforeach
            </div>
            @if ($history->isEmpty())
                <p class="muted" style="margin:0">Belum ada perubahan status. Action item dibuat berstatus TODO saat MoM ditulis.</p>
            @else
                <ul class="timeline">
                    @foreach ($history as $row)
                        <li>
                            <strong>{{ $row->from_status ? $row->from_status.' → ' : '' }}{{ $row->to_status }}</strong>
                            <small>{{ $name($row->changed_by) }} · {{ $row->changed_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }} WIB</small>
                            @if ($row->note) <small>{{ $row->note }}</small> @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="stack">
            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><h2>Ringkasan</h2></div>
                <dl class="kv">
                    <div><dt>Proyek</dt><dd>{{ $project->name }}</dd></div>
                    <div><dt>Sumber MoM</dt><dd><a href="/moms/{{ $item->mom_id }}">{{ $meeting->title }}</a><div class="sub">{{ $item->mom->meeting_date->locale('id')->translatedFormat('d M Y') }}</div></dd></div>
                    <div><dt>PIC</dt><dd style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->pic_account_id)" size="24" /> {{ $name($item->pic_account_id) }}</dd></div>
                    <div><dt>Deadline</dt><dd>{{ $item->due_date->locale('id')->translatedFormat('d M Y') }}</dd></div>
                    <div><dt>Status</dt><dd><x-status-badge :value="$item->status->value" /></dd></div>
                    <div><dt>Selesai (WIB)</dt><dd>{{ $item->completed_at?->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                </dl>
            </div>
            @cannot('updateStatus', $item)
                <div class="alert info"><x-icon name="lock" /> Hanya PIC yang dapat mengubah status action item ini.</div>
            @endcannot
        </aside>
    </div>
@endsection
