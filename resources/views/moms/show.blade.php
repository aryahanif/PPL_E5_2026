@extends('layouts.app')

@section('title', 'MoM')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $status = $mom->status;
        $writer = $people[$mom->written_by]->displayName ?? $mom->written_by;
        $reviewer = $mom->reviewed_by ? ($people[$mom->reviewed_by]->displayName ?? $mom->reviewed_by) : null;
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
    @endphp

    <a class="back" href="/meetings/{{ $meeting->id }}"><x-icon name="arrow-left" size="16" /> Detail rapat</a>

    <div class="page-head">
        <div>
            <div class="actions" style="align-items:center; margin-bottom:8px">
                <x-status-badge :value="$status->value" />
                <span class="muted mono">{{ $project->projectId }}</span>
            </div>
            <h1>MoM · {{ $meeting->title }}</h1>
            <p class="muted">Rapat {{ $mom->meeting_date->locale('id')->translatedFormat('d M Y') }} · ditulis oleh {{ $writer }}</p>
        </div>

        @if ($editable)
            @can('update', $mom)
                <form method="GET" action="/moms/{{ $mom->id }}/edit">
                    <button type="submit" class="primary"><x-icon name="edit" size="16" /> Edit</button>
                </form>
            @endcan
        @endif
    </div>

    @if ($status === App\Enums\MomStatus::REVISION && $mom->review_note)
        <div class="alert info">
            <x-icon name="info" />
            <div><strong>Catatan revisi dari TL:</strong><br>{{ $mom->review_note }}</div>
        </div>
    @endif

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

    <div class="split">
        <div class="stack">
            @if ($status === App\Enums\MomStatus::IN_REVIEW)
                @can('review', $mom)
                    <form method="POST" action="/moms/{{ $mom->id }}/status" class="card">
                        @method('PATCH')
                        @csrf
                        <div class="card-title" style="margin-bottom:16px"><span class="kpi-icon tone-info" style="width:36px; height:36px"><x-icon name="check-square" /></span><h2>Review MoM</h2></div>
                        <div class="field @error('reviewNote') invalid @enderror" style="margin-bottom:0">
                            <label for="reviewNote">Catatan revisi <span class="muted">(wajib bila meminta revisi)</span></label>
                            <textarea id="reviewNote" name="reviewNote" rows="3" placeholder="Jelaskan bagian yang perlu diperbaiki TW">{{ old('reviewNote') }}</textarea>
                            @error('reviewNote') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                        </div>
                        <div class="card-foot">
                            <button type="submit" name="status" value="REVISION" class="danger"><x-icon name="x" size="16" /> Minta Revisi</button>
                            <button type="submit" name="status" value="FINAL" class="primary"
                                    onclick="return confirm('Finalkan MoM ini? MoM tidak dapat diubah lagi.')"><x-icon name="check" size="16" /> Finalkan</button>
                        </div>
                    </form>
                @endcan
            @endif

            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><span class="step">01</span><h2>Informasi Rapat</h2></div>
                <dl class="kv">
                    <div><dt>Rapat</dt><dd><a href="/meetings/{{ $meeting->id }}">{{ $meeting->title }}</a></dd></div>
                    <div><dt>Tanggal rapat</dt><dd>{{ $mom->meeting_date->locale('id')->translatedFormat('d M Y') }}</dd></div>
                    <div><dt>Jadwal (WIB)</dt><dd>{{ $meeting->scheduled_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }}</dd></div>
                    <div><dt>Lokasi</dt><dd>{{ $meeting->location ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="step">02</span><h2>Kehadiran</h2><span class="count">{{ $meeting->participants->where('attended', true)->count() }}/{{ $meeting->participants->count() }}</span></div>
                </div>
                <ul class="people">
                    @foreach ($meeting->participants as $participant)
                        @php
                            $person = $participant->account_id ? ($people[$participant->account_id] ?? null) : null;
                            $participantName = $person?->displayName ?? $participant->display_name ?? $participant->account_id;
                        @endphp
                        <li>
                            <x-avatar :name="$participantName" size="36" />
                            <div class="who">
                                <strong>{{ $participantName }}</strong>
                                <span class="role">{{ $person?->role->value ?? 'Klien' }}</span>
                            </div>
                            <span class="muted" style="min-width:90px; text-align:right">{{ $participant->attended ? 'Hadir' : 'Tidak hadir' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom:12px"><span class="step">03</span><h2>Pembahasan</h2></div>
                <p style="margin:0; white-space:pre-line">{{ $mom->discussion }}</p>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom:12px"><span class="step">04</span><h2>Keputusan</h2></div>
                <p style="margin:0; white-space:pre-line">{{ $mom->decision }}</p>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom:12px"><span class="step">05</span><h2>Perubahan / Penyesuaian</h2></div>
                @if ($mom->change_summary)
                    <p style="margin:0; white-space:pre-line">{{ $mom->change_summary }}</p>
                @else
                    <p class="muted" style="margin:0">Tidak ada perubahan yang dicatat.</p>
                @endif
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="step">06</span><h2>Action Item</h2><span class="count">{{ $mom->actionItems->count() }}</span></div>
                </div>
                @if ($mom->actionItems->isEmpty())
                    <p class="muted" style="margin:0">Tidak ada action item.</p>
                @else
                    <div class="table-card">
                        <table>
                            <thead>
                                <tr><th>Deskripsi</th><th>PIC</th><th>Tenggat</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($mom->actionItems->sortBy('due_date') as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td><div class="sub" style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->pic_account_id)" size="28" /> {{ $name($item->pic_account_id) }}</div></td>
                                        <td>{{ $item->due_date->locale('id')->translatedFormat('d M Y') }}</td>
                                        <td><x-status-badge :value="$item->status->value" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <aside class="stack">
            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><h2>Ringkasan</h2></div>
                <dl class="kv">
                    <div><dt>Status MoM</dt><dd><x-status-badge :value="$status->value" /></dd></div>
                    <div><dt>Ditulis oleh</dt><dd>{{ $writer }}</dd></div>
                    <div><dt>Direview oleh</dt><dd>{{ $reviewer ?? '—' }}</dd></div>
                    <div><dt>Difinalkan (WIB)</dt><dd>{{ $mom->finalized_at ? $mom->finalized_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') : '—' }}</dd></div>
                </dl>
            </div>
        </aside>
    </div>
@endsection
