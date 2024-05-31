@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Apertura {{ $category->is_problem ? 'Incident' : 'Request' }}

@if($mailType == "user") 
Di seguito la sintesi del ticket che hai aperto. <br><br>
@elseif($mailType == "referer")
Sei stato indicato come referente in sede per {{ $category->is_problem ? 'il seguente Incident' : 'la seguente Request' }}. <br><br>
@elseif($mailType == "referer_it")
Sei stato indicato come referente IT per {{ $category->is_problem ? 'il seguente Incident' : 'la seguente Request' }}. <br><br>
@endif

{{ $category->is_problem ? 'Incident' : 'Request' }} n° {{ $ticket->id }} <br>
@if($mailType == "admin") 
Azienda: {{ $company->name }} <br>
Utente: {{ $user->is_admin ? 'Supporto' : $user->name . ' ' . $user->surname ?? '' }} <br>
@endif
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br><br>
Messaggio: <br>{{ $ticket->description }} <br><br>

@if(isset($form))
  {!! $form !!}
@endif

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent