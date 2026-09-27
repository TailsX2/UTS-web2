<?php

namespace Database\Seeders;

use App\Models\Book;
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
        User::updateOrCreate(['email' => 'kepala@perpus.local'], [
            'name' => 'Kepala Perpustakaan',
            'password' => 'password',
            'role' => 'kepala',
        ]);

        User::updateOrCreate(['email' => 'staf@perpus.local'], [
            'name' => 'Staf Pustakawan',
            'password' => 'password',
            'role' => 'staf',
        ]);

        foreach ([
            ['9786020332956', 'Laskar Pelangi', 'Andrea Hirata', 5],
            ['9786020333175', 'Bumi', 'Tere Liye', 3],
            ['9786020652871', 'Laut Bercerita', 'Leila S. Chudori', 2],
            ['9786020633178', 'Filosofi Teras', 'Henry Manampiring', 0],
        ] as [$isbn, $title, $author, $stock]) {
            Book::updateOrCreate(['isbn' => $isbn], [
                'title' => $title,
                'author' => $author,
                'stock' => $stock,
            ]);
        }

        foreach ([
            ['AG-0001', 'Alya Pratama', '081234567801'],
            ['AG-0002', 'Bima Saputra', '081234567802'],
            ['AG-0003', 'Citra Lestari', '081234567803'],
        ] as [$memberNumber, $name, $phone]) {
            Member::updateOrCreate(['member_number' => $memberNumber], [
                'name' => $name,
                'phone' => $phone,
            ]);
        }
    }
}
