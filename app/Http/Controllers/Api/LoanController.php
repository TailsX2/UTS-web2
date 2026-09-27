<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Loan::query()
            ->with(['book:id,title,isbn', 'member:id,name,member_number', 'user:id,name'])
            ->latest('loan_date')
            ->latest('id')
            ->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'loan_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $loan = DB::transaction(function () use ($validated, $request): Loan {
            $stockUpdated = Book::query()
                ->whereKey($validated['book_id'])
                ->where('stock', '>', 0)
                ->decrement('stock');

            if ($stockUpdated !== 1) {
                throw ValidationException::withMessages([
                    'book_id' => 'Gagal: Stok buku tidak tersedia.',
                ]);
            }

            return Loan::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'status' => 'dipinjam',
            ]);
        });

        return response()->json(['data' => $loan->load(['book', 'member', 'user'])], 201);
    }

    public function returnBook(Loan $loan): JsonResponse
    {
        $returnedLoan = DB::transaction(function () use ($loan): Loan {
            $lockedLoan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($lockedLoan->status !== 'dikembalikan') {
                $lockedLoan->book()->increment('stock');
                $lockedLoan->update([
                    'status' => 'dikembalikan',
                    'returned_at' => today(),
                ]);
            }

            return $lockedLoan->load(['book', 'member', 'user']);
        });

        return response()->json(['data' => $returnedLoan]);
    }

    public function destroy(Request $request, Loan $loan): JsonResponse
    {
        abort_unless($request->user()->role === 'kepala', 403, 'Hanya Kepala Perpustakaan yang dapat menghapus riwayat transaksi.');

        if ($loan->status !== 'dikembalikan') {
            return response()->json(['message' => 'Transaksi aktif harus dikembalikan sebelum riwayat dihapus.'], 409);
        }

        $loan->delete();

        return response()->json(['message' => 'Riwayat transaksi berhasil dihapus.']);
    }
}
