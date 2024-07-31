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
        <p class="text-lg text-gray-800">Dear</p>
        <p class="mt-4 text-gray-800">
            Welcome to VoicingMap! We're excited to have you join our community.
            Please take a moment to verify your email address to get started on our platform.
        </p>
    </div>
    <div class="mt-6 text-center">
        <p class="text-lg font-bold text-gray-800">THIS IS YOUR VERIFICATION CODE:</p>
        <div class="mt-4 p-4 bg-gray-200 rounded-lg inline-block">
            <span class="text-3xl font-bold text-gray-800 tracking-widest">{{ $code }}</span>
        </div>
    </div>
    <div class="mt-6">
        {{--        <img src="{{asset('images/email.png'}}" alt="Map" class="rounded-lg shadow-md mx-auto">--}}
    </div>
</div>
</body>
</html>
