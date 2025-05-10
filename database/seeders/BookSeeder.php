<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Book::create([
            'title' => 'Sample Book',
            'author' => 'John Doe',
            'file_path' => storage_path('app/epubs/sample.epub'),
        ]);
        Book::create([
            'title' => 'Sample 2 Book',
            'author' => 'John Doe',
            'file_path' => storage_path('app/epubs/second.epub'),
        ]);
    }
}
