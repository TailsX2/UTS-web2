<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Book::query()->orderBy('title')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $book = Book::create($request->validate([
            'isbn' => ['required', 'string', 'max:20', 'unique:books,isbn'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
        ], ['isbn.unique' => 'ISBN sudah terdaftar.']));

        return response()->json(['data' => $book], 201);
    }

    public function update(Request $request, Book $book): JsonResponse
    {
        $book->update($request->validate([
            'isbn' => ['required', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($book)],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
        ], ['isbn.unique' => 'ISBN sudah digunakan oleh buku lain.']));

        return response()->json(['data' => $book->refresh()]);
    }

    public function destroy(Request $request, Book $book): JsonResponse
    {
        abort_unless($request->user()->role === 'kepala', 403, 'Hanya Kepala Perpustakaan yang dapat menghapus data buku.');

        if ($book->loans()->exists()) {
            return response()->json(['message' => 'Buku yang memiliki riwayat transaksi tidak dapat dihapus.'], 409);
        }

        $book->delete();

        return response()->json(['message' => 'Buku berhasil dihapus.']);
    }
}
