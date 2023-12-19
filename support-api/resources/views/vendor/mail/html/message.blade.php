<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
    <img src="https://frontend.ifortech.com/images/logo.png" class="logo-horizontal" alt="ifortech Logo">
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. <br>
<p>Le informazioni contenute nella presente comunicazione e i relativi allegati possono essere riservate e sono, comunque, destinate esclusivamente alle persone o alla Società indicate nella mail.
Se avete ricevuto questo messaggio per errore, vi preghiamo di distruggerlo e di informarci immediatamente inviando un messaggio all’indirizzo e-mail <a href="mailto:info@ifortech.com">info@ifortech.com</a>.
The information in this communication (which includes any documents with it) is confidential and may also be legally privileged.
If you have received this message for error, please destroy this and inform us immediately by e-mail message at <a href="mailto:info@ifortech.com">info@ifortech.com</a></p>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
