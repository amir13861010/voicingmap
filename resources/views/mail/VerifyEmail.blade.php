<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="max-w-lg mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-800">voicingmap</h1>
            <p class="text-gray-500">This is your voice</p>
        </div>
        <div class="mt-6">
            <p class="mt-4 text-gray-800">
                Welcome to VoicingMap! We're excited to have you join our community.
                Please take a moment to verify your email address to get started on our platform.
            </p>
        </div>
        <div class="mt-6 text-center">
            <p>Please click the button below to verify your email address.</p>
            <a href="{{ $url }}">Verify Email Address</a>

        </div>
        <div class="mt-6">
            © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </div>
    </div>
</body>

</html>
