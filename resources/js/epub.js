const elements = {
    menuBtn: document.getElementById('menu-btn'),
    settingsBtn: document.getElementById('settings-btn'),
    sidebar: document.getElementById('sidebar'),
    settingsPanel: document.getElementById('settings-panel'),
    singlePage: document.getElementById('single-page'),
    readerContainer: document.getElementById('reader-container'),
    themeBtns: document.querySelectorAll('.theme-btn'),
    chapterTitle: document.getElementById('chapter-title'),
    chaptersList: document.querySelectorAll('#chapters-list li'),
    fontIncreaseBtn: document.getElementById('font-increase'),
    fontDecreaseBtn: document.getElementById('font-decrease'),
    fontSizeDisplay: document.getElementById('font-size-display'),
    readingModeRadios: document.querySelectorAll('input[name="reading-mode"]'),
    fullscreenBtn: document.getElementById('fullscreen-btn'),
    prevBtn: document.getElementById('prev-btn'),
    nextBtn: document.getElementById('next-btn'),
    currentPageSpan: document.getElementById('current-chapter'),
    dataContainer: document.querySelector('.data-container')
};

const state = {
    sidebarOpen: false,
    settingsOpen: false,
    theme: localStorage.getItem('theme') || 'light',
    fontSize: parseInt(localStorage.getItem('fontSize')) || 16,
    readingMode: localStorage.getItem('readingMode') || 'single',
    currentChapter: parseInt(localStorage.getItem('currentChapter')) || 1,
    bookId: elements.dataContainer?.dataset.bookId || 'default',
    scrollPositions: JSON.parse(localStorage.getItem(`scrollPositions_${elements.dataContainer?.dataset.bookId}`)) || {}
};

function init() {
    loadSettings();
    setupEventListeners();
    loadChapter(state.currentChapter, true);
}

function setupEventListeners() {
    elements.menuBtn.addEventListener('click', toggleSidebar);
    elements.settingsBtn.addEventListener('click', toggleSettingsPanel);
    elements.fullscreenBtn.addEventListener('click', toggleFullscreen);
    elements.prevBtn.addEventListener('click', () => navigateChapter(-1));
    elements.nextBtn.addEventListener('click', () => navigateChapter(1));

    elements.themeBtns.forEach(btn => 
        btn.addEventListener('click', () => changeTheme(btn.dataset.theme))
    );

    elements.chaptersList.forEach((chapter, index) => 
        chapter.addEventListener('click', () => {
            loadChapter(index + 1);
            if (state.sidebarOpen) toggleSidebar();
        })
    );

    elements.fontIncreaseBtn.addEventListener('click', () => adjustFontSize(2));
    elements.fontDecreaseBtn.addEventListener('click', () => adjustFontSize(-2));

    elements.readingModeRadios.forEach(radio => 
        radio.addEventListener('change', e => updateReadingMode(e.target.value))
    );

    elements.readerContainer.addEventListener('scroll', debounce(saveScrollPosition, 100));
}

function loadSettings() {
    changeTheme(state.theme);
    updateFontSize();
    updateReadingMode(state.readingMode);
    updatePageDisplay();
}

function loadChapter(chapterNumber, isInitial = false) {
    state.currentChapter = chapterNumber;
    saveToLocalStorage('currentChapter', chapterNumber);

    const chapterEl = elements.chaptersList[chapterNumber - 1];
    elements.chapterTitle.textContent = `Chapter: ${chapterEl.textContent}`;
    updatePageDisplay();

    // Trigger chapter content load
    const scrollPos = state.scrollPositions[chapterNumber] || 0;
    if (isInitial) {
        setTimeout(() => {
            console.log(`Triggering chapter ${chapterNumber} load`);
            chapterEl.click();
        }, 100); // Increased delay for reliability
        // Wait for content to render before scrolling
        waitForContentLoad(elements.readerContainer, () => {
            console.log(`Scrolling to position ${scrollPos} for chapter ${chapterNumber}`);
            elements.readerContainer.scrollTo(0, scrollPos);
        });
    } else {
        chapterEl.click();
        elements.readerContainer.scrollTo(0, scrollPos);
    }
}

function waitForContentLoad(container, callback) {
    let attempts = 0;
    const maxAttempts = 100; // Increased for more patience
    let lastHeight = 0;
    const checkContent = () => {
        const currentHeight = container.scrollHeight;
        console.log(`Checking content: attempt ${attempts}, height ${currentHeight}`);
        // Check if content is loaded and height is stable
        if ((currentHeight > 0 && currentHeight === lastHeight) || attempts >= maxAttempts) {
            callback();
        } else {
            lastHeight = currentHeight;
            attempts++;
            requestAnimationFrame(checkContent);
        }
    };
    requestAnimationFrame(checkContent);
}

function saveScrollPosition() {
    state.scrollPositions[state.currentChapter] = elements.readerContainer.scrollTop;
    saveToLocalStorage(`scrollPositions_${state.bookId}`, JSON.stringify(state.scrollPositions));
}

function updateReadingMode(mode = state.readingMode) {
    state.readingMode = mode;
    elements.singlePage.classList.toggle('hidden', mode !== 'single');
    elements.readerContainer.classList.toggle('overflow-hidden', mode !== 'single');
    saveToLocalStorage('readingMode', mode);
    document.querySelector(`input[name="reading-mode"][value="${mode}"]`).checked = true;
}

function updateFontSize() {
    document.querySelectorAll('.prose').forEach(el => {
        el.style.fontSize = `${state.fontSize}px`;
    });
    elements.fontSizeDisplay.textContent = `${state.fontSize}px`;
}

function updatePageDisplay() {
    elements.currentPageSpan.textContent = `${state.currentChapter} of ${elements.chaptersList.length}`;
}

function toggleSidebar() {
    state.sidebarOpen = !state.sidebarOpen;
    elements.sidebar.classList.toggle('-translate-x-full', !state.sidebarOpen);
    if (state.sidebarOpen && state.settingsOpen) toggleSettingsPanel();
}

function toggleSettingsPanel() {
    state.settingsOpen = !state.settingsOpen;
    elements.settingsPanel.classList.toggle('translate-x-full', !state.settingsOpen);
    if (state.settingsOpen && state.sidebarOpen) toggleSidebar();
}

function changeTheme(theme) {
    document.body.classList.remove(`theme-${state.theme}`);
    document.body.classList.add(`theme-${theme}`);
    
    elements.themeBtns.forEach(btn => {
        btn.classList.toggle('ring-2', btn.dataset.theme === theme);
        btn.classList.toggle('ring-blue-500', btn.dataset.theme === theme);
    });

    state.theme = theme;
    saveToLocalStorage('theme', theme);
}

function navigateChapter(direction) {
    const newChapter = state.currentChapter + direction;
    if (newChapter >= 1 && newChapter <= elements.chaptersList.length) {
        loadChapter(newChapter);
    }
}

function adjustFontSize(change) {
    const newSize = state.fontSize + change;
    if (newSize >= 12 && newSize <= 24) {
        state.fontSize = newSize;
        updateFontSize();
        saveToLocalStorage('fontSize', newSize);
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => 
            console.error(`Error attempting to enable fullscreen: ${err.message}`)
        );
    } else if (document.exitFullscreen) {
        document.exitFullscreen();
    }
}

function saveToLocalStorage(key, value) {
    localStorage.setItem(key, value);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

init();