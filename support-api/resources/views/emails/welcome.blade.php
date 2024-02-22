@component('mail::message')
## Creazione utenza

Buongiorno {{ $user->name }},<br>
le comunichiamo la creazione della sua utenza sul portale di supporto iFortech.

Può impostare la sua password al seguente link.

@component('mail::button', ['url' => $url])
Imposta password
@endcomponent

@endcomponent

