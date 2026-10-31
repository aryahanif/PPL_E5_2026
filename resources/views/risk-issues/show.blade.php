@extends('layouts.app')

@section('title', 'Detail Risiko/Isu')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $typeLabel = $item->type->value === 'RISK' ? 'Risiko' : 'Isu';
        $impactTone = ['HIGH' => 'danger', 'MEDIUM' => 'warning', 'LOW' => 'info'];
        $steps = ['OPEN', 'IN_PROGRESS', 'CLOSED'];
    @endphp

    <a class="back" href="/projects/{{ $project->projectId }}/risk-issues"><x-icon name="arrow-left" size="16" /> Risiko &amp; Isu</a>

    <div class="page-head">
        <div>
            <div class="actions" style="align-items:center; margin-bottom:8px">
                <span class="badge">{{ $typeLabel }}</span>
                <x-status-badge :value="$item->status->value" />
                @if ($item->impact_level)
                    <span class="badge {{ $impactTone[$item->impact_level->value] }}"><i></i>Dampak {{ $item->impact_level->value }}</span>
                @endif
            </div>
            <h1>{{ $item->title }}</h1>
            <div class="actions muted" style="margin-top:8px; gap:16px">
                <span class="sub"><x-icon name="user" size="14" /> PIC {{ $name($item->pic_account_id) }}</span>
                <span class="sub"><x-icon name="calendar" size="14" /> Dicatat {{ $item->created_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }} WIB oleh {{ $name($item->reported_by) }}</span>
            </div>
        </div>
    </div>

    <div class="split">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="step">01</span><h2>Detail {{ $typeLabel }}</h2></div>
                </div>
                <div class="stepper" style="margin-bottom:16px">
                    @foreach ($steps as $step)
                        <span @class(['on' => $item->status->value === $step])>{{ $step }}</span>
                        @if (! $loop->last) <x-icon name="chevron-right" size="16" /> @endif
                    @endforeach
                </div>
                <dl class="kv">
                    <div><dt>Deskripsi</dt><dd style="white-space:pre-line">{{ $item->description }}</dd></div>
                    <div><dt>Mitigasi</dt><dd style="white-space:pre-line">{{ $item->mitigation ?? '—' }}</dd></div>
                    <div><dt>Sumber MoM</dt><dd>
                        @if ($item->sourceMom)
                            <a href="/moms/{{ $item->sourceMom->id }}">{{ $item->sourceMom->meeting->title }}</a>
                        @else
                            —
                        @endif
                    </dd></div>
                    @if ($item->status->value === 'CLOSED')
                        <div><dt>Catatan penyelesaian</dt><dd style="white-space:pre-line">{{ $item->resolution_note ?? '—' }}</dd></div>
                        <div><dt>Ditutup (WIB)</dt><dd>{{ $item->closed_at?->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($canEdit)
                <form method="POST" action="/risk-issues/{{ $item->id }}" class="card">
                    @method('PATCH')
                    @csrf

                    <div class="card-title" style="margin-bottom:16px"><span class="step">02</span><h2>Perbarui {{ $typeLabel }}</h2></div>

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

                    @if ($fullEdit)
                        @include('risk-issues._fields')
                    @else
                        <div class="alert info"><x-icon name="info" /> Sebagai PIC, Anda dapat memperbarui status, mitigasi, dan catatan penyelesaian.</div>

                        <div class="field @error('mitigation') invalid @enderror">
                            <label for="mitigation">Mitigasi</label>
                            <textarea id="mitigation" name="mitigation" rows="2" placeholder="Langkah untuk mencegah atau mengatasi">{{ old('mitigation', $item->mitigation) }}</textarea>
                            @error('mitigation') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div class="cols">
                        <div class="field @error('status') invalid @enderror">
                            <label for="status">Status <span class="req">*</span></label>
                            <select id="status" name="status">
                                @foreach ($statuses as $option)
                                    <option value="{{ $option->value }}" @selected(old('status', $item->status->value) === $option->value)>
                                        {{ $option->value }}{{ $option === $item->status ? ' (saat ini)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint">CLOSED tidak dapat dibuka kembali.</div>
                            @error('status') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field @error('resolutionNote') invalid @enderror">
                        <label for="resolutionNote">Catatan penyelesaian</label>
                        <textarea id="resolutionNote" name="resolutionNote" rows="2" placeholder="Bagaimana risiko/isu ini diselesaikan">{{ old('resolutionNote', $item->resolution_note) }}</textarea>
                        <div class="hint">Wajib diisi saat status diubah ke CLOSED.</div>
                        @error('resolutionNote') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <div class="card-foot">
                        <button type="button" onclick="location.href='/projects/{{ $project->projectId }}/risk-issues'">Batal</button>
                        <button type="submit" class="primary"><x-icon name="check" size="16" /> Simpan Perubahan</button>
                    </div>
                </form>
            @else
                <div class="alert info"><x-icon name="lock" /> Hanya PM, atau TL yang menjadi PIC, yang dapat memperbarui {{ strtolower($typeLabel) }} ini.</div>
            @endif
        </div>

        <aside class="stack">
            <div class="card">
                <div class="card-title" style="margin-bottom:16px"><h2>Ringkasan</h2></div>
                <dl class="kv">
                    <div><dt>Proyek</dt><dd>{{ $project->name }}</dd></div>
                    <div><dt>Jenis</dt><dd>{{ $typeLabel }}</dd></div>
                    <div><dt>Status</dt><dd><x-status-badge :value="$item->status->value" /></dd></div>
                    <div><dt>Dampak</dt><dd>{{ $item->impact_level?->value ?? '—' }}</dd></div>
                    <div><dt>PIC</dt><dd style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->pic_account_id)" size="24" /> {{ $name($item->pic_account_id) }}</dd></div>
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
                                <strong>{{ $row->from_status && $row->from_status !== $row->to_status ? $row->from_status.' → ' : '' }}{{ $row->to_status }}</strong>
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
