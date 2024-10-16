@component('mail::message', ['previewText' => $previewText])
<h2> Update {{ $category->is_problem ? "Incident" : "Request" }} - Stato: 
@component('mail::status', ['status' => $ticket->status, 'stages' => $stages])
@endcomponent
</h2>

L'utente {{ $user->name }} ha fatto un update.

{{ $category->is_problem ? "Incident" : "Request" }} n° {{ $ticket->id }} <br>
Azienda: {{ $company->name }} <br>
Categoria: {{ $category->name }} <br>
Tipo di ticket: {{ $ticketType->name }} <br>
Responsabilità: {{ $ticket->is_user_error ? 'Cliente' : 'Supporto' }} <br><br>
Update: <br>
{{ $update->content }} <br><br>


<br>
@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent