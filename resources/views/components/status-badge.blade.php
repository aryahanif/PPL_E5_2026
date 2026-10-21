@props(['value'])

{{-- Raw uppercase enum value with a coloured dot, coloured by meaning (doc/DESIGN.md §8). --}}
@php
    $tone = match ($value) {
        'ACTIVE', 'DONE', 'FINAL', 'ON_TRACK', 'APPROVED', 'RECORDED', 'CLOSED' => 'success',
        'INACTIVE', 'CANCELLED', 'OVERDUE', 'DELAYED', 'REJECTED' => 'danger',
        'DRAFT', 'REVISION', 'PENDING_ANALYSIS', 'OPEN' => 'warning',
        'SCHEDULED', 'IN_REVIEW', 'IN_PROGRESS', 'ANALYZED' => 'info',
        default => '',
    };
@endphp

<span class="badge {{ $tone }}"><i></i>{{ $value }}</span>
