@extends('layouts.app')

@section('title', $mom ? 'Ubah MoM' : 'Tulis MoM')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $at = $meeting->scheduled_at->timezone($tz)->locale('id');
        $hasOld = session()->hasOldInput();
        $attended = $hasOld
            ? (array) old('attendance', [])
            : $meeting->participants->where('attended', true)->pluck('id')->all();
        $rows = $hasOld ? (array) old('actionItems', []) : $items;
        $nextIndex = $rows ? max(array_map('intval', array_keys($rows))) + 1 : 0;
    @endphp

    <a class="back" href="/meetings/{{ $meeting->id }}"><x-icon name="arrow-left" size="16" /> Detail rapat</a>

    <div class="page-head">
        <div>
            <div class="actions" style="align-items:center; margin-bottom:8px">
                @if ($mom) <x-status-badge :value="$mom->status->value" /> @else <x-status-badge value="DRAFT" /> @endif
                <span class="muted mono">{{ $project->projectId }}</span>
            </div>
            <h1>{{ $mom ? 'Ubah MoM' : 'Tulis MoM' }} · {{ $project->name }}</h1>
            <p class="muted">Minutes of Meeting mengikuti 8 butir WI-05. Action item baru selalu berstatus TODO.</p>
        </div>
    </div>

    @if ($mom && $mom->status === App\Enums\MomStatus::REVISION && $mom->review_note)
        <div class="alert info">
            <x-icon name="info" />
            <div><strong>Catatan revisi dari TL:</strong><br>{{ $mom->review_note }}</div>
        </div>
    @endif

    <form method="POST" action="{{ $mom ? '/moms/'.$mom->id : '/meetings/'.$meeting->id.'/mom' }}">
        @csrf
        @if ($mom) @method('PUT') @endif

        @if ($errors->any())
            <div class="alert danger">
                <x-icon name="alert" />
                <div>
                    Harap lengkapi isian MoM yang wajib diisi.
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-title" style="margin-bottom:16px"><span class="step">01</span><h2>Informasi Rapat</h2></div>
            <div class="cols">
                <div class="field">
                    <label for="meeting">Rapat</label>
                    <input id="meeting" type="text" value="{{ $meeting->title }}" readonly>
                </div>
                <div class="field @error('meetingDate') invalid @enderror">
                    <label for="meetingDate">Tanggal rapat <span class="req">*</span></label>
                    <input id="meetingDate" name="meetingDate" type="date" value="{{ old('meetingDate', $meetingDate) }}">
                    @error('meetingDate') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="schedule">Jadwal (WIB)</label>
                    <input id="schedule" type="text" value="{{ $at->translatedFormat('d M Y H:i') }}" readonly>
                </div>
                <div class="field">
                    <label for="location">Lokasi</label>
                    <input id="location" type="text" value="{{ $meeting->location ?? '—' }}" readonly>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:16px"><span class="step">02</span><h2>Kehadiran</h2></div>
            <fieldset class="field @error('attendance') invalid @enderror" style="border:0; padding:0; margin:0">
                <legend>Centang peserta yang hadir</legend>
                <div class="choices">
                    @foreach ($meeting->participants as $participant)
                        @php
                            $person = $participant->account_id ? ($people[$participant->account_id] ?? null) : null;
                            $name = $person?->displayName ?? $participant->display_name ?? $participant->account_id;
                        @endphp
                        <label class="choice">
                            <input type="checkbox" name="attendance[]" value="{{ $participant->id }}" @checked(in_array($participant->id, $attended, true))>
                            <x-avatar :name="$name" size="34" />
                            <span>{{ $name }}<br><span class="role">{{ $person?->role->value ?? 'Klien' }}</span></span>
                            <span class="tick"><x-icon name="check" size="14" /></span>
                        </label>
                    @endforeach
                </div>
                @error('attendance') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                @error('attendance.*') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
            </fieldset>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:16px"><span class="step">03</span><h2>Pembahasan</h2></div>
            <div class="field @error('discussion') invalid @enderror" style="margin-bottom:0">
                <label for="discussion">Hal yang dibahas <span class="req">*</span></label>
                <textarea id="discussion" name="discussion" rows="5" placeholder="Ringkasan pembahasan rapat">{{ old('discussion', $mom?->discussion) }}</textarea>
                @error('discussion') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:16px"><span class="step">04</span><h2>Keputusan</h2></div>
            <div class="field @error('decision') invalid @enderror" style="margin-bottom:0">
                <label for="decision">Keputusan rapat <span class="req">*</span></label>
                <textarea id="decision" name="decision" rows="4" placeholder="Keputusan yang disepakati">{{ old('decision', $mom?->decision) }}</textarea>
                @error('decision') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:16px"><span class="step">05</span><h2>Perubahan / Penyesuaian</h2></div>
            <div class="field @error('changeSummary') invalid @enderror" style="margin-bottom:0">
                <label for="changeSummary">Ringkasan perubahan (opsional)</label>
                <textarea id="changeSummary" name="changeSummary" rows="3" placeholder="Perubahan lingkup, jadwal, atau penyesuaian lain yang muncul di rapat">{{ old('changeSummary', $mom?->change_summary) }}</textarea>
                <div class="hint">Mengubah catatan ini menjadi change request tersedia di slice berikutnya.</div>
                @error('changeSummary') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-title"><span class="step">06</span><h2>Action Item</h2></div>
                <button type="button" id="add-row"><x-icon name="plus" size="16" /> Tambah action item</button>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr><th>Deskripsi <span class="req">*</span></th><th>PIC <span class="req">*</span></th><th>Tenggat <span class="req">*</span></th><th></th></tr>
                    </thead>
                    <tbody id="rows">
                        @foreach ($rows as $i => $row)
                            @include('moms._item-row', ['i' => $i, 'row' => $row])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" id="rows-empty" style="margin:12px 0 0" @if ($rows) hidden @endif>Belum ada action item. Klik Tambah action item bila ada tindak lanjut.</p>

            <div class="card-foot">
                <button type="submit" name="action" value="draft"><x-icon name="file" size="16" /> Simpan Draft</button>
                <button type="submit" name="action" value="submit" class="primary"
                        onclick="return confirm('Ajukan MoM ke TL untuk direview? MoM tidak dapat diubah selama review.')"><x-icon name="check" size="16" /> Ajukan Review</button>
            </div>
        </div>
    </form>

    <template id="row-template">
        @include('moms._item-row', ['i' => '__i__', 'row' => []])
    </template>

    <script>
        (function () {
            var next = {{ $nextIndex }};
            var body = document.getElementById('rows');
            var empty = document.getElementById('rows-empty');
            var sync = function () { empty.hidden = body.children.length > 0; };

            document.getElementById('add-row').addEventListener('click', function () {
                var html = document.getElementById('row-template').innerHTML.split('__i__').join(next++);
                body.insertAdjacentHTML('beforeend', html);
                sync();
            });

            body.addEventListener('click', function (event) {
                var button = event.target.closest('.remove-row');
                if (button) { button.closest('tr').remove(); sync(); }
            });
        })();
    </script>
@endsection
