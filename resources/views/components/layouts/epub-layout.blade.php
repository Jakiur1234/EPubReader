<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'EPUB Reader' }}</title>
    <!-- Tailwind CSS CDN -->
    @vite(['resources/js/reader/reader.js', 'resources/js/show.js', 'resources/css/app.css'])
    <!-- Custom Kindle-inspired styles -->
    <style>
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            font-family: 'Helvetica', 'Noto Serif Bengali', sans-serif;
            background-color: #F7F7F7;
        }
        iframe {
            pointer-events: auto;
        }
        .toolbar {
            transition: opacity 0.3s ease;
        }
        .toolbar-hidden {
            opacity: 0;
            pointer-events: none;
        }
        .toc-sidebar, .toc-mobile {
            transition: transform 0.3s ease;
        }
        .toc-mobile-hidden {
            transform: translateX(-100%);
        }
        .toc-sidebar ul li a, .toc-mobile ul li a {
            font-family: 'Noto Serif Bengali', 'Helvetica', sans-serif;
        }
        .settings-panel {
            transition: transform 0.3s ease;
        }
        .settings-panel-hidden {
            transform: translateX(100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    {{ $slot }}
</body>
</html>