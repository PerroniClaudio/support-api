
@props([
    'status',
    'stages'
])
<div class="status-label status-{{ $status }}-box">
<span class="status-{{ $status }}-circle">●</span>
{{ $stages[$status] }}
</div>