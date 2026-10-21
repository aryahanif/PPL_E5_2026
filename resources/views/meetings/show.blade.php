@extends('layouts.app')

@section('title', 'Detail Rapat')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $at = $meeting->scheduled_at->timezone($tz)->locale('id');
        $scheduled = $meeting->status === App\Enums\MeetingStatus::SCHEDULED;
        $creator = $people[$meeting->created_by]->displayName ?? $meeting->created_by;
        $momStatus = $meeting->mom?->status->value;
        $momSteps = ['DRAFT', 'IN_REVIEW', 'FINAL'];
    @endphp

    <a class="back" href="/projects/{{ $project->projectId }}/meetings"><x-icon name="arrow-left" size="16" /> Rapat</a>

    <div class="page-head">
        <div>
            <div class="actions" style="align-items:center; margin-bottom:8px">
                <x-status-badge :value="$meeting->status->value" />
                <span class="muted mono">{{ $project->projectId }}</span>
            </div>
            <h1>{{ $meeting->title }}</h1>
            <div class="actions muted" style="margin-top:8px; gap:16px">
                <span class="sub"><x-icon name="calendar" size="14" /> {{ $at->translatedFormat('l, d M Y H:i') }} WIB</span>
                <span class="sub"><x-icon name="pin" size="14" /> {{ $meeting->location ?? '—' }}</span>
                <span class="sub"><x-icon name="user" size="14" /> Dibuat oleh {{ $creator }}</span>
            </div>
        </div>

        @if ($scheduled)
            @can('updateStatus', $meeting)
                <form method="POST" action="/meetings/{{ $meeting->id }}/status" class="actions">
                    @method('PATCH')
                    @csrf
                    <button type="submit" name="status" value="CANCELLED" class="danger"
                            onclick="return confirm('Batalkan rapat ini? Status tidak dapat dikembalikan.')"><x-icon name="x" size="16" /> Batalkan Rapat</button>
                    <button type="submit" name="status" value="DONE" class="primary"><x-icon name="check" size="16" /> Tandai Selesai</button>
                </form>
            @endcan
        @endif
    </div>

    <div class="split">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="step">01</span><h2>Peserta</h2><span class="count">{{ $meeting->participants->count() }}</span></div>
                </div>
                @if ($meeting->participants->isEmpty())
                    <p class="muted">Belum ada peserta.</p>
                @else
                    <ul class="people">
                        @foreach ($meeting->participants as $participant)
                            @php
                                $person = $participant->account_id ? ($people[$participant->account_id] ?? null) : null;
                                $name = $person?->displayName ?? $participant->display_name ?? $participant->account_id;
                            @endphp
                            <li>
                                <x-avatar :name="$name" size="36" />
                                <div class="who">
                                    <strong>{{ $name }}</strong>
                                    <span class="role">{{ $person?->role->value ?? 'Klien' }}</span>
                                </div>
                                <x-status-badge :value="$participant->participant_type->value" />
                                <span class="muted" style="min-width:90px; text-align:right">
                                    @if (! $meeting->mom)
                                        Belum dicatat
                                    @else
                                        {{ $participant->attended ? 'Hadir' : 'Tidak hadir' }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="step">02</span><h2>Minutes of Meeting</h2></div>
                    @if ($momStatus)
                        <x-status-badge :value="$momStatus" />
                    @endif
                </div>
                <div class="stepper">
                    @foreach ($momSteps as $step)
                        <span @class(['on' => $momStatus === $step || ($momStatus === 'REVISION' && $step === 'IN_REVIEW')])>{{ $step }}</span>
                        @if (! $loop->last) <x-icon name="chevron-right" size="16" /> @endif
                    @endforeach
                    <span @class(['on' => $momStatus === 'REVISION'])>REVISION</span>
                </div>
                <p class="muted" style="margin:14px 0 0">
                    @if ($meeting->mom)
                        MoM ditulis TW dan direview TL. Status saat ini {{ $momStatus }}.
                    @elseif ($meeting->status === App\Enums\MeetingStatus::DONE)
                        Rapat selesai. Menunggu TW menulis MoM.
                    @else
                        MoM dapat ditulis TW setelah rapat berstatus DONE.
                    @endif
                </p>
            </div>
        </div>

        <aside class="stack">
            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><h2>Ringkasan</h2></div>
                <dl class="kv">
                    <div><dt>Proyek</dt><dd>{{ $project->name }}</dd></div>
                    <div><dt>Jadwal (WIB)</dt><dd>{{ $at->translatedFormat('d M Y H:i') }}</dd></div>
                    <div><dt>Lokasi</dt><dd>{{ $meeting->location ?? '—' }}</dd></div>
                    <div><dt>Status rapat</dt><dd><x-status-badge :value="$meeting->status->value" /></dd></div>
                    <div><dt>Status MoM</dt><dd>@if ($momStatus) <x-status-badge :value="$momStatus" /> @else — @endif</dd></div>
                </dl>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><h2>Riwayat Status</h2></div>
                @if ($history->isEmpty())
                    <p class="muted" style="margin:0">Belum ada riwayat status.</p>
                @else
                    <ul class="timeline">
                        @foreach ($history as $row)
                            <li>
                                <strong>{{ $row->from_status ? $row->from_status.' → ' : '' }}{{ $row->to_status }}</strong>
                                <small>{{ $people[$row->changed_by]->displayName ?? $row->changed_by }} · {{ $row->changed_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }} WIB</small>
                                @if ($row->note) <small>{{ $row->note }}</small> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
@endsection
