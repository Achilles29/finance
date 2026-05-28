# POS Printer Local Service

Service ini mengikuti pola dashboard:

- printer Bluetooth diidentifikasi dengan `mac_address`
- service Python lokal berjalan di laptop kasir
- browser POS mengirim payload ke `http://127.0.0.1:{python_port}/cetak`
- service lokal mendeteksi COM dari MAC lalu mencetak raw ESC/POS

## Alur kerja

1. `core` menyimpan master printer, route, dan template.
2. Service lokal membaca printer aktif dari endpoint bootstrap.
3. Saat kasir melakukan `Simpan Transaksi`, `Payment`, `Void`, atau `Refund`, browser menerima `print_jobs`.
4. Browser memanggil `localhost:{python_port}/cetak`.
5. Service lokal akan refresh daftar printer dari API `core` secara berkala dan menulis ulang daftar printer aktif ke `config.json` lokal.

Catatan:
- restart service tetap diperlukan bila Anda mengubah `python_port`, karena port lama sudah terikat di proses yang sedang berjalan.
- perubahan routing printer/divisi dibaca langsung dari `core`, bukan dari `config.json`.
6. Service lokal mencetak ke printer Bluetooth yang MAC-nya sesuai.

## Dependensi

```bash
pip install -r requirements.txt
```

Isi `requirements.txt` sekarang:

- `Flask`
- `flask-cors`
- `pyserial`
- `Pillow`

## Instalasi Windows

```bat
cd C:\pos_printer_agent
python -m venv .venv
.venv\Scripts\activate
python -m pip install -r requirements.txt
```

Download dari halaman panduan printer di `core`:

- `agent.py`
- `config.json`
- `requirements.txt`
- `detect_printers.py`
- `detect_windows.bat`

## Format config

```json
{
  "agent_name": "POS-PRINTER-AGENT-01",
  "retry_seconds": 10,
  "print_retry_count": 2,
  "log_file": "./agent.log",
  "api": {
    "enabled": true,
    "base_url": "https://core.namuacoffee.com",
    "endpoint": "/pos-printers/bootstrap",
    "key": "",
    "key_query_param": "key",
    "agent_name_param": "agent_name",
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

## Menjalankan service

Validasi bootstrap lebih dulu:

```bat
python agent.py --config config.json --once
```

Kalau printer aktif sudah terbaca, jalankan service:

```bat
python agent.py --config config.json
```

Service akan membuka endpoint lokal sesuai `python_port` tiap printer.

Contoh:

- `http://127.0.0.1:3000/cetak`
- `http://127.0.0.1:3001/cetak`

## Endpoint lokal

### Health check

```text
GET /health
```

### Cetak

```text
POST /cetak
Content-Type: application/json
```

Payload:

```json
{
  "text": "ISI STRUK",
  "paper_width_mm": 80
}
```

## MAC address

Format yang disarankan di master printer:

```text
86677A7B9914
```

Format dengan separator tetap diterima, misalnya:

```text
86:67:7A:7B:99:14
```

## Logo struk

Gunakan marker di payload teks:

```text
[[LOGO_URL:https://domain/logo.png]]
```

Service akan mencoba mengambil gambar lalu mengubahnya ke ESC/POS.

## Catatan implementasi

- `python_port` harus unik per role printer.
- Dua role boleh memakai MAC yang sama bila memang testing memakai satu printer fisik yang sama.
- `IP address`, `port`, dan nama spooler tidak dipakai untuk pola Bluetooth ini.
- `venv` tidak wajib, tapi tetap disarankan agar environment printer tidak bercampur dengan Python global.
