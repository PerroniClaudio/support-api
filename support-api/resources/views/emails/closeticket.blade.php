@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Chiusura {{ $category->is_problem ? 'Incident' : 'Request' }}

<p style="font-size: 11px; line-height: 12px;">
  <span style="font-size: 14px;"><b>Si prega di non rispondere a questa email.</b></span> Per comunicare col supporto in merito a questo ticket, 
  accedere al portale tramite il bottone sottostante ed utilizzare l'apposita sezione di messaggistica 
  nella pagina di dettaglio del ticket. In alternativa all'utilizzo del bottone sottostante si può 
  accedere al portale e selezionare dalla lista il ticket desiderato o crearne uno nuovo. <br>
  Se il proprio account non è ancora attivo si devono seguire le indicazioni contenute nell'email di attivazione ricevuta. <br>
  Si ricorda che in caso di password dimenticata, si può recuperare utilizzando il tasto apposito 
  nella schermata di login ed indicando l'indirizzo email del proprio account (solitamente il proprio indirizzo email aziendale).
</p>

Buongiorno,

{{ $category->is_problem ? 'L\'Incident ' : 'La Request ' }} n° {{ $ticket->id }} {{ $category->is_problem ? ' è stato chiuso ' : ' è stata chiusa ' }} col messaggio seguente:<br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
