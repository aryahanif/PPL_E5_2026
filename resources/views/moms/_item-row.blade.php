{{-- One action item row of the MoM editor. $i is the row index ("__i__" in the JS template). --}}
<tr>
    <td>
        @if (! empty($row['actionItemId']))
            <input type="hidden" name="actionItems[{{ $i }}][actionItemId]" value="{{ $row['actionItemId'] }}">
        @endif
        <input type="text" name="actionItems[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" placeholder="Tindak lanjut yang harus dikerjakan">
        @if ($errors->has("actionItems.$i.description"))
            <div class="field-error"><x-icon name="alert" size="14" /> {{ $errors->first("actionItems.$i.description") }}</div>
        @endif
    </td>
    <td>
        <select name="actionItems[{{ $i }}][picAccountId]">
            <option value="">Pilih PIC</option>
            @foreach ($members as $member)
                <option value="{{ $member->accountId }}" @selected(($row['picAccountId'] ?? '') === $member->accountId)>{{ $member->displayName }} ({{ $member->role->value }})</option>
            @endforeach
        </select>
        @if ($errors->has("actionItems.$i.picAccountId"))
            <div class="field-error"><x-icon name="alert" size="14" /> {{ $errors->first("actionItems.$i.picAccountId") }}</div>
        @endif
    </td>
    <td>
        <input type="date" name="actionItems[{{ $i }}][dueDate]" value="{{ $row['dueDate'] ?? '' }}">
        @if ($errors->has("actionItems.$i.dueDate"))
            <div class="field-error"><x-icon name="alert" size="14" /> {{ $errors->first("actionItems.$i.dueDate") }}</div>
        @endif
    </td>
    <td><button type="button" class="danger remove-row" title="Hapus baris" aria-label="Hapus baris"><x-icon name="trash" size="16" /></button></td>
</tr>
