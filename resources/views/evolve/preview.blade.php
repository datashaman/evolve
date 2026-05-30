<!DOCTYPE html>
@php(config(['livewire.inject_assets' => false]))
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview</title>
    @livewireStyles
    <link rel="stylesheet" href="{{ route('evolve.styles') }}?v={{ filemtime(resource_path('evolve/manifest.json')) }}">
    <style>
        html, body { min-height: 100%; margin: 0; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            align-content: center;
            justify-content: center;
            padding: 40px;
            font-family: var(--font-sans, system-ui, sans-serif);
            color: var(--text, #18181b);
            background: #fafafa;
            background-image:
                linear-gradient(#0000000a 1px, transparent 1px),
                linear-gradient(90deg, #0000000a 1px, transparent 1px);
            background-size: 16px 16px;
        }
        body.preview-full {
            display: block;
            padding: 0;
            background: #fff;
        }
        body.preview-full > * {
            width: 100%;
            min-height: 100vh;
        }
        body > [wire\:id],
        body > [wire\:name] {
            align-self: center;
            justify-self: center;
        }
        body.preview-full > [wire\:id],
        body.preview-full > [wire\:name] {
            align-self: auto;
            justify-self: auto;
        }
        .empty-preview {
            padding: 16px 20px;
            border-radius: 8px;
            background: #fff;
            color: #71717a;
            box-shadow: 0 10px 40px rgba(15, 23, 42, .08);
        }
    </style>
</head>
<body class="{{ ($kind ?? '') === 'layout' || ($full_bleed ?? false) ? 'preview-full' : '' }}">
    {!! $content !!}
    @livewireScripts
</body>
</html>
