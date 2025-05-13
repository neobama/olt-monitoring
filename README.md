# Net-Mon: ZTE OLT/ONU Monitoring System

Net-Mon adalah aplikasi monitoring dan manajemen perangkat ZTE OLT/ONU berbasis Laravel. Sistem ini memudahkan operator untuk memantau status, konfigurasi, dan performa perangkat GPON secara real-time melalui web interface yang modern.

## Fitur Utama
- **Monitoring Status ONU**: Lihat status online/offline, RX power, serial number, dan info penting lain dari semua ONU di OLT.
- **Detail & Konfigurasi ONU**: Popup detail lengkap (modal) untuk setiap ONU, termasuk WAN info, model, TCONT, GEMPORT, VLAN, dan deskripsi.
- **Edit & Hapus OLT**: Manajemen data OLT (tambah, edit, hapus) langsung dari halaman utama.
- **Batch Update**: Update massal RX power dan serial number seluruh ONU.

## Kebutuhan Sistem
- PHP >= 8.1
- Composer
- Laravel >= 9.x
- SQLite/MySQL
- Akses ke perangkat ZTE OLT via Telnet

## Instalasi
1. **Clone repository**
   ```bash
   git clone https://github.com/neobama/olt-monitoring.git
   cd olt-monitoring
   ```
2. **Install dependencies**
   ```bash
   composer install
   npm install && npm run build # jika ingin build asset frontend
   ```
3. **Copy file environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. **Set konfigurasi database**
   - Edit `.env` dan sesuaikan DB_CONNECTION, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   - Default: SQLite (`database/database.sqlite`)
5. **Migrasi database**
   ```bash
   php artisan migrate
   ```
6. **Jalankan server lokal**
   ```bash
   php artisan serve
   ```

## Konfigurasi OLT
- Tambahkan OLT baru melalui menu **Add OLT**.
- Masukkan IP, port, username, password sesuai perangkat ZTE Anda.
- Setelah OLT ditambah, klik "Refresh Status" untuk mengambil data ONU.

## Fitur Monitoring
- Klik tombol **Detail** pada tabel ONU untuk melihat info lengkap dalam modal popup.
- Edit deskripsi ONU langsung dari modal.
- Edit/hapus OLT dari halaman utama.

## Pengembangan
- Kode modular: controller, service, dan view terpisah.
- Mudah dikembangkan untuk support OLT/ONU merek lain.


---

> Dibuat oleh [neobama](https://github.com/neobama) untuk kebutuhan monitoring jaringan berbasis ZTE OLT/ONU.
