@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Apertura ticket

@if($mailType == "user") 
Di seguito la sintesi del ticket che hai aperto. <br><br>
@elseif($mailType == "referer")
Sei stato indicato come referente in sede per il seguente ticket. <br><br>
@elseif($mailType == "referer_it")
Sei stato indicato come referente IT per il seguente ticket. <br><br>
@endif

Ticket n° {{ $ticket->id }} <br>
@if($mailType == "admin") 
Azienda: {{ $company->name }} <br>
@endif
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br>
Messaggio: <br>{{ $ticket->description }} <br>

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent