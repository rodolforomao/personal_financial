@php
    $fiqIcon = fn (string $file) => file_exists(public_path("financialiq/{$file}"))
        ? asset("financialiq/{$file}")
        : null;
    $favicon = file_exists(public_path('favicon.ico'))
        ? asset('favicon.ico')
        : $fiqIcon('favicon.ico');
@endphp
@if($favicon)
    <link rel="icon" href="{{ $favicon }}" sizes="any">
@endif
@if($icon32 = $fiqIcon('logo_32x32.png'))
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $icon32 }}">
@endif
@if($icon16 = $fiqIcon('logo_16x16.png'))
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $icon16 }}">
@endif
@if($apple = $fiqIcon('logo_180x180.png'))
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $apple }}">
@endif
@if(file_exists(public_path('financialiq/site.webmanifest')))
    <link rel="manifest" href="{{ asset('financialiq/site.webmanifest') }}">
@endif
