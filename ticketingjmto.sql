-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Nov 2026 pada 10.50
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ticketingjmto`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `area_lokasi`
--

CREATE TABLE `area_lokasi` (
  `id_area` int(11) NOT NULL,
  `nama_area` varchar(50) NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `nama_gate` varchar(50) NOT NULL,
  `kode_mitra` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `area_lokasi`
--

INSERT INTO `area_lokasi` (`id_area`, `nama_area`, `nama_lokasi`, `nama_gate`, `kode_mitra`) VALUES
(1, 'TMII', '[01] - KG A', '[01] - GATE 1', 'MTR-001'),
(2, 'Ancol', '[02] - PINTU TIMUR', '[02] - GATE 2', 'MTR-002'),
(3, 'Ragunan', '[03] - PINTU UTARA', '[01] - GATE 1', 'MTR-001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mitra`
--

CREATE TABLE `mitra` (
  `kode_mitra` varchar(15) NOT NULL,
  `nama_mitra` varchar(100) NOT NULL,
  `kategori_mitra` enum('Transportasi','Wisata & Rekreasi','Retail & F&B') NOT NULL,
  `nama_pic` varchar(100) NOT NULL,
  `jabatan_pic` varchar(100) DEFAULT NULL,
  `email_pic` varchar(100) NOT NULL,
  `telepon_pic` varchar(20) NOT NULL,
  `email_mitra` varchar(100) DEFAULT NULL,
  `telepon_mitra` varchar(20) DEFAULT NULL,
  `logo_mitra` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `masa_kontrak` varchar(50) DEFAULT NULL,
  `npwp` varchar(50) DEFAULT NULL,
  `nib` varchar(50) DEFAULT NULL,
  `status_mitra` enum('Aktif','Pending','Nonaktif') DEFAULT 'Aktif',
  `status_akun` enum('Accept','Invitation') DEFAULT 'Invitation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mitra`
--

INSERT INTO `mitra` (`kode_mitra`, `nama_mitra`, `kategori_mitra`, `nama_pic`, `jabatan_pic`, `email_pic`, `telepon_pic`, `email_mitra`, `telepon_mitra`, `logo_mitra`, `alamat`, `masa_kontrak`, `npwp`, `nib`, `status_mitra`, `status_akun`, `created_at`) VALUES
('MTR-001', 'PT Jasa Marga Tollroad', 'Transportasi', 'Hendra Wijaya', NULL, 'hendra@jmto.co.id', '081234567890', NULL, NULL, NULL, 'Jl. Tol Jagorawi KM 4, Jakarta Timur', '12/01/2025 - 12/01/2026', NULL, NULL, 'Aktif', 'Accept', '2026-11-12 04:45:59'),
('MTR-002', 'PT Pembangunan Jaya Ancol', 'Wisata & Rekreasi', 'Rina Kartika', NULL, 'rina@ancol.com', '081298765432', NULL, NULL, NULL, 'Jl. Lodan Timur No. 7, Jakarta Utara', '01/03/2026 - 01/03/2027', NULL, NULL, 'Aktif', 'Accept', '2026-11-12 04:45:59'),
('MTR-003', 'Rest Area KM 57', 'Retail & F&B', 'Dedi Kurniawan', NULL, 'dedi@km57.co.id', '081355667788', NULL, NULL, NULL, 'Tol Jakarta-Cikampek KM 57', '15/05/2026 - 15/05/2027', NULL, NULL, 'Pending', 'Invitation', '2026-11-12 04:45:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perangkat`
--

CREATE TABLE `perangkat` (
  `id_perangkat` varchar(15) NOT NULL,
  `nama_perangkat` varchar(100) NOT NULL,
  `jenis_perangkat` enum('Turnstile','CCTV','Barrier Gate','Card Reader') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `id_area` int(11) NOT NULL,
  `status_perangkat` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `last_ping` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `perangkat`
--

INSERT INTO `perangkat` (`id_perangkat`, `nama_perangkat`, `jenis_perangkat`, `ip_address`, `id_area`, `status_perangkat`, `last_ping`) VALUES
('DEV-001', 'Turnstile 01', 'Turnstile', '172.16.98.11', 1, 'Aktif', '2026-11-12 11:45:59'),
('DEV-002', 'Kereta Gantung - KG A - CCTV 001', 'CCTV', '172.16.98.21', 1, 'Aktif', '2026-11-12 11:45:59'),
('DEV-003', 'Barrier Gate 01', 'Barrier Gate', '172.16.98.32', 2, 'Nonaktif', '2026-11-12 11:45:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `roles`
--

CREATE TABLE `roles` (
  `id_role` varchar(15) NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  `hak_akses` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `roles`
--

INSERT INTO `roles` (`id_role`, `nama_role`, `hak_akses`, `created_at`) VALUES
('ROL-001', 'Super Admin', 'dashboard,laporan,monitoring,manajemen', '2026-11-12 04:44:37'),
('ROL-002', 'Admin Mitra', 'dashboard,laporan,monitoring', '2026-11-12 04:44:37'),
('ROL-003', 'Operator Gate', 'monitoring', '2026-11-12 04:44:37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` varchar(30) NOT NULL,
  `waktu_transaksi` datetime NOT NULL,
  `id_area` int(11) NOT NULL,
  `penerbit` varchar(50) NOT NULL,
  `tiket_kategori` enum('Reguler','Rombongan','Voucher','Internal') NOT NULL,
  `jenis_tiket` varchar(100) NOT NULL,
  `tipe_tiket` varchar(50) NOT NULL,
  `kode_qr` varchar(100) NOT NULL,
  `foto_cctv` varchar(255) DEFAULT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `status_transaksi` enum('Berhasil','Gagal','Pending') DEFAULT 'Berhasil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id_transaksi`, `waktu_transaksi`, `id_area`, `penerbit`, `tiket_kategori`, `jenis_tiket`, `tipe_tiket`, `kode_qr`, `foto_cctv`, `nominal`, `status_transaksi`) VALUES
('TRX-20261112-001', '2026-11-12 08:15:22', 1, 'Bank DKI', 'Reguler', 'Tiket Masuk Utama', 'Perorangan', 'MPOS-20260902-TMII-0052', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=faces', 50000.00, 'Berhasil'),
('TRX-20261112-002', '2026-11-12 08:42:10', 2, 'GOERS', 'Reguler', 'Tiket Wahana', 'Terusan', 'MPOS-20260901-ANCL-0083', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop&crop=faces', 150000.00, 'Berhasil'),
('TRX-20261112-003', '2026-11-12 09:05:47', 3, 'Petugas', 'Rombongan', 'Tiket Masuk', 'Group', 'MPOS-20260902-RGN-7133', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop&crop=faces', 250000.00, 'Berhasil'),
('TRX-20261112-2165', '2026-11-12 10:26:36', 3, 'APPSKU', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-RAGU-5309', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-2240', '2026-11-12 10:26:36', 3, 'Bank DKI', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-RAGU-2011', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-3396', '2026-11-12 10:26:36', 1, 'Bank DKI', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-TMII-8194', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-3903', '2026-11-12 10:26:36', 2, 'APPSKU', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-ANCO-9327', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-4229', '2026-11-12 10:26:36', 2, 'Bank DKI', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-ANCO-4555', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-5028', '2026-11-12 10:26:36', 3, 'Petugas', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-RAGU-6761', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-5737', '2026-11-12 10:26:36', 1, 'APPSKU', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-TMII-8029', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-6010', '2026-11-12 16:28:54', 1, 'Bank DKI', 'Reguler', 'Tiket Wahana', 'Kereta Gantung', 'MPOS-20261112-TMII-2487', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-7120', '2026-11-12 10:25:26', 1, 'APPSKU', '', 'Tiket Masuk Utama', 'Kendaraan Roda 4', 'MPOS-20261112-TMII-8819', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 30000.00, 'Berhasil'),
('TRX-20261112-7281', '2026-11-12 10:26:36', 2, 'Petugas', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-ANCO-5695', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-8010', '2026-11-12 16:45:00', 2, 'APPSKU', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-ANCO-7615', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-8402', '2026-11-12 10:26:36', 1, 'APPSKU', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-TMII-6263', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil'),
('TRX-20261112-8834', '2026-11-12 10:26:36', 2, 'Bank DKI', 'Reguler', 'GUEST', 'Kereta Gantung', 'MPOS-20261112-ANCO-9564', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=300&auto=format&fit=crop&q=60', 50000.00, 'Berhasil');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `kode_user` varchar(15) DEFAULT NULL,
  `nama_user` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `id_role` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status_user` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `kode_user`, `nama_user`, `email`, `id_role`, `password`, `status_user`, `created_at`) VALUES
(1, 'USR-001', 'Admin JMTO', 'admin@jmto.co.id', 'ROL-002', '123123', 'Active', '2026-11-11 14:06:16');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `area_lokasi`
--
ALTER TABLE `area_lokasi`
  ADD PRIMARY KEY (`id_area`),
  ADD KEY `kode_mitra` (`kode_mitra`);

--
-- Indeks untuk tabel `mitra`
--
ALTER TABLE `mitra`
  ADD PRIMARY KEY (`kode_mitra`);

--
-- Indeks untuk tabel `perangkat`
--
ALTER TABLE `perangkat`
  ADD PRIMARY KEY (`id_perangkat`),
  ADD KEY `id_area` (`id_area`);

--
-- Indeks untuk tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_role`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD UNIQUE KEY `kode_qr` (`kode_qr`),
  ADD KEY `id_area` (`id_area`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `area_lokasi`
--
ALTER TABLE `area_lokasi`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `area_lokasi`
--
ALTER TABLE `area_lokasi`
  ADD CONSTRAINT `area_lokasi_ibfk_1` FOREIGN KEY (`kode_mitra`) REFERENCES `mitra` (`kode_mitra`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perangkat`
--
ALTER TABLE `perangkat`
  ADD CONSTRAINT `perangkat_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `area_lokasi` (`id_area`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `area_lokasi` (`id_area`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
