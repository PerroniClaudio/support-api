@component('mail::message', ['brand_url' => $brand_url])
## Apertura ticket

Ticket n° {{ $ticket->id }} <br>
Azienda: {{ $company->name }} <br>
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br>
Messaggio: {{ $ticket->description }} <br>

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent