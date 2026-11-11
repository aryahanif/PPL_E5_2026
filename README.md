# E-5 Pengawasan, Rapat, Risiko & Perubahan

Modul E-5 pada proyek ERP PT Anaheim Nimbus Universal: rapat mingguan, MoM,
action item, dan data risiko/perubahan untuk D-3. Kebutuhan ada di
[doc/PRD.md](doc/PRD.md), kontrak API di [openapi.yaml](openapi.yaml).

**Stack:** Laravel 13, PHP 8.3+, MySQL (test memakai SQLite in-memory), Blade.

## Prasyarat

- PHP 8.3 atau lebih baru dengan `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`
- Composer 2
- MySQL 8 (atau MariaDB)
- Node.js, hanya untuk lint `openapi.yaml`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat database kosong `e5_pengawasan` di MySQL, lalu isi `DB_USERNAME` dan
`DB_PASSWORD` di `.env`. Session dan cache disimpan di file, jadi tabel
`sessions`, `cache`, dan `jobs` tidak diperlukan.

```bash
php artisan migrate:fresh --seed   # tabel E-5 + data fiktif tetap (PRD §10)
php artisan serve                  # http://localhost:8000
```

Buka http://localhost:8000/dev-login dan pilih akun mock.

Akun F-1 dan proyek D-1 **tidak** disimpan di database. Keduanya dibaca dari
`database/mocks/*.json` selama `INTEGRATION_MODE=mock` (lihat `config/e5.php`).
Data baseline E-3 dan tugas E-4 juga dibaca dari sana.

Semua ID fixture adalah UUID v7 tetap. Grup kedua menandai pemiliknya: `f101` akun F-1,
`d101` proyek D-1, `e301` baseline E-3, `e401` tugas E-4, dan `1111` sampai `6666` data E-5.

| Proyek | Nama | Keterangan |
|---|---|---|
| `019a0000-d101-7000-8000-000000000014` | Proyek Contoh Sistem Informasi | Aktif, indikator ON_TRACK |
| `019a0000-d101-7000-8000-000000000015` | Proyek Contoh Nonaktif | Nonaktif |
| `019a0000-d101-7000-8000-000000000016` | Proyek Contoh Terlambat | Aktif, indikator DELAYED |

| Akun | Peran | Proyek (akhiran ID) |
|---|---|---|
| `019a0000-f101-7000-8000-000000000007` | PM | …014, …016, …015 (nonaktif) |
| `019a0000-f101-7000-8000-000000000042` | TL | …014, …016 |
| `019a0000-f101-7000-8000-000000000091` | TW | …014, …016 |
| `019a0000-f101-7000-8000-000000000092` | TW | …015 (nonaktif) |

## Memanggil API

Gunakan Postman, dengan `php artisan serve` berjalan:

1. **Import** → `openapi.yaml` → *Postman Collection*. Semua endpoint dibuat, dengan
   `baseUrl` = `http://localhost:8000/api/v1`.
2. Collection → **Authorization** → *Bearer Token* → `mock-019a0000-f101-7000-8000-000000000007`. Sampai F-1
   merilis skema tokennya, token berbentuk `mock-<accountId>`. Ganti untuk bertindak
   sebagai akun lain dari tabel di atas.
3. Kirim mis. *GET /me* atau *GET /projects/{projectId}/meetings* dengan `projectId` = `019a0000-d101-7000-8000-000000000014`.

Import ulang setelah `openapi.yaml` berubah. Endpoint user story yang belum dibuat
mengembalikan `404 NOT_FOUND` sampai diimplementasikan.

Respons dikembalikan tanpa pembungkus `data`. Setiap error berbentuk `{ "code", "message" }`.

## Untuk D-3: API-K05-09

`GET /api/v1/projects/{projectId}/control-data?periodStart=YYYY-MM-DD&periodEnd=YYYY-MM-DD`

Masih **stub**: respons mengikuti `openapi.yaml`, tetapi isinya data contoh tetap
(`examples/success.json`); hanya `projectId` dan periode yang mengikuti request. Validasi
periode, 401, 403, dan 404 sudah berjalan nyata. Data asli menyusul di slice 4 (BL-13).

Token sementara: `Bearer mock-019a0000-f101-7000-8000-000000000007` (token layanan D-3 dari F-1 belum ada).

| Kasus | Request | Hasil |
|---|---|---|
| Berhasil | `019a0000-d101-7000-8000-000000000014`, `periodStart=2026-09-01&periodEnd=2026-09-30` | 200 |
| Periode terbalik | `periodStart=2026-09-30&periodEnd=2026-09-01` | 400 `INVALID_PERIOD` |
| Tanpa token | tanpa header `Authorization` | 401 |
| Bukan anggota | token `mock-019a0000-f101-7000-8000-000000000092` ke `019a0000-d101-7000-8000-000000000014` | 403 |
| Proyek tidak ada | `019a0000-d101-7000-8000-000000000999` | 404 `PROJECT_NOT_FOUND` |

Tanpa menjalankan Laravel: `npx @stoplight/prism-cli mock openapi.yaml -p 4010`, lalu
panggil `http://localhost:4010/api/v1/...`.

## Test dan lint

```bash
php artisan test                      # SQLite in-memory, tidak perlu MySQL
npx @redocly/cli@1 lint openapi.yaml
```

CI (`.github/workflows/ci.yml`) menjalankan keduanya pada setiap push dan pull request.

## Menambah endpoint

Ikuti [doc/DESIGN.md](doc/DESIGN.md) (aturan kode dan UI; agen AI membacanya lewat
`AGENTS.md` / `CLAUDE.md`).
Salin endpoint acuan `GET /projects/{projectId}/meetings`:
[MeetingController](app/Http/Controllers/Api/MeetingController.php),
[ListMeetingsRequest](app/Http/Requests/ListMeetingsRequest.php),
[MeetingPolicy](app/Policies/MeetingPolicy.php),
[MeetingResource](app/Http/Resources/MeetingResource.php),
[MeetingListTest](tests/Feature/MeetingListTest.php).
Perbarui `openapi.yaml` dalam pull request yang sama.
