@component('mail::message')
## Update ticket

L'utente {{ $user->name }} ha fatto un update.

Ticket n° {{ $ticket->id }} <br>
Azienda: {{ $company->name }} <br>
Categoria: {{ $category->name }} <br>
Tipo di ticket: {{ $ticketType->name }} <br><br>
Update: {{ $update->content }} <br><br>
Stato:
@component('mail::status', ['status' => $ticket->status, 'stages' => $stages])
@endcomponent

<br>
@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent