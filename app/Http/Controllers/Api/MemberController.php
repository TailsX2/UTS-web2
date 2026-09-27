<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Member::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $member = Member::create($request->validate([
            'member_number' => ['required', 'string', 'max:30', 'unique:members,member_number'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ], ['member_number.unique' => 'Nomor anggota sudah terdaftar.']));

        return response()->json(['data' => $member], 201);
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $member->update($request->validate([
            'member_number' => ['required', 'string', 'max:30', Rule::unique('members', 'member_number')->ignore($member)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ], ['member_number.unique' => 'Nomor anggota sudah digunakan oleh anggota lain.']));

        return response()->json(['data' => $member->refresh()]);
    }

    public function destroy(Request $request, Member $member): JsonResponse
    {
        abort_unless($request->user()->role === 'kepala', 403, 'Hanya Kepala Perpustakaan yang dapat menghapus anggota.');

        if ($member->loans()->exists()) {
            return response()->json(['message' => 'Anggota yang memiliki riwayat transaksi tidak dapat dihapus.'], 409);
        }

        $member->delete();

        return response()->json(['message' => 'Anggota berhasil dihapus.']);
    }
}
