<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_seeder_uses_fictional_data_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('books', 4);
        $this->assertDatabaseCount('members', 3);
        $this->assertDatabaseCount('loans', 2);
        $this->assertDatabaseHas('books', [
            'isbn' => '9780000000101',
            'title' => 'Peta Hujan Kota Arunika',
            'author' => 'Mira Senandika',
            'stock' => 4,
        ]);
        $this->assertDatabaseHas('members', [
            'member_number' => 'AG-0001',
            'name' => 'Aksa Wiratama',
        ]);
        $this->assertDatabaseHas('loans', [
            'status' => 'dipinjam',
            'returned_at' => null,
        ]);

        $loan = Loan::query()->with(['book', 'member', 'user'])->where('status', 'dipinjam')->firstOrFail();
        $this->assertSame('Peta Hujan Kota Arunika', $loan->book->title);
        $this->assertSame('Aksa Wiratama', $loan->member->name);
        $this->assertSame('Staf Pustakawan', $loan->user->name);
    }

    public function test_guest_session_endpoint_returns_a_null_user(): void
    {
        $this->getJson('/api/session')->assertOk()->assertExactJson(['data' => null]);
    }

    public function test_request_cannot_borrow_a_book_with_zero_stock(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 0]);
        $member = Member::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/transaksi', [
                'book_id' => $book->id,
                'member_id' => $member->id,
                'loan_date' => today()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Gagal: Stok buku tidak tersedia.')
            ->assertJsonValidationErrors('book_id');

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 0]);
    }

    public function test_duplicate_isbn_is_rejected(): void
    {
        $user = User::factory()->create();
        Book::factory()->create(['isbn' => '9780000000001']);

        $this->actingAs($user)
            ->postJson('/api/buku', [
                'isbn' => '9780000000001',
                'title' => 'Buku Duplikat',
                'author' => 'Penulis',
                'stock' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');

        $this->assertDatabaseCount('books', 1);
    }

    public function test_staff_can_login_and_manage_books_and_members(): void
    {
        $user = User::factory()->create([
            'email' => 'staf@example.test',
            'password' => 'secret-password',
            'role' => 'staf',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('data.role', 'staf');

        $bookResponse = $this->postJson('/api/buku', [
            'isbn' => '9780000000012',
            'title' => 'Kumpulan Cerita',
            'author' => 'R. Penulis',
            'stock' => 3,
        ])->assertCreated();

        $this->putJson('/api/buku/'.$bookResponse->json('data.id'), [
            'isbn' => '9780000000012',
            'title' => 'Kumpulan Cerita Baru',
            'author' => 'R. Penulis',
            'stock' => 4,
        ])->assertOk()->assertJsonPath('data.stock', 4);

        $memberResponse = $this->postJson('/api/anggota', [
            'member_number' => 'AG-9001',
            'name' => 'Dara Contoh',
            'phone' => '081234567890',
        ])->assertCreated();

        $this->getJson('/api/anggota')
            ->assertOk()
            ->assertJsonFragment(['id' => $memberResponse->json('data.id'), 'name' => 'Dara Contoh']);
    }

    public function test_borrow_decrements_stock_and_return_restores_it_once(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 2]);
        $member = Member::factory()->create();

        $borrowResponse = $this->actingAs($user)->postJson('/api/transaksi', [
            'book_id' => $book->id,
            'member_id' => $member->id,
            'loan_date' => today()->toDateString(),
        ])->assertCreated();

        $loanId = $borrowResponse->json('data.id');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 1]);
        $this->assertDatabaseHas('loans', ['id' => $loanId, 'status' => 'dipinjam', 'returned_at' => null]);

        $this->patchJson("/api/transaksi/{$loanId}/kembali")
            ->assertOk()
            ->assertJsonPath('data.status', 'dikembalikan');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 2]);
        $this->assertDatabaseHas('loans', [
            'id' => $loanId,
            'status' => 'dikembalikan',
        ]);
        $this->assertSame(today()->toDateString(), Loan::findOrFail($loanId)->returned_at->toDateString());

        $this->patchJson("/api/transaksi/{$loanId}/kembali")->assertOk();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 2]);
    }

    public function test_staff_cannot_delete_library_records_or_loan_history(): void
    {
        $user = User::factory()->create(['role' => 'staf']);
        $book = Book::factory()->create();
        $member = Member::factory()->create();
        $loan = Loan::factory()->create([
            'book_id' => $book->id,
            'member_id' => $member->id,
            'user_id' => $user->id,
            'status' => 'dikembalikan',
            'returned_at' => today(),
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/buku/{$book->id}")
            ->assertForbidden();
        $this->deleteJson("/api/anggota/{$member->id}")->assertForbidden();
        $this->deleteJson("/api/transaksi/{$loan->id}")->assertForbidden();
    }
}
