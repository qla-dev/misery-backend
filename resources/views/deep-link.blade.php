<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Join Misery Meter room</title>
    <link rel="icon" href="{{ url('/favicon.ico') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ url('/favicon.ico') }}">
    <meta name="description" content="Join room {{ $code }} in Misery Meter.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Misery Meter">
    <meta property="og:title" content="Join my Misery Meter room">
    <meta property="og:description" content="Open Misery Meter and join room {{ $code }}.">
    <meta property="og:url" content="{{ request()->fullUrl() }}">
    <meta property="og:image" content="{{ url('/misery-og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Misery Meter">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Join my Misery Meter room">
    <meta name="twitter:description" content="Open Misery Meter and join room {{ $code }}.">
    <meta name="twitter:image" content="{{ url('/misery-og.png') }}">
    <style>
        body { align-items: center; background: #0a0a0a; color: #f5f5f5; display: flex; font-family: system-ui, sans-serif; justify-content: center; margin: 0; min-height: 100vh; padding: 24px; text-align: center; }
        main { max-width: 420px; width: 100%; }
        h1 { color: #facc15; font-size: 34px; margin-bottom: 8px; text-transform: uppercase; }
        p { color: #a3a3a3; line-height: 1.6; }
        code { color: #facc15; font-size: 22px; font-weight: 800; letter-spacing: 4px; }
        a { background: #facc15; border-radius: 10px; color: #0a0a0a; display: block; font-weight: 900; margin-top: 24px; padding: 16px; text-decoration: none; text-transform: uppercase; }
    </style>
</head>
<body>
<main>
    <h1>Misery Meter</h1>
    <p>Opening room</p>
    <code>{{ $code }}</code>
    <a href="miseryindex:///code/{{ $code }}">Open in the app</a>
    <p>If the app does not open, install or update Misery Meter and try this link again.</p>
</main>
<script>
    window.setTimeout(() => window.location.href = @json('miseryindex:///code/'.$code), 100);
</script>
</body>
</html>
