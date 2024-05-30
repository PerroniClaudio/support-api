@component('mail::message', ['url' => $url, 'brand_url' => $brand_url, 'previewText' => $previewText])
@if($mailType != "admin" && $mailType != "support")
## Nuovo messaggio dal Supporto
@else
## Nuovo messaggio {{ $sender->is_admin ? "al cliente " . $company->name : "dal cliente " . $company->name . ' - ' . $sender->name . ($sender->surname ?? '') }}
@endif
@if($mailType == "referer")
Buongiorno, <br><br>
Questa mail ti è stata inviata perchè sei il referente in sede per il relativo ticket. <br><br>
@elseif($mailType == "referer_it")
Buongiorno, <br><br>
Questa mail ti è stata inviata perchè sei il referente IT per il relativo ticket. <br><br>
@endif
{{ $category->is_problem ? 'Incident' : 'Richiesta' }} n° {{ $ticket->id }} - {{ $ticketType->name }}<br><br>
Testo del messaggio: <br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
