@component('mail::message')

# Report orario - Ticket non gestiti e in gestione

## Problemi
@component('mail::table')
|Azienda|Tipologia|Aperto il|Visita|
|:--|:--|:--|:--|
@foreach ($tickets as $ticket)
    @if($ticket->ticketType->category->is_problem)
        |{{ $ticket->company->name }}|{{ $ticket->ticketType->name }}|{{ $ticket->created_at->format('d/m/Y H:i') }}|<a href="{{ config('app.frontend_url') }}/support/admin/ticket/{{ $ticket->id }}">Visualizza</a>|
    @endif

@endforeach
@endcomponent

## Richieste

@component('mail::table')
|Azienda|Tipologia|Aperto il|Visita|
|:--|:--|:--|:--|
@foreach ($tickets as $ticket)
    @if($ticket->ticketType->category->is_request)
        |{{ $ticket->company->name }}|{{ $ticket->ticketType->name }}|{{ $ticket->created_at->format('d/m/Y H:i') }}|<a href="{{ config('app.frontend_url') }}/support/admin/ticket/{{ $ticket->id }}">Visualizza</a>|
    @endif
@endforeach
@endcomponent

@endcomponent