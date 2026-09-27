<script setup>
import { computed, onMounted, ref } from 'vue';

const user = ref(null);
const page = ref('ringkasan');
const books = ref([]);
const members = ref([]);
const loans = ref([]);
const search = ref('');
const loanFilter = ref('semua');
const dialog = ref(null);
const toast = ref('');
const toastError = ref(false);
const busy = ref(false);
const loginData = ref({ email: '', password: '' });
const loginError = ref('');
let toastTimer;

const pages = [
    { id: 'ringkasan', label: 'Ringkasan', icon: '01' },
    { id: 'buku', label: 'Data Buku', icon: '02' },
    { id: 'anggota', label: 'Anggota', icon: '03' },
    { id: 'transaksi', label: 'Peminjaman', icon: '04' },
];
const titles = { ringkasan: 'Ringkasan', buku: 'Data buku', anggota: 'Data anggota', transaksi: 'Peminjaman' };
const isKepala = computed(() => user.value?.role === 'kepala');
const activeLoans = computed(() => loans.value.filter((loan) => loan.status === 'dipinjam'));
const availableCopies = computed(() => books.value.reduce((total, book) => total + Number(book.stock), 0));
const filteredBooks = computed(() => books.value.filter((book) => `${book.title} ${book.author} ${book.isbn}`.toLowerCase().includes(search.value.toLowerCase())));
const filteredMembers = computed(() => members.value.filter((member) => `${member.name} ${member.member_number} ${member.phone}`.toLowerCase().includes(search.value.toLowerCase())));
const filteredLoans = computed(() => loans.value.filter((loan) => {
    const matchesFilter = loanFilter.value === 'semua' || loan.status === (loanFilter.value === 'aktif' ? 'dipinjam' : 'dikembalikan');
    const haystack = `${loan.book?.title ?? ''} ${loan.member?.name ?? ''} ${loan.user?.name ?? ''}`.toLowerCase();
    return matchesFilter && haystack.includes(search.value.toLowerCase());
}));
const currentTitle = computed(() => titles[page.value]);
const todayLabel = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...options.headers,
        },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw { status: response.status, message: payload.message ?? 'Permintaan tidak dapat diproses.', errors: payload.errors ?? {} };
    }
    return payload;
}

async function loadData() {
    const [bookResponse, memberResponse, loanResponse] = await Promise.all([
        api('/api/buku'), api('/api/anggota'), api('/api/transaksi'),
    ]);
    books.value = bookResponse.data;
    members.value = memberResponse.data;
    loans.value = loanResponse.data;
}

async function checkSession() {
    try {
        const response = await api('/api/session');
        if (!response.data) return;

        user.value = response.data;
        await loadData();
    } catch (error) {
        if (error.status !== 401) notify(error.message, true);
        user.value = null;
    }
}

async function signIn() {
    loginError.value = '';
    busy.value = true;
    try {
        const response = await api('/api/login', { method: 'POST', body: JSON.stringify(loginData.value) });
        user.value = response.data;
        await loadData();
    } catch (error) {
        loginError.value = error.message;
    } finally {
        busy.value = false;
    }
}

async function signOut() {
    await api('/api/logout', { method: 'POST' });
    user.value = null;
    page.value = 'ringkasan';
}

function notify(message, isError = false) {
    toast.value = message;
    toastError.value = isError;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => { toast.value = ''; }, 3200);
}

function openDialog(type, record = null) {
    const data = type === 'buku'
        ? { isbn: record?.isbn ?? '', title: record?.title ?? '', author: record?.author ?? '', stock: record?.stock ?? 0 }
        : type === 'anggota'
            ? { member_number: record?.member_number ?? '', name: record?.name ?? '', phone: record?.phone ?? '' }
            : { book_id: '', member_id: '', loan_date: new Date().toISOString().slice(0, 10) };
    dialog.value = { type, record, data, errors: {}, message: '' };
}

async function saveDialog() {
    if (!dialog.value) return;
    busy.value = true;
    dialog.value.errors = {};
    dialog.value.message = '';
    const { type, record, data } = dialog.value;
    const collection = type === 'buku' ? 'buku' : type === 'anggota' ? 'anggota' : 'transaksi';
    try {
        await api(record ? `/api/${collection}/${record.id}` : `/api/${collection}`, {
            method: record ? 'PUT' : 'POST',
            body: JSON.stringify(data),
        });
        dialog.value = null;
        await loadData();
        notify(type === 'transaksi' ? 'Peminjaman berhasil dicatat.' : record ? 'Perubahan berhasil disimpan.' : 'Data baru berhasil ditambahkan.');
    } catch (error) {
        if (dialog.value) {
            dialog.value.errors = error.errors;
            dialog.value.message = error.message;
        }
    } finally {
        busy.value = false;
    }
}

function fieldError(field) {
    return dialog.value?.errors?.[field]?.[0] ?? '';
}

async function returnLoan(loan) {
    try {
        await api(`/api/transaksi/${loan.id}/kembali`, { method: 'PATCH' });
        await loadData();
        notify('Pengembalian tercatat dan stok buku telah diperbarui.');
    } catch (error) {
        notify(error.message, true);
    }
}

async function deleteRecord(type, record) {
    const label = type === 'buku' ? record.title : type === 'anggota' ? record.name : `transaksi #${record.id}`;
    if (!window.confirm(`Hapus ${label}?`)) return;
    try {
        await api(`/api/${type}/${record.id}`, { method: 'DELETE' });
        await loadData();
        notify('Data berhasil dihapus.');
    } catch (error) {
        notify(error.message, true);
    }
}

function initials(name) {
    return (name ?? '').split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function displayDate(date) {
    if (!date) return '-';
    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(date));
}

onMounted(checkSession);
</script>

<template>
    <div v-if="!user" class="login-screen">
        <section class="login-art">
            <div class="brand"><span class="brand-mark">R</span><span>Ruang Baca</span></div>
            <div class="login-pitch">
                <p class="eyebrow">Sirkulasi perpustakaan</p>
                <h1>Setiap buku, selalu terlacak.</h1>
                <p>Kelola koleksi dan peminjaman dalam satu ruang kerja yang tertib.</p>
            </div>
            <div class="book-stack" aria-hidden="true"><span class="book-spine">KOLEKSI</span><span class="book-spine">CATATAN</span><span class="book-spine">CERITA</span><span class="book-spine">ARSIP</span></div>
            <div class="login-credit">PERPUSTAKAAN · 2026</div>
        </section>
        <main class="login-panel">
            <form class="login-form" @submit.prevent="signIn">
                <p class="eyebrow">Selamat datang kembali</p>
                <h2>Masuk ke sistem</h2>
                <p>Gunakan akun pustakawan untuk melanjutkan.</p>
                <div v-if="loginError" class="form-alert login-error">{{ loginError }}</div>
                <div class="field"><label for="login-email">Email</label><input id="login-email" v-model="loginData.email" type="email" autocomplete="username" required placeholder="nama@perpus.local"></div>
                <div class="field" style="margin-top:15px"><label for="login-password">Kata sandi</label><input id="login-password" v-model="loginData.password" type="password" autocomplete="current-password" required placeholder="Masukkan kata sandi"></div>
                <button class="button button-primary" style="width:100%;margin-top:22px" :disabled="busy">{{ busy ? 'Memeriksa…' : 'Masuk' }}</button>
                <div class="login-demo"><strong>Akun demo</strong><span>Kepala: kepala@perpus.local · password</span><span>Staf: staf@perpus.local · password</span></div>
            </form>
        </main>
    </div>

    <div v-else class="app-shell">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark">R</span><span>Ruang Baca</span></div>
            <p class="nav-caption">Perpustakaan</p>
            <nav class="nav-list" aria-label="Navigasi utama">
                <button v-for="item in pages" :key="item.id" class="nav-item" :class="{ active: page === item.id }" @click="page = item.id; search = ''">
                    <span class="nav-icon">{{ item.icon }}</span><span>{{ item.label }}</span><span v-if="item.id === 'transaksi' && activeLoans.length" class="nav-count">{{ activeLoans.length }}</span>
                </button>
            </nav>
            <div class="sidebar-bottom">
                <div class="staff-card"><span class="avatar">{{ initials(user.name) }}</span><div><div class="staff-name">{{ user.name }}</div><div class="staff-role">{{ isKepala ? 'Kepala Perpustakaan' : 'Staf Pustakawan' }}</div></div></div>
                <button class="logout-button" @click="signOut">Keluar dari akun</button>
            </div>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <div><p class="eyebrow">Ruang kerja pustakawan</p><h1>{{ currentTitle }}</h1></div>
                <div class="top-actions"><span class="today-label">{{ todayLabel }}</span><button class="button button-primary" @click="openDialog('transaksi')">+ Peminjaman</button></div>
            </header>

            <section class="page">
                <template v-if="page === 'ringkasan'">
                    <div class="page-heading"><div><h2>Aktivitas hari ini</h2><p>Ringkasan koleksi dan sirkulasi perpustakaan.</p></div></div>
                    <div class="stats-grid">
                        <article class="stat-card"><span class="stat-label">Judul koleksi</span><strong class="stat-value">{{ books.length }}</strong><span class="stat-note">judul buku terdata</span></article>
                        <article class="stat-card"><span class="stat-label">Buku tersedia</span><strong class="stat-value">{{ availableCopies }}</strong><span class="stat-note">eksemplar siap dipinjam</span></article>
                        <article class="stat-card"><span class="stat-label">Sedang dipinjam</span><strong class="stat-value">{{ activeLoans.length }}</strong><span class="stat-note">transaksi belum kembali</span></article>
                        <article class="stat-card"><span class="stat-label">Anggota aktif</span><strong class="stat-value">{{ members.length }}</strong><span class="stat-note">anggota terdaftar</span></article>
                    </div>
                    <div class="dashboard-grid">
                        <section class="panel">
                            <div class="panel-header"><div><h3>Peminjaman terbaru</h3><p>Riwayat sirkulasi terakhir</p></div><button class="text-link" @click="page = 'transaksi'">Semua transaksi →</button></div>
                            <div v-if="loans.length" class="table-wrap"><table><thead><tr><th>Buku / Anggota</th><th>Tanggal pinjam</th><th>Status</th></tr></thead><tbody><tr v-for="loan in loans.slice(0, 5)" :key="loan.id"><td><span class="primary-cell">{{ loan.book?.title }}</span><span class="secondary-cell">{{ loan.member?.name }}</span></td><td>{{ displayDate(loan.loan_date) }}</td><td><span class="status-pill" :class="{ returned: loan.status === 'dikembalikan' }">{{ loan.status === 'dipinjam' ? 'Dipinjam' : 'Dikembalikan' }}</span></td></tr></tbody></table></div>
                            <div v-else class="empty-state"><strong>Belum ada transaksi</strong>Peminjaman baru akan muncul di sini.</div>
                        </section>
                        <section class="panel">
                            <div class="panel-header"><div><h3>Pantauan stok</h3><p>Eksemplar tersisa per judul</p></div><button class="text-link" @click="page = 'buku'">Daftar buku →</button></div>
                            <div v-if="books.length" class="stock-list"><div v-for="book in books.slice(0, 6)" :key="book.id" class="stock-row"><div><div class="stock-title">{{ book.title }}</div><div class="stock-author">{{ book.author }}</div></div><span class="stock-number" :class="{ zero: book.stock === 0 }">{{ book.stock }}</span></div></div>
                            <div v-else class="empty-state"><strong>Belum ada buku</strong>Tambahkan koleksi pertama.</div>
                        </section>
                    </div>
                </template>

                <template v-else-if="page === 'buku'">
                    <div class="page-heading"><div><h2>Koleksi buku</h2><p>Tambah dan perbarui data judul serta ketersediaan.</p></div><button class="button button-primary" @click="openDialog('buku')">+ Tambah buku</button></div>
                    <div class="toolbar"><div class="result-count">{{ filteredBooks.length }} judul buku</div><label class="search-box"><span class="search-symbol">⌕</span><input v-model="search" type="search" placeholder="Cari judul, penulis, ISBN"></label></div>
                    <div class="table-panel"><div v-if="filteredBooks.length" class="table-wrap"><table><thead><tr><th>Judul / Penulis</th><th>ISBN</th><th>Stok tersedia</th><th>Aksi</th></tr></thead><tbody><tr v-for="book in filteredBooks" :key="book.id"><td><span class="primary-cell">{{ book.title }}</span><span class="secondary-cell">{{ book.author }}</span></td><td class="mono">{{ book.isbn }}</td><td><span class="status-pill" :class="{ empty: book.stock === 0 }">{{ book.stock === 0 ? 'Habis' : `${book.stock} tersedia` }}</span></td><td><div class="row-actions"><button class="button button-quiet" @click="openDialog('buku', book)">Ubah</button><button v-if="isKepala" class="button button-quiet button-danger" @click="deleteRecord('buku', book)">Hapus</button></div></td></tr></tbody></table></div><div v-else class="empty-state"><strong>{{ search ? 'Buku tidak ditemukan' : 'Belum ada koleksi' }}</strong>{{ search ? 'Coba kata pencarian lain.' : 'Tambahkan buku untuk mulai mengelola koleksi.' }}</div></div>
                </template>

                <template v-else-if="page === 'anggota'">
                    <div class="page-heading"><div><h2>Data anggota</h2><p>Anggota terdaftar tersedia pada pilihan peminjaman.</p></div><button class="button button-primary" @click="openDialog('anggota')">+ Tambah anggota</button></div>
                    <div class="toolbar"><div class="result-count">{{ filteredMembers.length }} anggota</div><label class="search-box"><span class="search-symbol">⌕</span><input v-model="search" type="search" placeholder="Cari nama, nomor, telepon"></label></div>
                    <div class="table-panel"><div v-if="filteredMembers.length" class="table-wrap"><table><thead><tr><th>Nama anggota</th><th>Nomor anggota</th><th>Telepon</th><th>Aksi</th></tr></thead><tbody><tr v-for="member in filteredMembers" :key="member.id"><td><span class="member-initial">{{ initials(member.name) }}</span><span class="primary-cell">{{ member.name }}</span></td><td class="mono">{{ member.member_number }}</td><td>{{ member.phone }}</td><td><div class="row-actions"><button class="button button-quiet" @click="openDialog('anggota', member)">Ubah</button><button v-if="isKepala" class="button button-quiet button-danger" @click="deleteRecord('anggota', member)">Hapus</button></div></td></tr></tbody></table></div><div v-else class="empty-state"><strong>{{ search ? 'Anggota tidak ditemukan' : 'Belum ada anggota' }}</strong>{{ search ? 'Coba kata pencarian lain.' : 'Tambahkan anggota agar dapat dipilih saat meminjam.' }}</div></div>
                </template>

                <template v-else>
                    <div class="page-heading"><div><h2>Riwayat peminjaman</h2><p>Catat peminjaman dan pantau pengembalian buku.</p></div><button class="button button-primary" @click="openDialog('transaksi')">+ Peminjaman</button></div>
                    <div class="toolbar"><div class="filter-tabs"><button v-for="filter in [{ id: 'semua', label: 'Semua' }, { id: 'aktif', label: 'Dipinjam' }, { id: 'selesai', label: 'Dikembalikan' }]" :key="filter.id" class="filter-tab" :class="{ active: loanFilter === filter.id }" @click="loanFilter = filter.id">{{ filter.label }}</button></div><label class="search-box"><span class="search-symbol">⌕</span><input v-model="search" type="search" placeholder="Cari buku atau anggota"></label></div>
                    <div class="table-panel"><div v-if="filteredLoans.length" class="table-wrap"><table><thead><tr><th>Buku</th><th>Anggota</th><th>Tanggal pinjam</th><th>Tanggal kembali</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="loan in filteredLoans" :key="loan.id"><td><span class="primary-cell">{{ loan.book?.title }}</span><span class="secondary-cell">{{ loan.book?.isbn }}</span></td><td><span class="primary-cell">{{ loan.member?.name }}</span><span class="secondary-cell">{{ loan.member?.member_number }}</span></td><td>{{ displayDate(loan.loan_date) }}</td><td>{{ displayDate(loan.returned_at) }}</td><td><span class="status-pill" :class="{ returned: loan.status === 'dikembalikan' }">{{ loan.status === 'dipinjam' ? 'Dipinjam' : 'Dikembalikan' }}</span></td><td><div class="row-actions"><button v-if="loan.status === 'dipinjam'" class="button button-quiet" @click="returnLoan(loan)">Catat kembali</button><button v-if="isKepala && loan.status === 'dikembalikan'" class="button button-quiet button-danger" @click="deleteRecord('transaksi', loan)">Hapus</button></div></td></tr></tbody></table></div><div v-else class="empty-state"><strong>{{ search ? 'Transaksi tidak ditemukan' : 'Belum ada peminjaman' }}</strong>{{ search ? 'Coba kata pencarian lain.' : 'Peminjaman baru akan tercatat di sini.' }}</div></div>
                </template>
            </section>
        </main>
    </div>

    <div v-if="dialog" class="modal-backdrop" @click.self="dialog = null">
        <section class="modal" role="dialog" aria-modal="true" :aria-label="dialog.type === 'transaksi' ? 'Peminjaman baru' : dialog.record ? 'Ubah data' : 'Tambah data'">
            <div class="modal-head"><div><h2>{{ dialog.type === 'transaksi' ? 'Peminjaman baru' : dialog.record ? `Ubah ${dialog.type}` : `Tambah ${dialog.type}` }}</h2><p>{{ dialog.type === 'transaksi' ? 'Pilih anggota dan buku yang masih tersedia.' : 'Lengkapi informasi berikut.' }}</p></div><button class="close-button" aria-label="Tutup" @click="dialog = null">×</button></div>
            <form class="modal-form" @submit.prevent="saveDialog">
                <div v-if="dialog.message" class="form-alert">{{ dialog.message }}</div>
                <template v-if="dialog.type === 'buku'">
                    <div class="field"><label for="book-isbn">ISBN</label><input id="book-isbn" v-model="dialog.data.isbn" required maxlength="20" placeholder="9786020000000"><span v-if="fieldError('isbn')" class="field-error">{{ fieldError('isbn') }}</span></div>
                    <div class="field"><label for="book-title">Judul buku</label><input id="book-title" v-model="dialog.data.title" required maxlength="255" placeholder="Masukkan judul buku"><span v-if="fieldError('title')" class="field-error">{{ fieldError('title') }}</span></div>
                    <div class="field"><label for="book-author">Penulis</label><input id="book-author" v-model="dialog.data.author" required maxlength="255" placeholder="Nama penulis"><span v-if="fieldError('author')" class="field-error">{{ fieldError('author') }}</span></div>
                    <div class="field"><label for="book-stock">Stok fisik</label><input id="book-stock" v-model.number="dialog.data.stock" type="number" min="0" required><span v-if="fieldError('stock')" class="field-error">{{ fieldError('stock') }}</span></div>
                </template>
                <template v-else-if="dialog.type === 'anggota'">
                    <div class="field"><label for="member-number">Nomor anggota</label><input id="member-number" v-model="dialog.data.member_number" required maxlength="30" placeholder="AG-0004"><span v-if="fieldError('member_number')" class="field-error">{{ fieldError('member_number') }}</span></div>
                    <div class="field"><label for="member-name">Nama lengkap</label><input id="member-name" v-model="dialog.data.name" required maxlength="255" placeholder="Nama anggota"><span v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</span></div>
                    <div class="field"><label for="member-phone">Nomor telepon</label><input id="member-phone" v-model="dialog.data.phone" required maxlength="30" placeholder="08xxxxxxxxxx"><span v-if="fieldError('phone')" class="field-error">{{ fieldError('phone') }}</span></div>
                </template>
                <template v-else>
                    <div class="field"><label for="loan-member">Anggota</label><select id="loan-member" v-model="dialog.data.member_id" required><option value="" disabled>Pilih anggota</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.member_number }} · {{ member.name }}</option></select><span v-if="fieldError('member_id')" class="field-error">{{ fieldError('member_id') }}</span></div>
                    <div class="field"><label for="loan-book">Buku</label><select id="loan-book" v-model="dialog.data.book_id" required><option value="" disabled>Pilih buku</option><option v-for="book in books" :key="book.id" :value="book.id" :disabled="book.stock < 1">{{ book.title }} · {{ book.stock ? `${book.stock} tersedia` : 'Stok habis' }}</option></select><span v-if="fieldError('book_id')" class="field-error">{{ fieldError('book_id') }}</span></div>
                    <div class="field"><label for="loan-date">Tanggal pinjam</label><input id="loan-date" v-model="dialog.data.loan_date" type="date" required><span v-if="fieldError('loan_date')" class="field-error">{{ fieldError('loan_date') }}</span></div>
                    <p v-if="members.length === 0" class="form-alert">Tambahkan anggota terlebih dahulu sebelum mencatat peminjaman.</p>
                </template>
                <div class="modal-actions"><button type="button" class="button button-secondary" @click="dialog = null">Batal</button><button class="button button-primary" :disabled="busy || (dialog.type === 'transaksi' && (!members.length || !books.some((book) => book.stock > 0)))">{{ busy ? 'Menyimpan…' : dialog.record ? 'Simpan perubahan' : 'Simpan' }}</button></div>
            </form>
        </section>
    </div>
    <div v-if="toast" class="toast" :class="{ error: toastError }" role="status">{{ toast }}</div>
</template>