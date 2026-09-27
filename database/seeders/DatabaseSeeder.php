<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $headLibrarian = User::updateOrCreate(['email' => 'kepala@perpus.local'], [
            'name' => 'Kepala Perpustakaan',
            'password' => 'password',
            'role' => 'kepala',
        ]);

        $staffLibrarian = User::updateOrCreate(['email' => 'staf@perpus.local'], [
            'name' => 'Staf Pustakawan',
            'password' => 'password',
            'role' => 'staf',
        ]);

        $books = [];

        foreach ([
            ['9786020332956', '9780000000101', 'Peta Hujan Kota Arunika', 'Mira Senandika', 4],
            ['9786020333175', '9780000000102', 'Atlas Bunyi Awan', 'Banyu Pradipta', 3],
            ['9786020652871', '9780000000103', 'Rumah di Ujung Musim', 'Tara Wening', 2],
            ['9786020633178', '9780000000104', 'Kode Rahasia Perpustakaan', 'Nara Pijar', 0],
        ] as [$legacyIsbn, $isbn, $title, $author, $stock]) {
            $book = Book::query()->where('isbn', $legacyIsbn)->first()
                ?? Book::query()->where('isbn', $isbn)->first()
                ?? new Book;

            $book->fill([
                'isbn' => $isbn,
                'title' => $title,
                'author' => $author,
                'stock' => $stock,
            ])->save();
            $books[$isbn] = $book;
        }

        $members = [];

        foreach ([
            ['AG-0001', 'Aksa Wiratama', '0800-0000-0001'],
            ['AG-0002', 'Nadine Larasati', '0800-0000-0002'],
            ['AG-0003', 'Jati Mahendra', '0800-0000-0003'],
        ] as [$memberNumber, $name, $phone]) {
            $members[$memberNumber] = Member::updateOrCreate(['member_number' => $memberNumber], [
                'name' => $name,
                'phone' => $phone,
            ]);
        }

        $activeLoan = Loan::query()
            ->where('book_id', $books['9780000000101']->id)
            ->where('member_id', $members['AG-0001']->id)
            ->where('user_id', $staffLibrarian->id)
            ->whereDate('loan_date', '2026-09-18')
            ->first() ?? new Loan;
        $activeLoan->fill([
            'book_id' => $books['9780000000101']->id,
            'member_id' => $members['AG-0001']->id,
            'user_id' => $staffLibrarian->id,
            'loan_date' => '2026-09-18',
            'returned_at' => null,
            'status' => 'dipinjam',
        ])->save();

        $returnedLoan = Loan::query()
            ->where('book_id', $books['9780000000102']->id)
            ->where('member_id', $members['AG-0002']->id)
            ->where('user_id', $headLibrarian->id)
            ->whereDate('loan_date', '2026-09-15')
            ->first() ?? new Loan;
        $returnedLoan->fill([
            'book_id' => $books['9780000000102']->id,
            'member_id' => $members['AG-0002']->id,
            'user_id' => $headLibrarian->id,
            'loan_date' => '2026-09-15',
            'returned_at' => '2026-09-17',
            'status' => 'dikembalikan',
        ])->save();
    }
}
