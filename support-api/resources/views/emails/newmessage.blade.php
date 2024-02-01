@component('mail::message', ['url' => $url, 'brand_url' => $brand_url])
## Nuovo messaggio dal Supporto

Buongiorno,

c'è un nuovo messaggio nel ticket n° {{ $ticket->id }}:<br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
