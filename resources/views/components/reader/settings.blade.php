<!-- Settings Panel -->
<div id="settings-panel"
    class="sidebar-transition absolute right-0 top-0 h-full w-64 bg-white dark:bg-gray-800 shadow-lg z-20 transform translate-x-full">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Settings</h2>
    </div>
    <div class="p-4 space-y-6">
        <div>
            <h3 class="text-md font-medium mb-2 text-gray-800 dark:text-gray-200">Reading Mode</h3>
            <div class="space-y-2">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="radio" name="reading-mode" value="single" checked class="h-4 w-4 text-blue-600">
                    <span class="text-gray-800 dark:text-gray-200">Single Page</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="radio" name="reading-mode" value="dual" class="h-4 w-4 text-blue-600">
                    <span class="text-gray-800 dark:text-gray-200">Dual Page</span>
                </label>
            </div>
        </div>

        <div>
            <h3 class="text-md font-medium mb-2 text-gray-800 dark:text-gray-200">Theme</h3>
            <div class="grid grid-cols-2 gap-2">
                <button data-theme="light" class="theme-btn p-2 rounded border border-gray-300 bg-white text-gray-800">
                    Light
                </button>
                <button data-theme="sepia"
                    class="theme-btn p-2 rounded border border-gray-300 bg-amber-50 text-amber-900">
                    Sepia
                </button>
                <button data-theme="dark" class="theme-btn p-2 rounded border border-gray-300 bg-gray-800 text-white">
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
                    A
                </button>
                <span id="font-size-display" class="text-gray-800 dark:text-gray-200">16px</span>
                <button id="font-increase"
                    class="p-1 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 w-8 h-8 flex items-center justify-center">
                    A
                </button>
            </div>
        </div>
    </div>
</div>