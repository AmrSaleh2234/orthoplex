@component('mail::message')
# You're Invited!

You have been invited to join a team on {{ config('app.name') }}.

Click the button below to accept the invitation and set up your account.

@component('mail::button', ['url' => $acceptUrl])
Accept Invitation
@endcomponent

This invitation will expire in 7 days.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
