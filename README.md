# E-5 Pengawasan, Rapat, Risiko & Perubahan

Modul E-5 pada proyek ERP PT Anaheim Nimbus Universal: rapat mingguan, MoM,
action item, dan data risiko/perubahan untuk D-3. Kebutuhan ada di
[PRD.md](PRD.md), kontrak API di [openapi.yaml](openapi.yaml).

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
Data baseline E-3 dan tugas E-4 juga dibaca dari sana, dan proyek PRJ-2026-016
menghasilkan indikator DELAYED sedangkan PRJ-2026-014 ON_TRACK.

| Akun | Peran | Proyek |
|---|---|---|
| `acc-pm-0007` | PM | PRJ-2026-014, PRJ-2026-016, PRJ-2026-015 (nonaktif) |
| `acc-tl-0042` | TL | PRJ-2026-014, PRJ-2026-016 |
| `acc-tw-0091` | TW | PRJ-2026-014, PRJ-2026-016 |
| `acc-tw-0092` | TW | PRJ-2026-015 (nonaktif) |

## Memanggil API

Gunakan Postman, dengan `php artisan serve` berjalan:

1. **Import** → `openapi.yaml` → *Postman Collection*. Semua endpoint dibuat, dengan
   `baseUrl` = `http://localhost:8000/api/v1`.
2. Collection → **Authorization** → *Bearer Token* → `mock-acc-pm-0007`. Sampai F-1
   merilis skema tokennya, token berbentuk `mock-<accountId>`. Ganti untuk bertindak
   sebagai akun lain dari tabel di atas.
3. Kirim mis. *GET /me* atau *GET /projects/{projectId}/meetings* dengan `projectId` = `PRJ-2026-014`.

Import ulang setelah `openapi.yaml` berubah. Endpoint user story yang belum dibuat
mengembalikan `404 NOT_FOUND` sampai diimplementasikan.

Respons dikembalikan tanpa pembungkus `data`. Setiap error berbentuk `{ "code", "message" }`.

## Test dan lint

```bash
php artisan test                      # SQLite in-memory, tidak perlu MySQL
npx @redocly/cli@1 lint openapi.yaml
```

CI (`.github/workflows/ci.yml`) menjalankan keduanya pada setiap push dan pull request.

## Menambah endpoint

Ikuti [DESIGN.md](DESIGN.md) (aturan kode dan UI; agen AI membacanya lewat
`AGENTS.md` / `CLAUDE.md`).
Salin endpoint acuan `GET /projects/{projectId}/meetings`:
[MeetingController](app/Http/Controllers/Api/MeetingController.php),
[ListMeetingsRequest](app/Http/Requests/ListMeetingsRequest.php),
[MeetingPolicy](app/Policies/MeetingPolicy.php),
[MeetingResource](app/Http/Resources/MeetingResource.php),
[MeetingListTest](tests/Feature/MeetingListTest.php).
Perbarui `openapi.yaml` dalam pull request yang sama.
