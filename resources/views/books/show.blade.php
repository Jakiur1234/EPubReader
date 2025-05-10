<x-layouts.epub-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="data-container" data-book-id="{{ $book->id }}" data-token="{{ $token }}"
        data-initial-path="{{ $initial_path }}" data-initial-file-token="{{ $initial_file_token }}"></div>

    <svg xmlns="http://www.w3.org/2000/svg" class="hidden">
        <symbol id="icon-book" viewBox="0 0 24 24">
            <path
                d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z" />
        </symbol>

        <symbol id="icon-menu" viewBox="0 0 24 24">
            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z" />
        </symbol>
    </svg>

    <div class="flex h-screen overflow-hidden">
        <!-- Side Navigation -->
        <aside
            class="w-80 bg-white shadow-lg z-50 transform transition-all duration-300 fixed md:relative -translate-x-full md:translate-x-0"
            id="sidebar">
            <div class="p-6 border-b border-gray-200">
                <h1 class="text-2xl font-bold text-gray-800">Book Chapters</h1>
            </div>
            <nav class="p-4 overflow-y-auto h-full">
                <ul class="space-y-2 chapter-item">
                    @foreach ($toc as $item)
                        <a href="#" data-path="{{ $item['src'] }}">
                            <li
                                class="chapter-item hover:bg-gray-100 rounded-lg p-4 cursor-pointer transition-colors flex items-center space-x-3">
                                {{ $item['name'] }}
                            </li>
                        </a>
                    @endforeach
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-auto bg-gray-50">
            <div class="sticky top-0 bg-white border-b border-gray-200 p-4 flex items-center space-x-4">
                <button id="menuButton" class="md:hidden text-gray-600 hover:text-gray-900">
                    <svg class="icon w-6 h-6">
                        <use xlink:href="#icon-menu" />
                    </svg>
                </button>
                <h2 class="text-xl font-semibold text-gray-800" id="chapterTitle"></h2>
            </div>

            <div class="w-full mx-auto p-3">
                <div class="prose prose-lg text-gray-700 bg-white rounded-lg shadow-sm p-4"
                    id="chapter-content" style="min-height: calc(100vh - 6rem); overflow-y: auto;">
                    @if ($initial_path)
                        <!-- Content will be loaded here -->
                    @else
                        <p class="text-gray-600 text-center mt-10">No chapters available.</p>
                    @endif
                </div>
            </div>
        </main>
    </div>
</x-layouts.epub-layout>