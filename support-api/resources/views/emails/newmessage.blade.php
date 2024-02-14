@component('mail::message', ['url' => $url, 'brand_url' => $brand_url])
## Nuovo messaggio dal Supporto
@if($mailType != "admin" && $mailType != "support")
Buongiorno,<br><br>
@endif
@if($mailType == "referer")
Questa mail ti è stata inviata perchè sei il referente in sede per il relativo ticket. <br><br>
@elseif($mailType == "referer_it")
Questa mail ti è stata inviata perchè sei il referente IT per il relativo ticket. <br><br>
@endif
C'è un nuovo messaggio nel ticket n° {{ $ticket->id }}:<br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
