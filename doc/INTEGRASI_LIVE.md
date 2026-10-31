# Adapter live F-1 dan D-1

Status: adapter selesai dan teruji. **Sumber anggota proyek masih sementara** (lihat bagian 3).
E-3 dan E-4 tetap mock.

## 1. Cara menyalakan

Tiap integrasi punya mode sendiri. `INTEGRATION_MODE` menjadi nilai bawaan, `F1_MODE`, `D1_MODE`,
`E3_MODE`, `E4_MODE` menimpanya satu per satu.

```env
INTEGRATION_MODE=mock
F1_MODE=live
D1_MODE=live

F1_BASE_URL=http://127.0.0.1:4100        # demo server F-1 tanpa /api/v1; kontrak F-1 memakai /api/v1
F1_JWT_SECRET=...                        # harus SAMA PERSIS dengan JWT_SECRET F-1; jangan di-commit
F1_ROLE_MAP=PM:PM,TL:TL,TW:TW            # roleCode F-1 : peran E-5
D1_BASE_URL=http://localhost:3000/api/v1
D1_ACTIVE_STATUSES=REGISTERED
E5_PROJECT_MEMBERS_FILE=storage/app/project_members.json   # format: database/mocks/project_members.example.json
```

Pilihan lain (`F1_SERVICE_TOKEN`, `D1_TOKEN`, `UPSTREAM_TIMEOUT`) ada di `.env.example`.
Nilai wajib yang kosong membuat aplikasi gagal saat pemakaian pertama dengan nama variabelnya.

## 2. Perilaku

**F-1** (`app/Integrations/Http/HttpIdentityProvider`, `BearerTokenAuthenticator`, `Hs256Verifier`)
- API menerima `Authorization: Bearer <JWT F-1>`. Tanda tangan HS256 dan `exp` diperiksa lokal, lalu E-5
  bertanya ke `GET /identity/accounts/{accountId}` memakai token pengguna itu. **Peran dan nama diambil dari
  jawaban F-1**, bukan dari token, sehingga akun yang dinonaktifkan atau diubah perannya langsung berlaku.
- Hanya akun berstatus `active` dengan peran yang terpetakan yang dikenali. Akun `inactive`/`locked` atau berperan
  lain (ADMIN, EMPLOYEE, ...) dianggap tidak ada: tidak bisa masuk, tidak bisa dipilih sebagai PIC atau peserta.
- Token `mock-<id>` ditolak (401) dan `/dev-login` menjadi 404 saat F-1 live; tanpa itu siapa pun bisa
  masuk sebagai akun mana saja. **Halaman web belum bisa dipakai saat F-1 live** (butuh halaman login F-1, tugas berikutnya).
- F-1 mati atau error 5xx → 503 `UPSTREAM_UNAVAILABLE`, bukan "akun tidak ditemukan".

**D-1** (`app/Integrations/Http/HttpProjectDirectory`)
- `GET /projects/{projectId}` dan `GET /projects?status=&page=&limit=100` (semua halaman diikuti).
- Proyek aktif = status D-1 termasuk `D1_ACTIVE_STATUSES` (bawaan `REGISTERED`).
- Hanya UUID `projectId` yang diterima. D-1 juga melayani kode kanonis (`202601`); itu ditolak di E-5 supaya
  satu proyek tidak tersimpan dengan dua ID.
- D-1 mati atau 5xx → 503 `UPSTREAM_UNAVAILABLE`.

## 3. Sumber anggota proyek (belum ada)

Dibaca dari kedua repo: **tidak ada yang menyediakan anggota proyek.**

| | Yang dimiliki | Kekurangan |
|---|---|---|
| D-1 | `amEmployeeId` dan `pmEmployeeId` (satu AM, satu PM) | Tidak ada TL/TW. Nilainya `employeeId` (F-2), bukan `accountId` (F-1); seed D-1 memakai `SDM-0004`, bukan UUID |
| F-1 | Akun, `employeeId` per akun, peran | Tidak tahu proyek. Tidak ada pencarian akun berdasarkan `employeeId` |

Sementara itu `FileProjectMemberSource` membaca file JSON `{"<projectId D-1>": ["<accountId F-1>"]}`.
Gagal-tertutup: file hilang atau proyek tak terdaftar berarti tidak ada anggota (akses ditolak).
Antarmukanya `App\Contracts\ProjectMemberSource`, jadi sumber asli cukup menggantinya satu kelas.

Yang perlu diputuskan dengan D-1, F-1, dan F-2 (pemilik penugasan SDM):
1. Siapa yang menyediakan daftar anggota proyek per peran (PM/TL/TW)? Usul: D-1 menambah `members[{accountId, role}]`
   di `GET /projects/{id}`, atau F-2 menyediakan endpoint penugasan.
2. Bagaimana `employeeId` dipetakan ke `accountId` jika sumbernya F-2?

## 4. Temuan pada F-1 dan D-1 (untuk dikonfirmasi)

F-1
- Peran F-1 hanya CEO, COO, CTO, ADMIN, AM, PM, EMPLOYEE, SPECIALIST. **Tidak ada TL dan TW**, padahal E-5 memerlukannya.
  Selama itu, hanya PM yang bisa masuk (kecuali `F1_ROLE_MAP` diisi, mis. `SPECIALIST:TL,EMPLOYEE:TW`; keputusan kelas).
- Kontrak menyebut server `.../api/v1`, demo server tidak punya `/api/v1`.
- Respons login demo `{accessToken, tokenType, expiresIn, accountId, roleCode}` berbeda dari kontrak (`account{...}`).
- Belum ada token layanan; `GET /accounts` (daftar) hanya untuk COO/CTO/Admin. Karena itu nama pengguna lain diambil satu per satu.

D-1 (dari membaca kode)
- API tanpa autentikasi. Detail dan daftar mengembalikan seluruh baris, termasuk `indicativeValue`, `rejectionNotes`,
  dan `auditLogs`, padahal README menyebut nilai komersial tidak dibocorkan.
- Tidak ada status "ditutup"; proyek yang ditutup D-3 tetap `REGISTERED` sehingga tetap dianggap aktif oleh E-5.
- `limit` bawaan 3 dan tanpa batas atas.

## 5. Pengujian

`php artisan test` (suite lengkap, 428 test). Test live memakai HTTP palsu dengan bentuk respons F-1 dan D-1
(`tests/Support/LiveIntegration.php`). Uji manual juga dilakukan terhadap **demo server F-1 asli**
(login nyata, JWT nyata) dengan **stand-in D-1** karena Prisma D-1 tidak bisa dipasang di lingkungan uji.
D-1 asli belum pernah dipanggil oleh adapter ini; jalankan sekali sebelum mengandalkannya.
