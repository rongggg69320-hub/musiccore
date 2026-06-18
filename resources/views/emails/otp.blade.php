@component('mail::message')
# Password Reset Code

Hello,

Your password reset code is:

@component('mail::panel')
**{{ $otp }}**
@endcomponent

This code will expire in **15 minutes**.

If you did not request a password reset, please ignore this email.

Thanks,
{{ config('app.name') }}
@endcomponent
