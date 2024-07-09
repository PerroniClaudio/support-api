@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Apertura {{ $category->is_problem ? 'Incident' : 'Request' }}

@if($mailType == "user") 
Di seguito la sintesi dei ticket che hai aperto. <br><br>
@elseif($mailType == "referer")
Sei stato indicato come referente in sede per {{ $category->is_problem ? 'i seguenti Incident' : 'le seguenti Request' }}. <br><br>
@elseif($mailType == "referer_it")
Sei stato indicato come referente IT per {{ $category->is_problem ? 'i seguenti Incident' : 'le seguenti Request' }}. <br><br>
@endif

{{ $category->is_problem ? 'Incident' : 'Request' }}<br>
@if($mailType == "admin") 
Azienda: {{ $company->name }} <br>
Utente: {{ $user->is_admin ? 'Supporto' : $user->name . ' ' . $user->surname ?? '' }} <br>
@endif
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br><br>
Messaggio: <br>{{ $description }} <br><br>

@if(isset($form))
  {!! $form !!}
@endif

<p>Di seguito la lista dei ticket generati:</p>
@foreach($ticketsInfo as $ticketInfo)
{{ $ticketInfo['text'] }} - <a href="{{ $ticketInfo['link'] }}">Vai al ticket</a>

{{-- @component('mail::button', ['url' => $ticketInfo['link']])
Vai al ticket
@endcomponent --}}
@endforeach


@endcomponent
