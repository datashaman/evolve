<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview</title>
    <link rel="stylesheet" href="{{ route('tokens') }}">
    @livewireStyles
    <style>
        html, body { min-height: 100%; margin: 0; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 40px;
            font-family: var(--font-sans, system-ui, sans-serif);
            color: var(--text, #18181b);
            background: #fafafa;
            background-image:
                linear-gradient(#0000000a 1px, transparent 1px),
                linear-gradient(90deg, #0000000a 1px, transparent 1px);
            background-size: 16px 16px;
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
<body>
    {!! $content !!}
    @livewireScripts
</body>
</html>
