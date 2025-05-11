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
            const bookContent = contentDiv.querySelector('.book-content');
            if (bookContent) {
                localStorage.setItem(`scrollPosition_${bookId}_${currentBasePath}`, bookContent.scrollTop);
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
                // Parse HTML with DOMParser
                const parser = new DOMParser();
                let doc = parser.parseFromString(html, 'application/xhtml+xml');
                let parseError = doc.querySelector('parsererror');

                // Fallback to text/html if XHTML parsing fails
                if (parseError) {
                    console.warn('XHTML parsing failed:', parseError.textContent);
                    doc = parser.parseFromString(html, 'text/html');
                    parseError = doc.querySelector('parsererror');
                    if (parseError) {
                        console.error('HTML parsing error:', parseError.textContent);
                        throw new Error('Invalid content format');
                    }
                }

                // Check for error message
                if (html.includes('<h2>Content Not Found</h2>')) {
                    throw new Error('Server returned Content Not Found');
                }

                // Extract body content
                let bodyContent = '';
                let body = doc.querySelector('body');
                if (body) {
                    bodyContent = body.innerHTML;
                    console.log('Body content length:', bodyContent.length, 'Sample:', bodyContent.substring(0, 50));
                } else {
                    console.warn('No <body> tag found, attempting fallback');
                    const htmlElement = doc.querySelector('html');
                    if (htmlElement) {
                        const tempDiv = document.createElement('div');
                        tempDiv.append(...htmlElement.childNodes);
                        tempDiv.querySelectorAll('head, style, title').forEach(el => el.remove());
                        bodyContent = tempDiv.innerHTML;
                        console.log('Fallback content length:', bodyContent.length, 'Sample:', bodyContent.substring(0, 50));
                    } else {
                        bodyContent = html;
                        console.log('Last resort content length:', bodyContent.length, 'Sample:', bodyContent.substring(0, 50));
                    }
                }

                // Relax empty content check
                if (!bodyContent) {
                    console.error('No body content extracted');
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

                // Ensure DOM is updated before proceeding
                requestAnimationFrame(() => {
                    loadImages();

                    // Restore scroll position
                    const bookContent = contentDiv.querySelector('.book-content');
                    if (bookContent) {
                        const savedScroll = localStorage.getItem(`scrollPosition_${bookId}_${basePath}`);
                        if (savedScroll) {
                            bookContent.scrollTop = parseInt(savedScroll, 10);
                            console.log('Restored scroll position:', bookContent.scrollTop);
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
                    const fallbackPaths = document.querySelectorAll('a[data-path]');
                    let nextPath = null;
                    if (fallbackPaths.length > 0) {
                        const tocPaths = Array.from(fallbackPaths).map(a => a.getAttribute('data-path'));
                        const currentIndex = tocPaths.indexOf(basePath);
                        nextPath = tocPaths[(currentIndex + 1) % tocPaths.length] || tocPaths[0];
                    }
                    if (nextPath && nextPath !== basePath) {
                        console.log('Retrying with next TOC path:', nextPath);
                        loadContent(path, retries - 1);
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