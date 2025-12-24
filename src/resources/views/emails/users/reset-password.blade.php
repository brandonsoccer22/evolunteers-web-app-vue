@component('mail::message')
# Welcome to {{ config('app.name') }}

Hi {{ $user->first_name ?? $user->name ?? 'there' }},

An account was created for you. Since no password was provided, you’ll need to set one now.

@component('mail::button', ['url' => $resetUrl])
Reset your password
@endcomponent

If you didn’t expect this email, you can ignore it.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
