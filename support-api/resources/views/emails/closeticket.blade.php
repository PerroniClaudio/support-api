@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Chiusura {{ $category->is_problem ? 'Incident' : 'Request' }}

Buongiorno,

{{ $category->is_problem ? 'L\'Incident è stato chiuso ' : 'La Request è stata chiusa ' }} n° {{ $ticket->id }} col messaggio seguente:<br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
