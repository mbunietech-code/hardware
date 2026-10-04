@props(['value'])
@php
    $color = match ($value) {
        'paid', 'completed', 'active', 'accepted', 'approved', 'closed', 'OK' => 'badge-green',
        'partial', 'draft', 'open', 'Low', 'retry' => 'badge-amber',
        'unpaid', 'voided', 'cancelled', 'rejected', 'conflict', 'inactive', 'Out of stock', 'revoked' => 'badge-red',
        default => 'badge-blue',
    };
@endphp
<span class="badge {{ $color }}">{{ __(str_replace('_', ' ', $value)) }}</span>
