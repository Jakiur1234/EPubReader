document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

document.addEventListener('dragstart', function(e) {
    e.preventDefault();
});

document.addEventListener('DOMContentLoaded', function() {
    const page = document.querySelector('.data-container');
    const bookId = page.dataset.bookId;
    const token = page.dataset.token;
    const initialPath = page.dataset.initialPath;
    const contentDiv = document.getElementById('chapter-content');
    let currentBasePath = null; // Track the currently loaded file

    // Inject book-like styles into the document head
    const bookStyles = document.createElement('style');
    bookStyles.textContent = `
        #chapter-content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            height: 80vh;
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .book-container {
            width: 100%;
            height: 100%;
            background: #fff;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        .book-content {
            height: 100%;
            padding: 15px;
            font-family: "Georgia", serif;
            font-size: 16px;
            line-height: 1.8;
            color: #333;
            text-align: justify;
            background: #fefefe;
            column-gap: 20px;
            column-fill: auto;
            column-count: 2;
            column-rule: 1px solid #ddd;
        }
        .book-content * {
            display: block; /* Override potential display: none */
            visibility: visible; /* Ensure visibility */
        }
        .book-content h1, .book-content h2, .book-content h3 {
            font-family: "Georgia", serif;
            color: #222;
            margin-bottom: 15px;
            break-after: avoid-column;
        }
        .book-content p {
            margin-bottom: 15px;
            break-inside: avoid-column;
        }
        .book-content img {
            max-width: 100%;
            height: auto;
            margin: 10px 0;
            break-inside: avoid-column;
        }
        .book-container::-webkit-scrollbar {
            width: 8px;
        }
        .book-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .book-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        /* Mobile: One page */
        @media (max-width: 767px) {
            .book-content {
                column-count: 1;
                font-size: 14px; /* Smaller font for mobile */
            }
        }
    `;
    document.head.appendChild(bookStyles);

    function loadContent(path, retries = 2) {
        // Extract fragment (e.g., #h.8cqz9on9ecp6) from path
        let basePath = path;
        let fragment = '';
        if (path.includes('#')) {
            [basePath, fragment] = path.split('#', 2);
        }

        // If the base file is already loaded, just scroll to the fragment
        if (currentBasePath === basePath && contentDiv.innerHTML) {
            console.log('Base file already loaded, scrolling to fragment:', fragment);
            if (fragment) {
                requestAnimationFrame(() => {
                    const element = document.getElementById(fragment);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth' });
                        console.log('Scrolled to fragment:', fragment);
                    } else {
                        console.warn('Fragment not found:', fragment);
                    }
                });
            }
            return;
        }

        // Save scroll position of the current chapter before loading a new one
        if (currentBasePath) {
            const bookContainer = contentDiv.querySelector('.book-container');
            if (bookContainer) {
                localStorage.setItem(`scrollPosition_${bookId}_${currentBasePath}`, bookContainer.scrollTop);
            }
        }

        const fileToken = Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2);
        fetch(`/book/${bookId}/store-file-token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ path: basePath, file_token: fileToken })
        })
        .then(response => {
            console.log('Store file token response:', response.status);
            if (!response.ok) {
                throw new Error('Failed to store file token: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                console.error('Failed to store file token:', data.error);
                if (retries > 0) {
                    loadContent(path, retries - 1);
                } else {
                    contentDiv.innerHTML = '<h2>Error</h2><p>Unable to load content due to token issue.</p>';
                }
                return;
            }

            fetch(`/book/${bookId}/file`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ token: token, path: basePath, file_token: fileToken })
            })
            .then(response => {
                console.log('Serve file response:', response.status, response.headers.get('Content-Type'));
                if (!response.ok) {
                    throw new Error('Failed to load content: ' + response.status);
                }
                return response.text();
            })
            .then(html => {
                console.log('Received HTML length:', html.length, 'Sample:', html.substring(0, 50));
                // Parse HTML with DOMParser to handle XHTML correctly
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'application/xhtml+xml');
                const parseError = doc.querySelector('parsererror');
                if (parseError) {
                    console.error('HTML parsing error:', parseError.textContent);
                    throw new Error('Invalid XHTML content');
                }

                // Check for error message
                if (html.includes('<h2>Content Not Found</h2>')) {
                    throw new Error('Server returned Content Not Found');
                }

                // Extract body content
                let bodyContent = '';
                const body = doc.querySelector('body');
                if (body) {
                    bodyContent = body.innerHTML;
                } else {
                    // Fallback: Use all content after <head>, excluding <style> and <title>
                    const htmlElement = doc.querySelector('html');
                    if (htmlElement) {
                        const tempDiv = document.createElement('div');
                        tempDiv.append(...htmlElement.childNodes);
                        tempDiv.querySelectorAll('head, style, title').forEach(el => el.remove());
                        bodyContent = tempDiv.innerHTML;
                    } else {
                        bodyContent = html; // Last resort
                    }
                }

                if (!bodyContent || bodyContent.trim() === '') {
                    throw new Error('No valid body content found in response');
                }

                // Extract and inject styles
                const styles = doc.querySelectorAll('style');
                Array.from(styles).forEach(style => {
                    const styleElement = document.createElement('style');
                    styleElement.textContent = style.textContent;
                    document.head.appendChild(styleElement);
                });

                // Process <link> tags for external CSS
                const links = doc.querySelectorAll('link[rel="stylesheet"]');
                Array.from(links).forEach(link => {
                    let cssPath = link.getAttribute('href');
                    if (!cssPath) return;
                    // Resolve relative paths based on the current basePath
                    if (!cssPath.startsWith('http') && basePath.includes('/')) {
                        const baseDir = basePath.substring(0, basePath.lastIndexOf('/'));
                        cssPath = `${baseDir}/${cssPath}`.replace(/\/+/g, '/');
                    }
                    const cssFileToken = Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2);
                    fetch(`/book/${bookId}/store-file-token`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ path: cssPath, file_token: cssFileToken })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            fetch(`/book/${bookId}/file`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ token: token, path: cssPath, file_token: cssFileToken })
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Failed to load CSS: ' + response.status);
                                }
                                return response.text();
                            })
                            .then(css => {
                                const styleElement = document.createElement('style');
                                styleElement.textContent = css;
                                document.head.appendChild(styleElement);
                                console.log('Loaded CSS:', cssPath);
                            })
                            .catch(error => console.error('Failed to load CSS:', cssPath, error));
                        }
                    })
                    .catch(error => console.error('Failed to store CSS file token:', cssPath, error));
                });

                // Render content
                contentDiv.innerHTML = `
                    <div class="book-container">
                        <div class="book-content">${bodyContent}</div>
                    </div>
                `;

                currentBasePath = basePath;

                // Fetch EPUB language for dynamic styling
                fetch(`/book/${bookId}/language`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ token: token })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.language) {
                        const isBangla = data.language === 'bn';
                        const fontFamily = isBangla ? '"Noto Serif Bengali", serif' : '"Georgia", serif';
                        const direction = isBangla ? 'rtl' : 'ltr';
                        const defaultStyle = document.createElement('style');
                        defaultStyle.textContent = `
                            .book-content {
                                font-family: ${fontFamily};
                                direction: ${direction};
                            }
                        `;
                        document.head.appendChild(defaultStyle);
                    }
                })
                .catch(error => console.warn('Failed to fetch EPUB language:', error));

                // Ensure DOM is updated before proceeding
                requestAnimationFrame(() => {
                    loadImages();

                    // Restore scroll position
                    const bookContainer = contentDiv.querySelector('.book-container');
                    if (bookContainer) {
                        const savedScroll = localStorage.getItem(`scrollPosition_${bookId}_${basePath}`);
                        if (savedScroll) {
                            bookContainer.scrollTop = parseInt(savedScroll, 10);
                            console.log('Restored scroll position:', savedScroll);
                        }
                    }

                    // Scroll to fragment if present
                    if (fragment) {
                        requestAnimationFrame(() => {
                            const element = document.getElementById(fragment);
                            if (element) {
                                element.scrollIntoView({ behavior: 'smooth' });
                                console.log('Scrolled to fragment:', fragment);
                            } else {
                                console.warn('Fragment not found:', fragment);
                            }
                        });
                    }
                });
            })
            .catch(error => {
                console.error('Error loading content:', path, error.message);
                if (retries > 0) {
                    // Use TOC or fallback paths only if initial fetch fails
                    const fallbackPaths = document.querySelectorAll('a[data-path]');
                    let nextPath = null;
                    if (fallbackPaths.length > 0) {
                        const tocPaths = Array.from(fallbackPaths).map(a => a.getAttribute('data-path'));
                        const currentIndex = tocPaths.indexOf(basePath);
                        nextPath = tocPaths[(currentIndex + 1) % tocPaths.length] || tocPaths[0];
                    }
                    if (nextPath && nextPath !== basePath) {
                        console.log('Retrying with next TOC path:', nextPath);
                        loadContent(nextPath, retries - 1);
                    } else {
                        contentDiv.innerHTML = '<h2>Content Not Found</h2><p>The requested content is not available. Please try another chapter.</p>';
                    }
                } else {
                    contentDiv.innerHTML = '<h2>Content Not Found</h2><p>The requested content is not available. Please try another chapter.</p>';
                }
            });
        })
        .catch(error => {
            console.error('Error storing file token:', error);
            if (retries > 0) {
                loadContent(path, retries - 1);
            } else {
                contentDiv.innerHTML = '<h2>Error</h2><p>Unable to load content due to token issue.</p>';
            }
        });
    }

    function loadImages() {
        if (!contentDiv || !contentDiv.innerHTML) {
            console.warn('Cannot load images: contentDiv is empty or not found');
            return;
        }

        const images = contentDiv.getElementsByTagName('img');
        if (!images || images.length === 0) {
            console.log('No images found to load');
            return;
        }

        Array.from(images).forEach(img => {
            if (!img) {
                console.warn('Invalid image element encountered');
                return;
            }

            const imgPath = img.getAttribute('data-img-path');
            const imgToken = img.getAttribute('data-img-token');
            const originalSrc = img.getAttribute('src') || '';

            if (imgPath && imgToken) {
                fetch(`/book/${bookId}/file`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ token: token, path: imgPath, file_token: imgToken })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load image: ' + response.status);
                    }
                    return response.blob();
                })
                .then(blob => {
                    const url = URL.createObjectURL(blob);
                    img.src = url;
                    setTimeout(() => URL.revokeObjectURL(url), 5000);
                })
                .catch(error => {
                    console.error('Error loading image:', imgPath, error);
                    img.alt = 'Image failed to load';
                    if (originalSrc) {
                        img.src = originalSrc;
                    }
                });
            } else {
                console.warn('Image missing data attributes:', { path: imgPath, token: imgToken, src: originalSrc });
            }
        });
    }

    document.querySelectorAll('a[data-path]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const path = this.getAttribute('data-path');
            console.log('Loading TOC path:', path);
            loadContent(path);
        });
    });

    if (initialPath) {
        console.log('Loading initial path:', initialPath);
        loadContent(initialPath);
    }
});