@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Chiusura ticket

Buongiorno,

Il ticket n° {{ $ticket->id }} è stato chiuso col messaggio seguente:<br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
