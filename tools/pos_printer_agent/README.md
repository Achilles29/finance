# Namua POS Printer Local Agent

Local Agent menjembatani browser POS dengan printer fisik Bluetooth, serial, atau USB. Aplikasi Finance menyimpan koneksi, layout, dan aturan cetak di database. Agent membaca printer aktif dari API bootstrap, lalu membuka endpoint lokal untuk setiap printer.

## Cara kerja

1. Admin mengatur koneksi printer, tampilan umum, layout, dan aturan cetak di Finance.
2. Browser POS pada komputer kasir mengirim pekerjaan ke `http://127.0.0.1:<python_port>/cetak`.
3. Agent memilih printer berdasarkan data bootstrap dan mengirim teks ESC/POS, logo, serta QR fisik.
4. Layout/routing yang diubah di Finance akan di-refresh oleh agent secara berkala. Restart hanya diperlukan bila port agent diubah atau agent bermasalah.

Agent sengaja hanya mendengarkan `127.0.0.1`. Browser POS dan agent harus berjalan pada komputer kasir yang sama.

## File yang disalin ke komputer kasir

Salin folder ini secara utuh. Minimal file berikut harus ada:

- `agent.py`
- `requirements.txt`
- `config.example.json`, lalu salin menjadi `config.json`
- `run_windows.bat` atau `run_linux.sh`
- `detect_windows.bat` atau `detect_linux.sh`
- `detect_printers.py` dan `check_saved_printers.py` untuk pemeriksaan

Jangan salin `config.json` dari komputer lain tanpa memeriksa nama agent, API key, dan perangkatnya.

## Dependensi

```text
Flask>=3.0.0
flask-cors>=4.0.0
pyserial>=3.5
Pillow>=10.0.0
qrcode[pil]>=7.4.2
```

`qrcode[pil]` dan `Pillow` dipakai untuk mode QR berbasis gambar. Bila keduanya belum terpasang, agent sekarang mencoba fallback QR native ESC/POS pada printer yang mendukungnya.

## Windows

1. Install Python 3.10+ dari https://www.python.org/downloads/windows/ dan centang `Add Python to PATH`.
2. Salin folder ini, misalnya ke `C:\NamuaPosPrinterAgent`.
3. Buka Command Prompt pada folder tersebut:

```bat
cd C:\NamuaPosPrinterAgent
python -m venv .venv
.venv\Scripts\activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

4. Hubungkan dan nyalakan printer, kemudian jalankan `detect_windows.bat`.
5. Salin `config.example.json` menjadi `config.json`, lalu isi konfigurasi agent yang benar.
6. Uji bootstrap:

```bat
.venv\Scripts\python.exe agent.py --config config.json --once
```

7. Jika valid, jalankan `run_windows.bat`.
8. Untuk autostart, buat Task Scheduler dengan trigger `At log on`; Action menunjuk ke `run_windows.bat`; `Start in` menunjuk folder agent.

## Linux (Debian/Ubuntu)

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip bluez libjpeg-dev zlib1g-dev
sudo usermod -aG dialout $USER
```

Keluar lalu masuk kembali setelah menambahkan grup `dialout`.

```bash
cd /opt/namua-pos-printer-agent
python3 -m venv .venv
. .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
chmod +x run_linux.sh detect_linux.sh
./detect_linux.sh
cp config.example.json config.json
./.venv/bin/python agent.py --config config.json --once
./run_linux.sh
```

Untuk autostart, lihat contoh systemd di halaman `POS > Printer > Panduan` pada Finance.

## Format config

```json
{
  "agent_name": "POS-PRINTER-AGENT-01",
  "retry_seconds": 10,
  "print_retry_count": 2,
  "log_file": "./agent.log",
  "api": {
    "enabled": true,
    "base_url": "https://finance.example.com",
    "endpoint": "/pos/printers/bootstrap",
    "key": "",
    "key_query_param": "key",
    "agent_name_param": "agent_name",
    "refresh_seconds": 30,
    "timeout_seconds": 8
  },
  "logo": {
    "mode": "esc_star",
    "threshold": 180,
    "scale": 1.5,
    "max_height_dots": 160,
    "fetch_timeout_seconds": 10
  },
  "printers": []
}
```

## Endpoint lokal

Setiap printer aktif membuka endpoint sesuai `python_port` dari bootstrap, misalnya:

- `GET http://127.0.0.1:3000/health`
- `POST http://127.0.0.1:3000/cetak`

Contoh payload cetak:

```json
{
  "text": "ISI STRUK",
  "paper_width_mm": 80
}
```

## Marker khusus

Logo dan QR ditulis ke payload sebagai marker, kemudian diubah agent menjadi gambar ESC/POS:

```text
[[LOGO_URL:https://domain/logo.png]]
[[QRCODE:https://domain/review/abc]]
```

## Pemeriksaan masalah

- QR tidak tercetak: restart agent lebih dulu. Bila printer tidak mendukung QR native ESC/POS, jalankan kembali `python -m pip install -r requirements.txt` agar agent kembali memakai mode gambar.
- Test gagal: buka `http://127.0.0.1:<port>/health` pada komputer kasir dan periksa `agent.log`.
- Teks terlalu sempit: cocokkan `paper_width_mm` dan `chars_per_line` pada Koneksi Printer dengan printer fisik.
- Routing salah: periksa Aturan Cetak di Finance. Jangan mengubah routing di `config.json`.
- Port berubah: restart agent karena proses lama masih memegang port sebelumnya.
