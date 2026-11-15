# TODO — SI-Sarpras

>Status implementasi terhadap [docs/Rancangan_Sistem_Informasi_Sarpras.md](docs/Rancangan_Sistem_Informasi_Sarpras.md) (v0.1, Bagian 14: 5 tahap).
>Pembaruan terakhir: 2026-11-16 — laporan aset & audit per pengguna/rentang selesai.

## Progress singkat

- Tahap 1 Fondasi ✅ — users/roles/permissions, teams, notifikasi, layout.
- Tahap 2 Inventaris aset ✅ — master kategori/lokasi, CRUD aset, kode inventaris, QR label, foto, histori, scan.
- Tahap 3 Operasional ✅ — peminjaman, kerusakan, jadwal pemeliharaan, pengingat.
- Tahap 4 Tata kelola ✅ — audit log + role auditor; pengadaan; penghapusan; laporan pengadaan/penghapusan.
- Tahap 5 Penyempurnaan 🚧 berjalan — dashboard & laporan aset selesai, tinggal scan kamera.
- **±9 dari 12 modul berfungsi** (Bagian 2.1 dokumen).

## Tahap 3b — Perawatan & Pengingat ✅ (selesai 2026-11-14)

- [x] **Laporan kerusakan** (`maintenance_reports`, §6.5): pelapor membuat laporan (aset, urgensi, deskripsi, foto lampiran); status menunggu → dalam proses → selesai/dibatalkan; penugasan petugas teknis; catat tindakan, biaya, suku cadang.
- [x] **Jadwal pemelihraan** (`maintenance_schedules`, §6.6): frekuensi harian/mingguan/bulan/semester/tahun; petugas penanggung jawab; realisasi pelaksanaan + tanggal jadwal berikutnya; tampilan jatuh tempo & terlambat.
- [x] **Role `petugas-teknis`** (§3): mengambil tugas perawatan/perbaikan, memperbarui progres.
- [x] **Scheduler** (`routes/console.php` + `Schedule::`): job harian pengecekan jadwal & tenggat → notifikasi.
- [x] **Pengingat tenggat peminjaman** (§6.4): notifikasi sebelum jatuh tempo + keterlambatan (job harian, table notifications).
- [x] **Pemicu notifikasi modul sarpras** (§6.10): pengajuan dibuat/disetujui/ditolak/di-serah terima/di-kembalikan → notifikasi ke pihak terkait (pemohon/penyetuju).
- [x] **Aturan bisnis #7**: aset dengan laporan rusak ditandai otomatis tidak tersedia/dalam perbaikan.
- [x] **Test** modul perawatan + scheduler.
- [x] pint, `wayfinder:generate --with-form`, `npm run build`, migrasi + seed DB dev, full test suite.

## Tahap 3c — Laporan Modul ✅ (selesai 2026-11-15)

- [x] Riwayat peminjaman & keterlambatan (§12) — filter periode/status, hari terlambat, ekspor CSV.
- [x] Rekap laporan kerusakan & biaya perbaikan — total biaya, rekap per kategori, ekspor CSV.
- [x] Permission `report.view` (admin, pimpinan, auditor) + test.

## Tahap 4 — Tata Kelola Aset

- [x] **Pengadaan** (`procurements` + `procurement_items`, §6.7): usulan multi-item + alasan + estimasi; verifikasi/persetujuan (alur berjenjang `approval_flows` module `procurement`); penerimaan → barang menjadi aset inventaris.
- [x] **Penghapusan** (`disposals`, §6.8): usulan dengan alasan + bukti; persetujuan; metode + tanggal realisasi + dokumen; aset berstatus `dihapus` tanpa menghilangkan histori.
- [x] **Audit log global** (`audit_logs`, §6.10): aktor, waktu, aksi, jenis objek, ID objek, ringkasan; read-only bagi pengguna biasa.
- [x] **Role `auditor`** (§3): baca semua data + audit log.
- [x] **Laporan pengadaan/penghapusan** (§12).
- [x] Test, pint, wayfinder, build, migrasi, seed.

## Tahap 5 — Penyempurnaan

- [x] **Dashboard sarpras** (§6.1, selesai 2026-11-15): ringkasan aset per kategori/lokasi/status/kondisi; peminjaman aktif & mendekati tenggat; kerusakan belum selesai; jadwal jatuh tempo; ringkasan pengadaan & penghapusan (hanya tampil sesuai permission).
- [ ] **Scan via kamera** (§6.3): integrasi pemindai kamera HP (BarcodeDetector API / lib) → buka detail aset.
- [x] Laporan lengkap §12 (selesai 2026-11-16): daftar aset per kategori/lokasi + filter, rekap kondisi, audit per pengguna/rentang tanggal.
- [x] Laporan "aset belum memiliki kode/foto lengkap" (selesai 2026-11-16, bagian dari laporan aset + ekspor CSV).

## Belum diputuskan (Bagian 15 dokumen — perlu konfirmasi pemilik proses)

- [ ] Format kode inventaris (apakah per tahun/kategori/unit) — sekarang bebas teks unik.
- [ ] Frekuensi & aturan pengingat pemeliharaan/pengembalian (mis. H-1/H-3).
- [ ] Kanal notifikasi: aplikasi saja atau + email/WhatsApp (§15.11).
- belum: prosedur resmi pengadaan/penghapusan + dokumen bukti, format ekspor laporan, volume aset/pengguna, hosting/backup.

## Konvensi proyek (jangan sampai terlewat)

- wayfinder: `php artisan wayfinder:generate --with-form` (tanpa --with-form merusak TS).
- pint: `vendor/bin/pint --dirty --format agent` setelah ubah PHP.
- test: `php artisan test --compact` (249 test saat ini, semua lulus).
- Dev DB: `docker compose exec laravel.test php artisan migrate --force` + `db:seed --force` (seeder idempoten, DemoDataSeeder skip produksi).
- tsc/build: 6 error TS pre-existing di file admin lama — tidak disentuh.
