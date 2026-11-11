@extends('layouts.app')

@section('title', 'Detail Perubahan')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $typeLabels = ['SCOPE' => 'Ruang lingkup', 'TIMELINE' => 'Jadwal', 'TASK' => 'Tugas', 'RESOURCE' => 'Sumber daya'];
        $steps = $item->is_formal_cr ? ['PENDING_ANALYSIS', 'ANALYZED', $item->status->value === 'REJECTED' ? 'REJECTED' : 'APPROVED'] : ['RECORDED'];
        $needsBaseline = in_array($item->change_type->value, ['SCOPE', 'TIMELINE'], true);
    @endphp

    <a class="back" href="/projects/{{ $project->projectId }}/change-requests"><x-icon name="arrow-left" size="16" /> Change Request</a>

    <div class="page-head">
        <div>
            <div class="actions" style="align-items:center; margin-bottom:8px">
                <span class="badge">{{ $item->is_formal_cr ? 'CR formal' : 'Penyesuaian' }}</span>
                <span class="badge">{{ $typeLabels[$item->change_type->value] }}</span>
                <x-status-badge :value="$item->status->value" />
            </div>
            <h1>{{ \Illuminate\Support\Str::limit($item->change_description, 90) }}</h1>
            <div class="actions muted" style="margin-top:8px; gap:16px">
                <span class="sub"><x-icon name="user" size="14" /> Dicatat oleh {{ $name($item->created_by) }}</span>
                <span class="sub"><x-icon name="calendar" size="14" /> {{ $item->created_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }} WIB</span>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert danger">
            <x-icon name="alert" />
            <div>
                Periksa kembali isian berikut.
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="split">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="step">01</span><h2>Detail Perubahan</h2></div>
                </div>
                <div class="stepper" style="margin-bottom:16px">
                    @foreach ($steps as $step)
                        <span @class(['on' => $item->status->value === $step])>{{ $step }}</span>
                        @if (! $loop->last) <x-icon name="chevron-right" size="16" /> @endif
                    @endforeach
                </div>
                <dl class="kv">
                    <div><dt>Deskripsi</dt><dd style="white-space:pre-line">{{ $item->change_description }}</dd></div>
                    <div><dt>Sumber</dt><dd>{{ $item->source->value === 'USER' ? 'USER · permintaan klien' : 'INTERNAL · dari tim' }}</dd></div>
                    <div><dt>MoM sumber</dt><dd>
                        @if ($item->mom)
                            <a href="/moms/{{ $item->mom->id }}">{{ $item->mom->meeting->title }}</a>
                        @else
                            —
                        @endif
                    </dd></div>
                </dl>
            </div>

            @if ($item->is_formal_cr && $item->status->value !== 'PENDING_ANALYSIS')
                <div class="card">
                    <div class="card-title" style="margin-bottom:16px"><span class="step">02</span><h2>Analisis Dampak</h2></div>
                    <dl class="kv">
                        <div><dt>Dampak</dt><dd style="white-space:pre-line">{{ $item->impact ?? '—' }}</dd></div>
                        <div><dt>Baseline (E-3)</dt><dd>{{ $baseline ? $baseline->baselineItemId.' · '.$baseline->name : ($item->baseline_item_id ?? '—') }}</dd></div>
                        <div><dt>Task terdampak (E-4)</dt><dd>
                            @forelse ($item->affectedTasks as $link)
                                <div>{{ $link->task_id }} · {{ $projectTasks[$link->task_id]->title ?? '' }}</div>
                            @empty
                                —
                            @endforelse
                        </dd></div>
                        <div><dt>Dianalisis oleh</dt><dd>{{ $item->analyzed_by ? $name($item->analyzed_by) : '—' }}</dd></div>
                    </dl>
                </div>
            @endif

            @if (in_array($item->status->value, ['APPROVED', 'REJECTED'], true))
                <div class="card">
                    <div class="card-title" style="margin-bottom:16px"><span class="step">03</span><h2>Keputusan</h2></div>
                    <dl class="kv">
                        <div><dt>Keputusan</dt><dd><x-status-badge :value="$item->status->value" /></dd></div>
                        <div><dt>Alasan</dt><dd style="white-space:pre-line">{{ $item->decision_note ?? '—' }}</dd></div>
                        <div><dt>Diputuskan oleh</dt><dd>{{ $item->decided_by ? $name($item->decided_by) : '—' }}</dd></div>
                        <div><dt>Diputuskan (WIB)</dt><dd>{{ $item->decided_at?->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                    </dl>
                </div>
            @endif

            @if ($canAnalyze)
                <form method="POST" action="/change-requests/{{ $item->id }}/analysis" class="card">
                    @method('PATCH')
                    @csrf

                    <div class="card-title" style="margin-bottom:16px"><span class="step">02</span><h2>Analisis Dampak</h2></div>

                    <div class="field @error('impact') invalid @enderror">
                        <label for="impact">Dampak <span class="req">*</span></label>
                        <textarea id="impact" name="impact" rows="3" placeholder="Dampak ke ruang lingkup, jadwal, dan tugas">{{ old('impact') }}</textarea>
                        @error('impact') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <div class="field @error('baselineItemId') invalid @enderror">
                        <label for="baselineItemId">Baseline (E-3) @if ($needsBaseline)<span class="req">*</span>@endif</label>
                        <select id="baselineItemId" name="baselineItemId">
                            <option value="">Tidak ada</option>
                            @foreach ($baselineItems as $baselineItem)
                                <option value="{{ $baselineItem->baselineItemId }}" @selected(old('baselineItemId') === $baselineItem->baselineItemId)>{{ $baselineItem->baselineItemId }} · {{ $baselineItem->name }} ({{ $baselineItem->type }})</option>
                            @endforeach
                        </select>
                        @if ($needsBaseline)
                            <div class="hint">Wajib untuk perubahan ruang lingkup atau jadwal.</div>
                        @endif
                        @error('baselineItemId') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <fieldset class="field @error('affectedTaskIds.*') invalid @enderror">
                        <legend>Task terdampak (E-4)</legend>
                        <div class="choices">
                            @foreach ($projectTasks as $task)
                                <label class="choice">
                                    <input type="checkbox" name="affectedTaskIds[]" value="{{ $task->taskId }}" @checked(in_array($task->taskId, old('affectedTaskIds', []), true))>
                                    <span>{{ $task->title }}<br><span class="muted" style="font-size:12px">{{ $task->taskId }} · progres {{ (int) $task->progress }}%</span></span>
                                    <span class="tick"><x-icon name="check" size="14" /></span>
                                </label>
                            @endforeach
                        </div>
                        @error('affectedTaskIds.*') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </fieldset>

                    <div class="card-foot">
                        <button type="button" onclick="location.href='/projects/{{ $project->projectId }}/change-requests'">Batal</button>
                        <button type="submit" class="primary"><x-icon name="check" size="16" /> Simpan Analisis</button>
                    </div>
                </form>
            @elseif ($item->status->value === 'PENDING_ANALYSIS')
                <div class="alert info"><x-icon name="lock" /> Menunggu TL menganalisis dampak CR ini.</div>
            @endif

            @if ($canDecide)
                <form method="POST" action="/change-requests/{{ $item->id }}/decision" class="card">
                    @method('PATCH')
                    @csrf

                    <div class="card-title" style="margin-bottom:16px"><span class="step">03</span><h2>Keputusan</h2></div>

                    <fieldset class="field @error('decision') invalid @enderror">
                        <legend>Keputusan <span class="req">*</span></legend>
                        <div class="choices">
                            <label class="choice">
                                <input type="radio" name="decision" value="APPROVED" @checked(old('decision') === 'APPROVED')>
                                <span class="kpi-icon tone-success" style="width:34px; height:34px"><x-icon name="check" size="18" /></span>
                                <span>Setujui<br><span class="muted" style="font-size:12px">APPROVED</span></span>
                                <span class="tick"><x-icon name="check" size="14" /></span>
                            </label>
                            <label class="choice">
                                <input type="radio" name="decision" value="REJECTED" @checked(old('decision') === 'REJECTED')>
                                <span class="kpi-icon tone-danger" style="width:34px; height:34px"><x-icon name="x" size="18" /></span>
                                <span>Tolak<br><span class="muted" style="font-size:12px">REJECTED</span></span>
                                <span class="tick"><x-icon name="check" size="14" /></span>
                            </label>
                        </div>
                        @error('decision') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </fieldset>

                    <div class="field @error('decisionNote') invalid @enderror">
                        <label for="decisionNote">Alasan <span class="req">*</span></label>
                        <textarea id="decisionNote" name="decisionNote" rows="2" placeholder="Alasan menyetujui atau menolak">{{ old('decisionNote') }}</textarea>
                        @error('decisionNote') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <div class="card-foot">
                        <button type="button" onclick="location.href='/projects/{{ $project->projectId }}/change-requests'">Batal</button>
                        <button type="submit" class="primary"><x-icon name="check" size="16" /> Simpan Keputusan</button>
                    </div>
                </form>
            @elseif ($item->status->value === 'ANALYZED')
                <div class="alert info"><x-icon name="lock" /> Menunggu keputusan PM atau TL.</div>
            @endif
        </div>

        <aside class="stack">
            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><h2>Ringkasan</h2></div>
                <dl class="kv">
                    <div><dt>Proyek</dt><dd>{{ $project->name }}</dd></div>
                    <div><dt>Bentuk</dt><dd>{{ $item->is_formal_cr ? 'CR formal' : 'Penyesuaian' }}</dd></div>
                    <div><dt>Jenis</dt><dd>{{ $typeLabels[$item->change_type->value] }}</dd></div>
                    <div><dt>Status</dt><dd><x-status-badge :value="$item->status->value" /></dd></div>
                    <div><dt>Diperbarui (WIB)</dt><dd>{{ $item->updated_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }}</dd></div>
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
                                <small>{{ $name($row->changed_by) }} · {{ $row->changed_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }} WIB</small>
                                @if ($row->note) <small>{{ $row->note }}</small> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
@endsection
