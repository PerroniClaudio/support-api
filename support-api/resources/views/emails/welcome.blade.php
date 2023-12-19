@component('mail::message')
## Attivazione utenza

Buongiorno {{ $user->name }},
le comunichiamo l'attivazione della sua utenza sul portale di supporto iFortech.

Può impostare la sua password al seguente link.

@component('mail::button', ['url' => "{{ $url }}"])
Imposta password
@endcomponent

@endcomponent

