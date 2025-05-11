// DOM Elements
const menuBtn = document.getElementById('menu-btn');
const settingsBtn = document.getElementById('settings-btn');
const sidebar = document.getElementById('sidebar');
const settingsPanel = document.getElementById('settings-panel');
const singlePage = document.getElementById('single-page');
const dualPage = document.getElementById('dual-page');
const readerContainer = document.getElementById('reader-container');
const themeBtns = document.querySelectorAll('.theme-btn');
const chapterTitle = document.getElementById('chapter-title');
const chaptersList = document.getElementById('chapters-list').querySelectorAll('li');
const fontIncreaseBtn = document.getElementById('font-increase');
const fontDecreaseBtn = document.getElementById('font-decrease');
const fontSizeDisplay = document.getElementById('font-size-display');
const readingModeRadios = document.querySelectorAll('input[name="reading-mode"]');
const fullscreenBtn = document.getElementById('fullscreen-btn');
const prevBtn = document.getElementById('prev-btn');
const nextBtn = document.getElementById('next-btn');
const currentPageSpan = document.getElementById('current-page');
const totalPagesSpan = document.getElementById('total-pages');

// State with localStorage defaults
let state = {
    sidebarOpen: false,
    settingsOpen: false,
    theme: localStorage.getItem('theme') || 'light',
    fontSize: parseInt(localStorage.getItem('fontSize')) || 16,
    readingMode: localStorage.getItem('readingMode') || 'single',
    currentChapter: parseInt(localStorage.getItem('currentChapter')) || 1,
    currentPage: parseInt(localStorage.getItem('currentPage')) || 1,
    totalPages: 10
};

// Initialize
function init() {
    // Load saved settings
    loadTheme();
    loadFontSize();
    loadReadingMode();
    loadChapter(state.currentChapter);
    updatePageDisplay();

    // Set total pages (would normally come from EPUB)
    totalPagesSpan.textContent = state.totalPages;

    // Set up event listeners
    setupEventListeners();
}

function setupEventListeners() {
    menuBtn.addEventListener('click', toggleSidebar);
    settingsBtn.addEventListener('click', toggleSettingsPanel);
    fullscreenBtn.addEventListener('click', toggleFullscreen);
    prevBtn.addEventListener('click', goToPreviousPage);
    nextBtn.addEventListener('click', goToNextPage);

    themeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.dataset.theme;
            changeTheme(theme);
        });
    });

    chaptersList.forEach((chapter, index) => {
        chapter.addEventListener('click', () => {
            loadChapter(index + 1);
            if (state.sidebarOpen) toggleSidebar();
        });
    });

    fontIncreaseBtn.addEventListener('click', () => {
        if (state.fontSize < 24) {
            state.fontSize += 2;
            updateFontSize();
            saveToLocalStorage('fontSize', state.fontSize);
        }
    });

    fontDecreaseBtn.addEventListener('click', () => {
        if (state.fontSize > 12) {
            state.fontSize -= 2;
            updateFontSize();
            saveToLocalStorage('fontSize', state.fontSize);
        }
    });

    readingModeRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            state.readingMode = e.target.value;
            updateReadingMode();
            saveToLocalStorage('readingMode', state.readingMode);
        });
    });
}

// Load functions
function loadTheme() {
    changeTheme(state.theme);
    // Highlight the current theme button
    themeBtns.forEach(btn => {
        if (btn.dataset.theme === state.theme) {
            btn.classList.add('ring-2', 'ring-blue-500');
        } else {
            btn.classList.remove('ring-2', 'ring-blue-500');
        }
    });
}

function loadFontSize() {
    fontSizeDisplay.textContent = `${state.fontSize}px`;
    updateFontSize();
}

function loadReadingMode() {
    // Set the radio button
    document.querySelector(`input[name="reading-mode"][value="${state.readingMode}"]`).checked = true;
    updateReadingMode();
}

function loadChapter(chapterNumber) {
    state.currentChapter = chapterNumber;
    state.currentPage = 1; // Reset to first page when changing chapters
    saveToLocalStorage('currentChapter', chapterNumber);
    saveToLocalStorage('currentPage', 1);

    // Update UI
    updatePageDisplay();
    chapterTitle.textContent = `Chapter : ${chaptersList[chapterNumber - 1].textContent}`;

    // Scroll to top
    readerContainer.scrollTo(0, 0);

    // In a real app, this would load the chapter content
    console.log(`Loading chapter ${chapterNumber}`);
}

// Update functions
function updateReadingMode() {
    if (state.readingMode === 'single') {
        singlePage.classList.remove('hidden');
        dualPage.classList.add('hidden');
        readerContainer.classList.remove('overflow-hidden');
    } else {
        singlePage.classList.add('hidden');
        dualPage.classList.remove('hidden');
        readerContainer.classList.add('overflow-hidden');
    }
}

function updateFontSize() {
    const proseElements = document.querySelectorAll('.prose');
    proseElements.forEach(el => {
        el.style.fontSize = `${state.fontSize}px`;
    });
    fontSizeDisplay.textContent = `${state.fontSize}px`;
}

function updatePageDisplay() {
    currentPageSpan.textContent = state.currentPage;
}

// Toggle functions
function toggleSidebar() {
    state.sidebarOpen = !state.sidebarOpen;
    if (state.sidebarOpen) {
        sidebar.classList.remove('-translate-x-full');
        if (state.settingsOpen) toggleSettingsPanel();
    } else {
        sidebar.classList.add('-translate-x-full');
    }
}

function toggleSettingsPanel() {
    state.settingsOpen = !state.settingsOpen;
    if (state.settingsOpen) {
        settingsPanel.classList.remove('translate-x-full');
        if (state.sidebarOpen) toggleSidebar();
    } else {
        settingsPanel.classList.add('translate-x-full');
    }
}

// Change functions
function changeTheme(theme) {
    document.body.classList.remove(`theme-${state.theme}`);
    document.body.classList.add(`theme-${theme}`);

    // Update theme buttons
    themeBtns.forEach(btn => {
        if (btn.dataset.theme === theme) {
            btn.classList.add('ring-2', 'ring-blue-500');
        } else {
            btn.classList.remove('ring-2', 'ring-blue-500');
        }
    });

    state.theme = theme;
    saveToLocalStorage('theme', theme);
}

// Navigation functions
function goToPreviousPage() {
    if (state.currentPage > 1) {
        state.currentPage--;
        saveToLocalStorage('currentPage', state.currentPage);
        updatePageDisplay();
        // In a real app, you would load the previous page content
        readerContainer.scrollTo(0, 0);
    }
}

function goToNextPage() {
    if (state.currentPage < state.totalPages) {
        state.currentPage++;
        saveToLocalStorage('currentPage', state.currentPage);
        updatePageDisplay();
        // In a real app, you would load the next page content
        readerContainer.scrollTo(0, 0);
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.error(`Error attempting to enable fullscreen: ${err.message}`);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// LocalStorage helper
function saveToLocalStorage(key, value) {
    localStorage.setItem(key, value);
}

// Initialize the app
init();