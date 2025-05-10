<?php
namespace App\Lib;

use ZipArchive;
use DOMDocument;
use Illuminate\Support\Facades\Log;

class EpubReader
{
    protected $filePath;
    protected $zip;
    protected $opfPath;
    protected $baseDir;
    protected $isZipOpen;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
        $this->isZipOpen = false;
        $this->validateFile();
        $this->zip = new ZipArchive();
        $openResult = $this->zip->open($filePath, ZipArchive::CHECKCONS);
        if ($openResult !== true) {
            Log::error('Failed to open EPUB file', [
                'file' => $filePath,
                'error_code' => $openResult,
            ]);
            throw new \Exception('Failed to open EPUB file: Invalid or corrupted file');
        }
        $this->isZipOpen = true;
        $this->initialize();
    }

    protected function validateFile()
    {
        if (!file_exists($this->filePath)) {
            Log::error('EPUB file does not exist', ['file' => $this->filePath]);
            throw new \Exception('EPUB file does not exist');
        }
        if (!is_readable($this->filePath)) {
            Log::error('EPUB file is not readable', ['file' => $this->filePath]);
            throw new \Exception('EPUB file is not readable');
        }
        if (pathinfo($this->filePath, PATHINFO_EXTENSION) !== 'epub') {
            Log::warning('File may not be a valid EPUB', ['file' => $this->filePath]);
        }
    }

    protected function initialize()
    {
        $container = $this->zip->getFromName('META-INF/container.xml');
        if ($container === false) {
            Log::error('Invalid EPUB: container.xml not found', ['file' => $this->filePath]);
            $this->close();
            throw new \Exception('Invalid EPUB: container.xml not found');
        }

        $dom = new DOMDocument();
        @$dom->loadXML($container);
        $rootfiles = $dom->getElementsByTagName('rootfile');
        $this->opfPath = null;
        foreach ($rootfiles as $rootfile) {
            if ($rootfile->hasAttribute('full-path')) {
                $this->opfPath = $rootfile->getAttribute('full-path');
                break;
            }
        }

        if (!$this->opfPath) {
            Log::error('Invalid EPUB: OPF path not found', ['file' => $this->filePath]);
            $this->close();
            throw new \Exception('Invalid EPUB: OPF path not found');
        }

        $this->baseDir = dirname($this->opfPath) === '.' ? '' : dirname($this->opfPath);
        Log::debug('EPUB initialized', [
            'file' => $this->filePath,
            'opfPath' => $this->opfPath,
            'baseDir' => $this->baseDir,
        ]);
    }

    public function getBaseDir()
    {
        return $this->baseDir;
    }

    public function getFileContent($path)
    {
        if (!$this->isZipOpen) {
            Log::error('Attempted to access file with closed ZipArchive', [
                'file' => $this->filePath,
                'path' => $path,
            ]);
            throw new \Exception('ZipArchive is not open');
        }

        $pathsToTry = [$path];
        if ($this->baseDir && !str_starts_with($path, $this->baseDir . '/') && !str_starts_with($path, $this->baseDir)) {
            $pathsToTry[] = $this->baseDir . '/' . ltrim($path, '/');
            $pathsToTry[] = $this->baseDir . '/' . dirname($path) . '/' . basename($path);
            $pathsToTry[] = ltrim($path, '/');
            $pathsToTry[] = 'images/' . basename($path);
            $pathsToTry[] = 'OEBPS/images/' . basename($path); // For converted EPUBs
            $pathsToTry[] = 'Text/' . basename($path); // For some converted formats
            if (in_array(basename($path), ['toc.ncx', 'nav.xhtml'])) {
                $pathsToTry[] = 'OEBPS/' . basename($path);
                $pathsToTry[] = $this->baseDir . '/toc.ncx';
                $pathsToTry[] = $this->baseDir . '/nav.xhtml';
                $pathsToTry[] = 'Text/' . basename($path);
            }
            if (in_array(basename($path), ['content.opf'])) {
                $pathsToTry[] = $this->baseDir . '/package.opf';
                $pathsToTry[] = 'OEBPS/package.opf';
                $pathsToTry[] = 'Text/package.opf';
            }
        }

        foreach ($pathsToTry as $tryPath) {
            $fullPath = $this->normalizePath($tryPath);
            Log::debug('Attempting to serve file', [
                'file' => $this->filePath,
                'requested_path' => $path,
                'try_path' => $tryPath,
                'full_path' => $fullPath,
            ]);

            $content = $this->zip->getFromName($fullPath);
            if ($content !== false) {
                Log::info('File served successfully', [
                    'file' => $this->filePath,
                    'full_path' => $fullPath,
                ]);
                return $content;
            }
            Log::warning('File not found in EPUB', [
                'file' => $this->filePath,
                'full_path' => $fullPath,
            ]);
        }

        return null;
    }

    public function fileExists($path)
    {
        if (!$this->isZipOpen) {
            Log::error('Attempted to check file existence with closed ZipArchive', [
                'file' => $this->filePath,
                'path' => $path,
            ]);
            return false;
        }

        $pathsToTry = [$path];
        if ($this->baseDir && !str_starts_with($path, $this->baseDir . '/') && !str_starts_with($path, $this->baseDir)) {
            $pathsToTry[] = $this->baseDir . '/' . ltrim($path, '/');
            $pathsToTry[] = $this->baseDir . '/' . dirname($path) . '/' . basename($path);
            $pathsToTry[] = ltrim($path, '/');
            $pathsToTry[] = 'images/' . basename($path);
            $pathsToTry[] = 'OEBPS/images/' . basename($path);
            $pathsToTry[] = 'Text/' . basename($path);
            if (in_array(basename($path), ['toc.ncx', 'nav.xhtml'])) {
                $pathsToTry[] = 'OEBPS/' . basename($path);
                $pathsToTry[] = $this->baseDir . '/toc.ncx';
                $pathsToTry[] = $this->baseDir . '/nav.xhtml';
                $pathsToTry[] = 'Text/' . basename($path);
            }
            if (in_array(basename($path), ['content.opf'])) {
                $pathsToTry[] = $this->baseDir . '/package.opf';
                $pathsToTry[] = 'OEBPS/package.opf';
                $pathsToTry[] = 'Text/package.opf';
            }
        }

        foreach ($pathsToTry as $tryPath) {
            $fullPath = $this->normalizePath($tryPath);
            Log::debug('Checking file existence', [
                'file' => $this->filePath,
                'requested_path' => $path,
                'try_path' => $tryPath,
                'full_path' => $fullPath,
            ]);

            if ($this->zip->getFromName($fullPath) !== false) {
                return $fullPath;
            }
        }
        return false;
    }

    protected function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        $parts = array_filter(explode('/', $path), fn($part) => $part !== '.' && $part !== '');
        $stack = [];
        foreach ($parts as $part) {
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

    public function close()
    {
        if ($this->isZipOpen && $this->zip instanceof ZipArchive) {
            try {
                $this->zip->close();
                $this->isZipOpen = false;
                Log::debug('ZipArchive closed successfully', ['file' => $this->filePath]);
            } catch (\Exception $e) {
                Log::error('Failed to close ZipArchive', [
                    'file' => $this->filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}