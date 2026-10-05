<!DOCTYPE html>
<html lang="en">
<body style="font-family:Arial,sans-serif;color:#18181b">
    <h1 style="font-size:20px">INTSEC Security Verification</h1>
    <p>Your verification code is:</p>
    <p style="font-size:30px;font-weight:700;letter-spacing:6px">{{ $code }}</p>
    <p>This code expires in {{ (int) ceil(config('mfa.email_otp_ttl', 300) / 60) }} minutes and can only be used once.</p>
    <p>If you did not request this code, review your account security.</p>
</body>
</html>
