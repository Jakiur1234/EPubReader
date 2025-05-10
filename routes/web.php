<?php
use App\Http\Controllers\BooksController;
use Illuminate\Support\Facades\Route;

Route::get('/books', [BooksController::class, 'index'])->name('books.index');
Route::get('/book/{id}', [BooksController::class, 'show'])->name('books.show');
Route::post('/book/{id}/file', [BooksController::class, 'serveFile'])->name('books.file');
Route::post('/book/{id}/store-file-token', [BooksController::class, 'storeFileToken'])->name('books.store-file-token');
Route::post('/book/{id}/language', [BooksController::class, 'getLanguage'])->name('books.language');