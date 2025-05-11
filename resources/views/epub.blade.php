<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern EPUB Reader</title>
    @vite(['resources/css/epub.css', 'resources/js/epub.js', 'resources/js/reader/reader.js'])
</head>

<body class="theme-light h-screen flex flex-col overflow-hidden">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="data-container" data-book-id="{{ $book->id }}" data-token="{{ $token }}"
        data-initial-path="{{ $initial_path }}" data-initial-file-token="{{ $initial_file_token }}"></div>
    <!-- Header -->
    <header class="reader-header bg-white dark:bg-gray-800 shadow-sm z-10">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <button id="menu-btn" class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                <i class="fas fa-bars text-xl"></i>
            </button>

            <h1 id="chapter-title" class="text-xl font-medium text-center text-gray-800 dark:text-gray-100"></h1>

            <div class="flex items-center space-x-4">
                <button id="settings-btn"
                    class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                    <i class="fas fa-cog text-xl"></i>
                </button>
                <button id="fullscreen-btn"
                    class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                    <i class="fas fa-expand text-xl"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex flex-1 overflow-hidden relative">
        <!-- Sidebar (Chapters) -->
        <aside id="sidebar"
            class="reader-sidebar sidebar-transition absolute left-0 top-0 h-full w-64 bg-white dark:bg-gray-800 shadow-lg z-20 transform -translate-x-full">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Table of Contents</h2>
            </div>
            <div class="overflow-y-auto h-full">
                <ul id="chapters-list" class="py-2">
                    @foreach ($toc as $item)
                        <a href="#" data-path="{{ $item['src'] }}">
                            <li
                                class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-gray-800 dark:text-gray-200">
                                {{ $item['name'] }}
                            </li>
                        </a>
                    @endforeach
                </ul>
            </div>
        </aside>

        <div id="settings-panel"
            class="reader-settings sidebar-transition absolute right-0 top-0 h-full w-64 bg-white dark:bg-gray-800 shadow-lg z-20 transform translate-x-full">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Settings</h2>
            </div>
            <div class="p-4 space-y-6">
                <div>
                    <h3 class="text-md font-medium mb-2 text-gray-800 dark:text-gray-200">Reading Mode</h3>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="reading-mode" value="single" class="h-4 w-4 text-blue-600">
                            <span class="text-gray-800 dark:text-gray-200">Single Page</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="reading-mode" value="dual" class="h-4 w-4 text-blue-600" disabled>
                            <span class="text-gray-800 dark:text-gray-200">Dual Page (Upcoming)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <h3 class="text-md font-medium mb-2 text-gray-800 dark:text-gray-200">Theme</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button data-theme="light"
                            class="theme-btn p-2 rounded border border-gray-300 bg-white text-gray-800">
                            Light
                        </button>
                        <button data-theme="sepia"
                            class="theme-btn p-2 rounded border border-gray-300 bg-amber-50 text-amber-900">
                            Sepia
                        </button>
                        <button data-theme="dark"
                            class="theme-btn p-2 rounded border border-gray-300 bg-gray-800 text-white">
                            Dark
                        </button>
                        <button data-theme="nature"
                            class="theme-btn p-2 rounded border border-gray-300 bg-green-50 text-green-900">
                            Nature
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="text-md font-medium mb-2 text-gray-800 dark:text-gray-200">Font Size</h3>
                    <div class="flex items-center space-x-4">
                        <button id="font-decrease"
                            class="p-1 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 w-8 h-8 flex items-center justify-center">
                            -
                        </button>
                        <span id="font-size-display" class="text-gray-800 dark:text-gray-200">16px</span>
                        <button id="font-increase"
                            class="p-1 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 w-8 h-8 flex items-center justify-center">
                            +
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reader Content -->
        <div id="reader-container" class="flex-1 flex overflow-auto page-transition">
            <!-- Single Page View -->
            <div id="single-page" class="flex-1 p-8 mx-auto max-w-4xl">
                <div class="prose dark:prose-invert max-w-none">
                    <div id="chapter-content">
                        <div class="book-content">
                            @if ($initial_path)
                                <!-- Content will be loaded here -->
                            @else
                                <p class="text-gray-600 text-center mt-10">No chapters available.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Navigation -->
    <footer class="reader-footer bg-white dark:bg-gray-800 shadow-sm z-10">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <button id="prev-btn"
                class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                <i class="fas fa-chevron-left mr-2"></i> Previous
            </button>

            <div class="text-sm text-gray-600 dark:text-gray-400">
                Chapter <span id="current-chapter"></span>
            </div>

            <button id="next-btn"
                class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                Next <i class="fas fa-chevron-right ml-2"></i>
            </button>
        </div>
    </footer>
</body>

</html>