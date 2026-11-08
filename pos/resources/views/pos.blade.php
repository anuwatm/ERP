<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="{{ asset('fonts/sarabun-regular-v1.ttf') }}" as="font" type="font/ttf" crossorigin>
    <title>POS — หน้าขาย</title>
    @include('partials.theme-preference')
    @vite('resources/pos.tsx')
</head>
<body><div id="root"></div></body>
</html>
