# Flow per Role — Modul E-5

Diagram alur kerja tiap role di E-5. Disusun dari tiga dokumen di folder ini:

- [PRD.md](PRD.md) v0.3: user story, status, dan matriks izin (§5, §6, §11)
- *Hak Akses per Role — Modul E-5.pdf*: matriks akses per fitur dan ringkasan per role
- *Rancangan_Antarmuka_E5_v0.2.pdf*: layar UI-K05-01 s.d. UI-K05-07 beserta validasinya

Kode layar `UI-K05-xx` merujuk ke Rancangan Antarmuka. Kode error mengikuti `openapi.yaml`.
Bagian yang ditandai **Slice 2** atau **Slice 3** belum dibangun di slice 1.

---

## 1. Alur besar (semua role)

Urutan SOP-06: Weekly Meeting → User Discussion → TW → MoM → Change/Adjustment → TL Analysis.

```mermaid
flowchart LR
    subgraph PM["PM"]
        A1["Jadwalkan rapat<br/>SCHEDULED"]
        A2["Tandai rapat DONE"]
    end
    subgraph TW["TW"]
        B1["Tulis MoM<br/>DRAFT"]
        B2["Ajukan review<br/>IN_REVIEW"]
    end
    subgraph TL["TL"]
        C1{"Review MoM"}
    end
    subgraph PIC["PIC: PM / TL / TW"]
        D1["Tindak lanjut action item<br/>TODO → IN_PROGRESS → DONE"]
    end
    subgraph S2["Slice 2–3: PM / TL"]
        E1["Catat perubahan /<br/>CR dari MoM FINAL"]
        E2["Risiko & isu,<br/>indikator proyek"]
    end

    A1 --> A2 --> B1 --> B2 --> C1
    C1 -- "Minta revisi<br/>REVISION" --> B1
    C1 -- "Finalkan<br/>FINAL" --> D1
    C1 -- "FINAL" --> E1
    D1 --> E2
    E1 --> E2
```

---

## 2. PM (Project Manager)

PM menjadwalkan rapat dan menutupnya, membaca semua data proyeknya, dan menjadi PIC action item
jika ditunjuk. PM **tidak** boleh menulis, mengedit, atau memfinalkan MoM.

```mermaid
flowchart TD
    Start(["Login (dev login, F-1)"]) --> Proj["Pilih proyek<br/>(daftar dari D-1)"]
    Proj --> Menu{"Pilih menu"}

    %% Rapat
    Menu --> Rapat["UI-K05-01 Daftar Rapat"]
    Rapat --> Jadwal["Klik Jadwalkan Rapat<br/>isi judul, tanggal/jam, lokasi,<br/>peserta internal, nama klien"]
    Jadwal --> CekAktif{"Proyek aktif?"}
    CekAktif -- Tidak --> ErrAktif["409 PROJECT_INACTIVE<br/>Proyek tidak ditemukan atau tidak aktif"]
    CekAktif -- Ya --> CekField{"Judul, waktu, dan<br/>min. 1 peserta terisi?<br/>Peserta tidak dobel?"}
    CekField -- Tidak --> ErrField["400 VALIDATION_ERROR<br/>Harap isi tanggal dan peserta rapat"]
    CekField -- Ya --> Scheduled["Rapat tersimpan<br/>status SCHEDULED"]
    Scheduled --> Tutup{"Rapat selesai?"}
    Tutup -- "Terlaksana" --> Done["Tandai DONE<br/>(TW bisa menulis MoM)"]
    Tutup -- "Batal" --> Cancel["Tandai CANCELLED"]

    %% MoM
    Menu --> LihatMom["UI-K05-07 Detail MoM<br/>(read-only, tanpa tombol aksi)"]
    LihatMom -. "Coba buat / edit MoM" .-> Tolak["UI-K05-03 Akses Ditolak<br/>403 FORBIDDEN"]

    %% Action item
    Menu --> AI["UI-K05-04 Action Items"]
    AI --> TabAI{"Tab"}
    TabAI -- "Semua di proyek" --> AllAI["Lihat semua action item<br/>+ badge OVERDUE"]
    TabAI -- "Milik saya" --> MyAI["Action item dengan PIC = PM"]
    MyAI --> UpdAI["Ubah status<br/>TODO → IN_PROGRESS → DONE"]
    AllAI -. "Item milik orang lain" .-> Lock["Dropdown nonaktif<br/>403 FORBIDDEN"]

    %% Slice 2
    Menu --> CR["UI-K05-05 Change Request & Log<br/>(Slice 2)"]
    CR --> CatatCR["Tambah Perubahan<br/>rujuk MoM FINAL"]
    CatatCR --> Formal{"isFormalCr?"}
    Formal -- Tidak --> Recorded["RECORDED<br/>masuk change log tanpa persetujuan"]
    Formal -- Ya --> Pending["PENDING_ANALYSIS<br/>menunggu analisis TL"]
    Pending --> Analyzed["ANALYZED<br/>(impact diisi TL)"]
    Analyzed --> Putus{"Putuskan<br/>alasan wajib diisi"}
    Putus -- Setujui --> Approved["APPROVED"]
    Putus -- Tolak --> Rejected["REJECTED"]

    %% Slice 3
    Menu --> Risk["UI-K05-06 Risiko & Isu<br/>(Slice 3)"]
    Risk --> AddRisk["Tambah risiko/isu<br/>type, title, description, PIC<br/>status otomatis OPEN"]
    AddRisk --> RiskSt["Ubah status semua risiko/isu<br/>OPEN → IN_PROGRESS → CLOSED"]
    Risk --> Ind["Lihat indikator proyek<br/>ON_TRACK / DELAYED (read-only)"]
```

---

## 3. TL (Technical Lead)

TL mereview MoM, membaca semua action item proyeknya, menganalisis dampak CR, dan ikut
memutuskan CR. TL **tidak** boleh membuat rapat, menulis MoM, atau mencatat risiko/isu baru.

```mermaid
flowchart TD
    Start(["Login (dev login, F-1)"]) --> Proj["Pilih proyek"]
    Proj --> Menu{"Pilih menu"}

    %% Rapat
    Menu --> Rapat["UI-K05-01 Daftar Rapat<br/>(lihat saja, tanpa tombol Jadwalkan)"]

    %% Review MoM
    Menu --> Review["UI-K05-07 Detail & Review MoM"]
    Review --> CekSt{"Status MoM<br/>IN_REVIEW?"}
    CekSt -- "Tidak (DRAFT / REVISION / FINAL)" --> ReadOnly["Tampil read-only<br/>tombol aksi disembunyikan"]
    CekSt -- Ya --> Pilih{"Keputusan review"}
    Pilih -- Finalkan --> Final["FINAL<br/>finalizedAt terisi, MoM terkunci<br/>action item mulai tampil"]
    Pilih -- "Minta Revisi" --> Note{"Catatan revisi<br/>terisi?"}
    Note -- Tidak --> ErrNote["400 VALIDATION_ERROR<br/>Catatan revisi wajib diisi"]
    Note -- Ya --> Revision["REVISION<br/>catatan tampil ke TW"]
    Review -. "Coba buat / edit MoM" .-> Tolak["UI-K05-03 Akses Ditolak<br/>403 FORBIDDEN"]

    %% Action item
    Menu --> AI["UI-K05-04 Action Items"]
    AI --> TabAI{"Tab"}
    TabAI -- "Semua di proyek" --> AllAI["Lihat semua action item<br/>+ badge OVERDUE"]
    TabAI -- "Milik saya" --> MyAI["Action item dengan PIC = TL"]
    MyAI --> UpdAI["Ubah status<br/>TODO → IN_PROGRESS → DONE"]
    AllAI -. "Item milik orang lain" .-> Lock["Dropdown nonaktif<br/>403 FORBIDDEN"]

    %% Slice 2
    Menu --> CR["UI-K05-05 Change Request & Log<br/>(Slice 2)"]
    CR --> CatatCR["Tambah Perubahan<br/>rujuk MoM FINAL"]
    CatatCR --> Formal{"isFormalCr?"}
    Formal -- Tidak --> Recorded["RECORDED"]
    Formal -- Ya --> Pending["PENDING_ANALYSIS"]
    Pending --> Analisis["Isi impact, baseline (E-3),<br/>tugas terdampak (E-4)<br/>baseline wajib untuk SCOPE/TIMELINE"]
    Analisis --> Analyzed["ANALYZED"]
    Analyzed --> Putus{"Putuskan<br/>alasan wajib diisi"}
    Putus -- Setujui --> Approved["APPROVED"]
    Putus -- Tolak --> Rejected["REJECTED"]

    %% Slice 3
    Menu --> Risk["UI-K05-06 Risiko & Isu<br/>(Slice 3)"]
    Risk --> RiskList["Lihat daftar risiko/isu"]
    RiskList --> RiskSt["Ubah status isu yang PIC-nya TL<br/>OPEN → IN_PROGRESS → CLOSED"]
    Risk -. "Lihat indikator proyek" .-> IndTolak["Ditolak<br/>indikator hanya untuk PM"]
```

---

## 4. TW (Technical Writer)

TW menulis MoM, menandai kehadiran, membuat action item di dalam MoM, lalu mengajukan review.
TW hanya melihat action item miliknya. TW **tidak** boleh membuat rapat, memfinalkan MoM,
mencatat atau memutuskan perubahan, atau mengelola risiko/isu.

```mermaid
flowchart TD
    Start(["Login (dev login, F-1)"]) --> Proj["Pilih proyek"]
    Proj --> Menu{"Pilih menu"}

    %% Rapat -> MoM
    Menu --> Rapat["UI-K05-01 Daftar Rapat<br/>(lihat saja)"]
    Rapat --> Detail["Detail rapat"]
    Detail --> CekDone{"Rapat DONE?"}
    CekDone -- Tidak --> ErrDone["409 MEETING_NOT_DONE<br/>tombol Tulis MoM tidak tampil"]
    CekDone -- Ya --> CekAda{"MoM sudah ada?"}
    CekAda -- Ya --> Buka["Buka MoM yang ada"]
    CekAda -- "Belum" --> Form["UI-K05-02 Draft MoM<br/>8 poin WI-05"]

    Form --> Isi["Isi tanggal, centang kehadiran,<br/>pembahasan, keputusan,<br/>action item (deskripsi, PIC, deadline),<br/>perubahan (opsional)"]
    Isi --> Valid{"Tanggal, pembahasan,<br/>keputusan terisi?<br/>Deadline ≥ tanggal rapat?"}
    Valid -- Tidak --> ErrVal["400 VALIDATION_ERROR<br/>sebutkan kolom yang kosong"]
    ErrVal --> Isi
    Valid -- Ya --> Draft["Simpan Draft<br/>status DRAFT"]
    Draft --> Ajukan["Ajukan Review<br/>status IN_REVIEW"]
    Ajukan --> Tunggu{"Hasil review TL"}
    Tunggu -- "Minta revisi" --> Rev["REVISION<br/>baca catatan TL"]
    Rev --> Isi
    Tunggu -- Finalkan --> Final["FINAL<br/>terkunci, tidak bisa diedit"]
    Final -. "Coba edit" .-> ErrEdit["409 MOM_NOT_EDITABLE"]
    Buka --> Edit{"Status DRAFT<br/>atau REVISION?"}
    Edit -- Ya --> Isi
    Edit -- Tidak --> ErrEdit

    %% Action item
    Menu --> AI["UI-K05-04 Action Items<br/>hanya tab Milik saya"]
    AI --> MyAI["Action item dengan PIC = TW<br/>+ badge OVERDUE"]
    MyAI --> UpdAI["Ubah status<br/>TODO → IN_PROGRESS → DONE"]
    UpdAI --> CekSt{"Status valid &<br/>maju ke depan?"}
    CekSt -- Tidak --> ErrSt["409 INVALID_STATUS_TRANSITION<br/>kembali ke status terakhir"]
    CekSt -- Ya --> Saved["Status tersimpan<br/>riwayat dicatat"]

    %% Slice 2-3 (lihat saja)
    Menu --> CR["Change log (Slice 2)<br/>lihat saja"]
    Menu --> Risk["Risiko & isu (Slice 3)<br/>lihat saja"]
    CR -. "Coba catat / setujui CR" .-> CRTolak["Tombol nonaktif<br/>Akses ditolak"]
```

---

## 5. Klien dan D-3

Keduanya tidak login ke E-5.

```mermaid
flowchart LR
    subgraph Klien["Klien (User)"]
        K1["Hadir di rapat"] --> K2["Dicatat PM sebagai peserta<br/>participantType CLIENT + displayName"]
        K2 --> K3["Kehadiran dicentang TW di MoM"]
    end
    subgraph D3["D-3 (layanan)"]
        D1["GET /api/v1/projects/{projectId}/control-data<br/>API-K05-09"] --> D2["Terima risiko/isu, RECORDED,<br/>CR APPROVED/REJECTED per periode"]
        D2 --> D3a["Tanpa field akun internal<br/>dan catatan review"]
    end
```

---

## 6. Status yang dipakai di semua flow

```mermaid
stateDiagram-v2
    direction LR
    state "Meeting (PM)" as M {
        [*] --> SCHEDULED
        SCHEDULED --> DONE
        SCHEDULED --> CANCELLED
    }
    state "MoM (TW → TL)" as MoM {
        [*] --> DRAFT
        DRAFT --> IN_REVIEW: TW ajukan
        IN_REVIEW --> FINAL: TL finalkan
        IN_REVIEW --> REVISION: TL minta revisi
        REVISION --> IN_REVIEW: TW ajukan ulang
    }
    state "Action item (PIC)" as AI {
        [*] --> TODO
        TODO --> IN_PROGRESS
        IN_PROGRESS --> DONE
    }
```

Setiap perubahan status dicatat otomatis di `StatusHistory` (siapa, kapan, dari/ke, catatan).
Riwayat tidak bisa diubah atau dihapus oleh siapa pun.

---

## 7. Perbedaan antar dokumen (perlu dikonfirmasi)

Flow di atas mengikuti PRD untuk slice 1 dan dokumen Hak Akses untuk slice 2–3. Beberapa aturan
tidak sama di ketiga dokumen:

| Topik | PRD v0.3 | Hak Akses | Rancangan Antarmuka v0.2 |
|---|---|---|---|
| Siapa membaca MoM | Semua anggota proyek | PM semua; TL/TW hanya MoM yang dia ikuti atau PIC | PM dan TW baca di UI-K05-07 |
| Siapa mencatat risiko/isu | PM (§3, slice 3) | Hanya PM | PM/TL (validasi UI-K05-06) |
| TW melihat change log dan risiko/isu | Belum dibahas | Boleh lihat | UI-K05-05 dan UI-K05-06 hanya PM, TL |
| Indikator proyek | Belum dibahas | Belum dibahas | Hanya PM |
| Kolom Change/Adjustment di MoM | Opsional | — | Wajib atau dikonfirmasi nihil |
