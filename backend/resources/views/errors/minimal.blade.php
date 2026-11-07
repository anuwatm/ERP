@php
    $status = trim($__env->yieldContent('code'));
    [$heading, $description] = match ($status) {
        '401' => ['Sign-in required', 'Sign in again to continue.'],
        '403' => ['Access denied', 'Your account does not have access to this page.'],
        '404' => ['Page not found', 'Check the address or return to the home page.'],
        '419' => ['Session expired', 'Return to the home page and open your form again.'],
        '429' => ['Too many requests', 'Wait a moment before trying again.'],
        '503' => ['Service unavailable', 'Try again when the service is available.'],
        default => ['Something went wrong', 'Return to the home page or try again later.'],
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} · Company OS ERP</title>
    @include('layouts.standalone-theme')
    <style>
        body.erp-error {
            box-sizing: border-box;
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            place-items: center;
            font: 16px system-ui, sans-serif;
        }
        .erp-error main {
            box-sizing: border-box;
            width: min(100%, 32rem);
            padding: clamp(1.25rem, 5vw, 2rem);
            border: 1px solid var(--line);
            border-radius: 1rem;
        }
        .erp-error h1 { margin: .25rem 0 .75rem; font-size: clamp(1.5rem, 5vw, 2rem); }
        .erp-error p { margin: 0 0 1rem; line-height: 1.5; }
        .erp-error .status-code { margin-bottom: 0; font-size: .875rem; font-weight: 700; }
    </style>
</head>
<body class="standalone erp-error">
    <main>
        @if (preg_match('/^[45][0-9]{2}$/D', $status))
            <p class="status-code">{{ $status }}</p>
        @endif
        <h1>{{ $heading }}</h1>
        <p>{{ $description }}</p>
        <a href="{{ url('/') }}">Return to home</a>
    </main>
</body>
</html>
