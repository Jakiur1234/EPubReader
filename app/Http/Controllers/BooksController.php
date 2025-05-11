<?php
namespace App\Http\Controllers;

use App\Models\Book;
use App\Lib\EpubReader;
use lywzx\epub\EpubParser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use DOMDocument;

class BooksController
{
    public function index()
    {
        $books = Book::all();
        return view('books.index', compact('books'));
    }

    public function show($id)
    {
        $book = Book::findOrFail($id);
        $toc = [];
        $initial_path = '';

        try {
            $epub = new EpubParser($book->file_path);
            $epub->parse();
            $toc = $epub->getTOC();
            if (empty($toc)) {
                Log::warning('TOC is empty after parsing', [
                    'book_id' => $id,
                    'file' => $book->file_path,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse EPUB for TOC', [
                'book_id' => $id,
                'file' => $book->file_path,
                'error' => $e->getMessage(),
            ]);
            $toc = $this->generateFallbackTOC($book);
        }

        $token = Str::random(32);
        Session::put('epub_token_' . $id, $token);

        try {
            $reader = new EpubReader($book->file_path);
            $baseDir = $reader->getBaseDir(); // Get baseDir (e.g., "GoogleDoc")
            $opfContent = $reader->getFileContent('content.opf') ?: $reader->getFileContent('package.opf') ?: $reader->getFileContent('OEBPS/content.opf') ?: $reader->getFileContent('OEBPS/package.opf') ?: $reader->getFileContent('Text/package.opf');
            if ($opfContent) {
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadXML($opfContent);
                libxml_clear_errors();
                $spine = $dom->getElementsByTagName('spine')->item(0);
                if ($spine) {
                    $itemrefs = $spine->getElementsByTagName('itemref');
                    $items = [];
                    $manifest = $dom->getElementsByTagName('item');
                    foreach ($manifest as $item) {
                        $items[$item->getAttribute('id')] = $item->getAttribute('href');
                    }
                    foreach ($itemrefs as $itemref) {
                        $idref = $itemref->getAttribute('idref');
                        if (isset($items[$idref])) {
                            $href = $items[$idref];
                            $initial_path = $this->resolveRelativePath($baseDir, $href);
                            if ($reader->fileExists($initial_path)) {
                                break;
                            }
                        }
                    }
                }
            }
            if (!$initial_path) {
                $common_files = ['index.html', 'chapter1.xhtml', 'content.html', 'page_1.xhtml', 'doc_1.xhtml', 'section_1.xhtml', 'Untitleddocument.xhtml', 'Text/Untitleddocument.xhtml'];
                foreach ($common_files as $file) {
                    $try_path = $this->resolveRelativePath($baseDir, $file);
                    if ($full_path = $reader->fileExists($try_path)) {
                        $initial_path = $full_path;
                        if (empty($toc)) {
                            $toc[] = ['name' => 'Chapter 1', 'src' => $initial_path];
                        }
                        break;
                    }
                }
            }
            $reader->close();
        } catch (\Exception $e) {
            Log::error('Error finding valid initial path', [
                'book_id' => $id,
                'file' => $book->file_path,
                'error' => $e->getMessage(),
            ]);
        }

        if (!$initial_path && !empty($toc)) {
            $initial_path = $toc[0]['src'];
        }

        if (!$initial_path) {
            Log::warning('No valid initial path found, using fallback', [
                'book_id' => $id,
                'file' => $book->file_path,
            ]);
            $initial_path = $this->resolveRelativePath($baseDir, 'index.html');
        }

        $initial_file_token = Str::random(32);
        Session::put('file_token_' . $id . '_' . $initial_path, $initial_file_token);

        Log::debug('Book loaded', [
            'book_id' => $id,
            'toc_count' => count($toc),
            'initial_path' => $initial_path,
        ]);

        return view('epub', compact('book', 'toc', 'token', 'initial_path', 'initial_file_token'));
    }

    protected function generateFallbackTOC($book)
    {
        $toc = [];
        try {
            $reader = new EpubReader($book->file_path);
            $baseDir = $reader->getBaseDir();

            $ncxContent = $reader->getFileContent('OEBPS/toc.ncx') ?: $reader->getFileContent('toc.ncx') ?: $reader->getFileContent($baseDir . '/toc.ncx') ?: $reader->getFileContent('Text/toc.ncx');
            if ($ncxContent) {
                if (substr($ncxContent, 0, 3) === "\xEF\xBB\xBF") {
                    $ncxContent = substr($ncxContent, 3);
                }
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadXML($ncxContent);
                libxml_clear_errors();
                if ($dom->documentElement) {
                    $navPoints = $dom->getElementsByTagName('navPoint');
                    foreach ($navPoints as $index => $navPoint) {
                        $label = $navPoint->getElementsByTagName('navLabel')->item(0);
                        $text = $label ? trim($label->getElementsByTagName('text')->item(0)->textContent) : '';
                        $content = $navPoint->getElementsByTagName('content')->item(0);
                        $src = $content ? $content->getAttribute('src') : '';
                        if ($text && $src) {
                            $resolved_src = $this->resolveRelativePath($baseDir, $src);
                            $toc[] = [
                                'name' => $text ?: "Chapter " . ($index + 1),
                                'src' => $resolved_src,
                            ];
                        }
                    }
                    Log::debug('Generated fallback TOC from toc.ncx', [
                        'book_id' => $book->id,
                        'toc_count' => count($toc),
                    ]);
                }
            }

            if (empty($toc)) {
                $navContent = $reader->getFileContent('nav.xhtml') ?: $reader->getFileContent($baseDir . '/nav.xhtml') ?: $reader->getFileContent('OEBPS/nav.xhtml') ?: $reader->getFileContent('Text/nav.xhtml');
                if ($navContent) {
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    @$dom->loadHTML($navContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    libxml_clear_errors();
                    $navs = $dom->getElementsByTagName('nav');
                    foreach ($navs as $nav) {
                        if ($nav->getAttribute('epub:type') === 'toc') {
                            $items = $nav->getElementsByTagName('a');
                            foreach ($items as $index => $item) {
                                $text = trim($item->textContent);
                                $src = $item->getAttribute('href');
                                if ($text && $src) {
                                    $resolved_src = $this->resolveRelativePath($baseDir, $src);
                                    $toc[] = [
                                        'name' => $text ?: "Section " . ($index + 1),
                                        'src' => $resolved_src,
                                    ];
                                }
                            }
                            break;
                        }
                    }
                    Log::debug('Generated fallback TOC from nav.xhtml', [
                        'book_id' => $book->id,
                        'toc_count' => count($toc),
                    ]);
                }
            }

            if (empty($toc)) {
                $mainContent = $reader->getFileContent('Untitleddocument.xhtml') ?: $reader->getFileContent($baseDir . '/Untitleddocument.xhtml') ?: $reader->getFileContent('Text/Untitleddocument.xhtml');
                if ($mainContent) {
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    @$dom->loadHTML($mainContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    libxml_clear_errors();
                    $headings = $dom->getElementsByTagName('h1');
                    foreach ($headings as $index => $heading) {
                        $text = trim($heading->textContent);
                        $id = $heading->getAttribute('id');
                        if ($text && $id) {
                            $resolved_path = $this->resolveRelativePath($baseDir, 'Untitleddocument.xhtml');
                            $toc[] = [
                                'name' => $text,
                                'src' => $resolved_path . '#' . $id,
                            ];
                        }
                    }
                    Log::debug('Generated fallback TOC from Untitleddocument.xhtml headings', [
                        'book_id' => $book->id,
                        'toc_count' => count($toc),
                    ]);
                }
            }

            if (empty($toc)) {
                $opfContent = $reader->getFileContent('content.opf') ?: $reader->getFileContent('package.opf') ?: $reader->getFileContent('OEBPS/content.opf') ?: $reader->getFileContent('OEBPS/package.opf') ?: $reader->getFileContent('Text/package.opf');
                if ($opfContent) {
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    @$dom->loadXML($opfContent);
                    libxml_clear_errors();
                    $spine = $dom->getElementsByTagName('spine')->item(0);
                    if ($spine) {
                        $itemrefs = $spine->getElementsByTagName('itemref');
                        $items = [];
                        $manifest = $dom->getElementsByTagName('item');
                        foreach ($manifest as $item) {
                            $items[$item->getAttribute('id')] = $item->getAttribute('href');
                        }
                        foreach ($itemrefs as $index => $itemref) {
                            $idref = $itemref->getAttribute('idref');
                            if (isset($items[$idref]) && strpos($items[$idref], '.xhtml') !== false) {
                                $href = $items[$idref];
                                $content = $reader->getFileContent($href);
                                $title = "Chapter " . ($index + 1);
                                if ($content) {
                                    $chapterDom = new DOMDocument();
                                    @$chapterDom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                                    $h1 = $chapterDom->getElementsByTagName('h1')->item(0);
                                    $title = $h1 ? trim($h1->textContent) : basename($href, '.xhtml');
                                }
                                $resolved_href = $this->resolveRelativePath($baseDir, $href);
                                $toc[] = [
                                    'name' => $title,
                                    'src' => $resolved_href,
                                ];
                            }
                        }
                        Log::debug('Generated fallback TOC from content.opf or package.opf spine', [
                            'book_id' => $book->id,
                            'toc_count' => count($toc),
                        ]);
                    }
                }
            }

            $reader->close();
        } catch (\Exception $e) {
            Log::error('Failed to generate fallback TOC', [
                'book_id' => $book->id,
                'file' => $book->file_path,
                'error' => $e->getMessage(),
            ]);
        }

        if (empty($toc)) {
            $resolved_index = $this->resolveRelativePath($baseDir, 'index.html');
            $toc[] = ['name' => 'Introduction', 'src' => $resolved_index];
        }
        return $toc;
    }

    protected function getEpubLanguage($filePath)
    {
        try {
            $reader = new EpubReader($filePath);
            $opfContent = $reader->getFileContent('content.opf') ?: $reader->getFileContent('package.opf') ?: $reader->getFileContent('OEBPS/content.opf') ?: $reader->getFileContent('OEBPS/package.opf') ?: $reader->getFileContent('Text/package.opf');
            $reader->close();
            if ($opfContent) {
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadXML($opfContent);
                libxml_clear_errors();
                $languages = $dom->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'language');
                if ($languages->length > 0) {
                    return strtolower(trim($languages->item(0)->textContent));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to detect EPUB language', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }
        return 'en';
    }

    public function serveFile(Request $request, $id)
    {
        if (ob_get_length()) {
            ob_clean();
        }

        if (!Session::has('epub_token_' . $id) || $request->input('token') !== Session::get('epub_token_' . $id)) {
            Log::warning('Unauthorized access attempt', [
                'book_id' => $id,
                'token' => $request->input('token'),
                'path' => $request->input('path'),
            ]);
            abort(403, 'Unauthorized access');
        }

        $path = $request->input('path');
        $file_token = $request->input('file_token');
        $session_file_token_key = 'file_token_' . $id . '_' . $path;
        if (!Session::has($session_file_token_key) || $file_token !== Session::get($session_file_token_key)) {
            Log::warning('Invalid or reused file token', [
                'book_id' => $id,
                'path' => $path,
                'file_token' => $file_token,
            ]);
            abort(403, 'Invalid file token');
        }

        Session::forget($session_file_token_key);

        // Extract fragment (e.g., #h.8cqz9on9ecp6) from path
        $fragment = '';
        if (strpos($path, '#') !== false) {
            list($basePath, $fragment) = explode('#', $path, 2);
            $path = $basePath;
        }

        $book = Book::findOrFail($id);
        try {
            $reader = new EpubReader($book->file_path);
            $content = $reader->getFileContent($path);
            if ($content === null) {
                Log::error('EpubReader returned null content', [
                    'book_id' => $id,
                    'path' => $path,
                ]);
            }
            $reader->close();
        } catch (\Exception $e) {
            Log::error('Error accessing EPUB file', [
                'book_id' => $id,
                'file' => $book->file_path,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Error accessing EPUB file: ' . $e->getMessage());
        }

        if ($content === null) {
            Log::warning('Serving fallback content due to file not found', [
                'book_id' => $id,
                'path' => $path,
            ]);
            $content = '<h2>Content Not Found</h2><p>The requested content is not available. Please try another chapter.</p>';
            return response($content, 200)
                ->header('Content-Type', 'text/html')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        }

        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
            Log::debug('Stripped UTF-8 BOM from content', [
                'book_id' => $id,
                'path' => $path,
            ]);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeType($extension);

        Log::debug('Serving content snippet', [
            'book_id' => $id,
            'path' => $path,
            'fragment' => $fragment,
            'content_length' => strlen($content),
            'content_start' => substr($content, 0, 50),
        ]);

        if (in_array($extension, ['xhtml', 'html'])) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $isXhtml = ($extension === 'xhtml');
            if ($isXhtml) {
                $dom->loadXML($content, LIBXML_NOCDATA);
            } else {
                $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            }
            libxml_clear_errors();

            if (!$dom->documentElement) {
                Log::warning('Failed to parse content for processing', [
                    'book_id' => $id,
                    'path' => $path,
                ]);
                $content = '<h2>Content Not Found</h2><p>Failed to parse the requested content.</p>';
                $mimeType = 'text/html';
            } else {
                // Process images
                $images = $dom->getElementsByTagName('img');
                $basePath = dirname($path) === '.' ? '' : dirname($path);
                $imageCount = $images->length;

                Log::debug('Processing images in HTML', [
                    'book_id' => $id,
                    'path' => $path,
                    'image_count' => $imageCount,
                ]);

                foreach ($images as $img) {
                    $imgPath = $img->getAttribute('src');
                    if ($imgPath) {
                        $fullImgPath = $this->resolveRelativePath($basePath, $imgPath);
                        $imgToken = Str::random(32);
                        Session::put('file_token_' . $id . '_' . $fullImgPath, $imgToken);

                        Log::debug('Generated image token', [
                            'book_id' => $id,
                            'image_path' => $fullImgPath,
                            'image_token' => $imgToken,
                        ]);

                        $img->setAttribute('data-img-path', $fullImgPath);
                        $img->setAttribute('data-img-token', $imgToken);
                        $img->setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
                    }
                }

                // Add custom styles
                $language = $this->getEpubLanguage($book->file_path);
                $isBangla = ($language === 'bn');
                $fontFamily = $isBangla ? '"Noto Serif Bengali", serif' : '"Helvetica", sans-serif';
                // Set direction based on language (RTL for Arabic, Urdu, etc.; LTR otherwise)
                $direction = in_array($language, ['ar', 'ur', 'fa']) ? 'rtl' : 'ltr';
                $styleContent = "img { max-width: 100%; height: auto; } body { font-family: $fontFamily; font-size: 16px; line-height: 1.6; direction: $direction; }";
                $style = $dom->createElement('style', $styleContent);
                $head = $dom->getElementsByTagName('head')->item(0);
                if ($head) {
                    $head->appendChild($style);
                } else {
                    // Create a head element if it doesn't exist
                    $head = $dom->createElement('head');
                    $dom->documentElement->insertBefore($head, $dom->getElementsByTagName('body')->item(0));
                    $head->appendChild($style);
                }

                // Preserve the full HTML document
                if ($isXhtml) {
                    $content = $dom->saveXML();
                } else {
                    $content = $dom->saveHTML();
                }
            }
        }

        Log::debug('Final content length', [
            'content' => strlen($content),
        ]);

        return response($content, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Fragment', $fragment);
    }

    public function getLanguage(Request $request, $id)
    {
        if (!Session::has('epub_token_' . $id) || $request->input('token') !== Session::get('epub_token_' . $id)) {
            Log::warning('Unauthorized language fetch attempt', ['book_id' => $id]);
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $book = Book::findOrFail($id);
        $language = $this->getEpubLanguage($book->file_path);
        return response()->json(['success' => true, 'language' => $language]);
    }

    public function storeFileToken(Request $request, $id)
    {
        if (!Session::has('epub_token_' . $id)) {
            Log::warning('Unauthorized attempt to store file token', ['book_id' => $id]);
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $path = $request->input('path');
        $file_token = $request->input('file_token');
        Session::put('file_token_' . $id . '_' . $path, $file_token);

        Log::debug('Stored single-use file token', [
            'book_id' => $id,
            'path' => $path,
            'file_token' => $file_token,
        ]);

        return response()->json(['success' => true]);
    }

    private function getMimeType($extension)
    {
        $mimes = [
            'html' => 'text/html',
            'xhtml' => 'application/xhtml+xml',
            'css' => 'text/css',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];

        return $mimes[strtolower($extension)] ?? 'application/octet-stream';
    }

    private function resolveRelativePath($basePath, $relativePath)
    {
        $relativePath = ltrim($relativePath, '/');
        if (empty($basePath)) {
            return $relativePath;
        }
        $pathParts = array_filter(explode('/', $basePath . '/' . $relativePath), fn($part) => $part !== '.' && $part !== '');
        $stack = [];
        foreach ($pathParts as $part) {
            if ($part === '..') {
                if (!empty($stack)) {
                    array_pop($stack);
                }
            } else {
                $stack[] = $part;
            }
        }
        return implode('/', $stack);
    }
}