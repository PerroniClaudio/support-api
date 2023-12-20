@component('mail::message')

L'utente {{ $user->name }} ha aggiornato lo stato del ticket #{{ $ticket->id }} in

<x-status>
@slot('status', $ticket->status)
@slot('slot')
Aperto
@endslot
</x-status>

@endcomponent