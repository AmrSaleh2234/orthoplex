@component('mail::message')
# Your Magic Login Link

Click the button below to log in to your account.

@component('mail::button', ['url' => route('magic.login', ['token' => $magicLink->token])])
Log In
@endcomponent

This link will expire in 15 minutes.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
