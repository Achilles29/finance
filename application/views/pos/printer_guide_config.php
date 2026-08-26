<?php $printerDownloadUrl = static function (string $key): string { return site_url('pos/printers/download/' . rawurlencode($key)); }; ?>
<style>
  .printer-guide-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(340px,.9fr);gap:1rem}.printer-guide-card{height:100%}.printer-guide-step{display:grid;grid-template-columns:34px minmax(0,1fr);gap:.75rem;padding:.8rem 0;border-bottom:1px solid #eee0d8}.printer-guide-step:last-child{border-bottom:0}.printer-guide-number{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#a80e27;color:#fff;font-weight:900}.printer-guide-code{margin:.65rem 0 0;padding:.75rem .85rem;overflow:auto;border-radius:10px;background:#24272d;color:#fff6ed;font:12px/1.55 Consolas,"Courier New",monospace;white-space:pre}.printer-guide-package{padding:.8rem;border:1px solid #ecd6cb;border-radius:12px;background:#fff9f5}.printer-guide-package .btn{width:100%;justify-content:center}.printer-guide-file{display:flex;align-items:center;flex-wrap:wrap;gap:.45rem .6rem;padding:.62rem 0;border-bottom:1px dashed #eadbd3}.printer-guide-file:last-child{border-bottom:0}.printer-guide-file strong{min-width:6rem;font-size:.78rem;color:#785647}.printer-guide-file-link{display:inline-flex;align-items:center;gap:.3rem;padding:.24rem .45rem;border:1px solid #ebcfc5;border-radius:.45rem;background:#fff;color:#a80e27;text-decoration:none;font-size:.82rem;font-weight:800}.printer-guide-file-link:hover{border-color:#a80e27;background:#fff4f1;color:#7f091e}.printer-guide-file-link code{font-size:.78rem;color:inherit}.printer-guide-alert{padding:.85rem 1rem;border:1px solid #ead6bd;border-radius:12px;background:#fff8e9;color:#755b44;font-size:.88rem;line-height:1.55}.printer-guide-table{margin:0}.printer-guide-table th{width:36%;white-space:normal}.printer-guide-table td{line-height:1.5}.printer-guide-links a{display:inline-flex;align-items:center;margin:.2rem .25rem .2rem 0}@media(max-width:991.98px){.printer-guide-grid{grid-template-columns:1fr}.printer-guide-table th{width:42%}}
</style>
<div class="container-xxl py-3 print-config-page">
  <div class="fin-page-header mb-3"><div><h4 class="fin-page-title mb-1">Panduan Printer POS</h4><p class="fin-page-subtitle mb-0">Panduan pemasangan agent, pengaturan cetak, QR ulasan, dan pemeriksaan masalah di Windows maupun Linux.</p></div></div>
  <?php $this->load->view('pos/_printer_config_common', ['printer_config_tab' => 'guide']); ?>

  <div class="printer-guide-alert mb-3"><strong>Prinsip kerja:</strong> aplikasi Finance menyimpan aturan cetak di database. Komputer kasir menjalankan <strong>Local Printer Agent</strong> untuk menerima perintah dan meneruskannya ke printer fisik. Jadi konfigurasi bisa diatur dari halaman POS, sedangkan kabel, Bluetooth, dan printer tetap ditangani oleh komputer kasir.</div>

  <div class="printer-guide-grid mb-3">
    <section class="card print-config-card printer-guide-card"><div class="card-body"><div class="print-config-kicker">Urutan pengaturan</div><h5 class="mb-2">Lakukan satu kali, dari atas ke bawah</h5>
      <div class="printer-guide-step"><div class="printer-guide-number">1</div><div><strong>Pasang Local Printer Agent pada komputer yang dekat dengan printer.</strong><div class="text-muted small mt-1">Agent wajib menyala agar POS bisa mencetak. QR pada cetakan fisik juga dibuat oleh agent, bukan oleh internet browser.</div></div></div>
      <div class="printer-guide-step"><div class="printer-guide-number">2</div><div><strong>Daftarkan perangkat di Koneksi Printer.</strong><div class="text-muted small mt-1">Satu koneksi berarti satu printer fisik. Isi nama, agent, port, lebar kertas, jumlah salinan, potong kertas, dan laci kas hanya di sini.</div></div></div>
      <div class="printer-guide-step"><div class="printer-guide-number">3</div><div><strong>Isi Tampilan Umum.</strong><div class="text-muted small mt-1">Atur nama usaha, alamat, logo, Wi-Fi, dan footer sekali saja. Jangan menggandakan data tersebut di setiap layout.</div></div></div>
      <div class="printer-guide-step"><div class="printer-guide-number">4</div><div><strong>Atur Layout Dokumen.</strong><div class="text-muted small mt-1">Pilih data apa yang tampil pada struk, KOT, void, refund, atau tutup kasir. Gunakan preview kertas sebelum mengirim test fisik.</div></div></div>
      <div class="printer-guide-step"><div class="printer-guide-number">5</div><div><strong>Hubungkan melalui Aturan Cetak.</strong><div class="text-muted small mt-1">Pilih kapan dokumen dicetak, lokasi/divisi, koneksi, layout, dan mode: <strong>Off</strong>, <strong>Cetak otomatis</strong>, atau <strong>Tanya dulu</strong>.</div></div></div>
      <div class="printer-guide-step"><div class="printer-guide-number">6</div><div><strong>Aktifkan QR ulasan bila diperlukan.</strong><div class="text-muted small mt-1">Masuk ke <a href="<?= site_url('pos/customer-reviews') ?>">Ulasan Pelanggan</a>, aktifkan QR pada struk, lalu centang QR ulasan pada layout <em>Struk Pembayaran</em>. QR area untuk ditempel bisa dicetak dari halaman yang sama.</div></div></div>
    </div></section>
    <aside class="d-grid gap-3">
      <section class="card print-config-card"><div class="card-body"><div class="print-config-kicker">Folder yang dibutuhkan</div><h5 class="mb-2">Unduh lalu salin ke komputer kasir</h5><p class="small text-muted">Paket lengkap berisi program agent dan contoh konfigurasi. File <code>config.json</code> aktif tidak ikut paket karena tiap komputer kasir dapat memakai agent dan kunci berbeda.</p>
        <div class="printer-guide-package mb-2"><a class="btn btn-primary btn-sm" href="<?= $printerDownloadUrl('agent_bundle') ?>"><i class="ri-folder-zip-line me-1"></i>Unduh Paket Agent Lengkap (.zip)</a><div class="small text-muted mt-2">Ekstrak folder <code>pos_printer_agent</code>, lalu ikuti panduan Windows atau Linux di bawah.</div></div>
        <div class="printer-guide-file"><strong>Wajib</strong><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('agent_py') ?>" download><i class="ri-download-2-line"></i><code>agent.py</code></a><span class="small text-muted">Program agent</span></div>
        <div class="printer-guide-file"><strong>Wajib</strong><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('requirements') ?>" download><i class="ri-download-2-line"></i><code>requirements.txt</code></a><span class="small text-muted">Flask, serial, Pillow, dan QR</span></div>
        <div class="printer-guide-file"><strong>Mulai dari</strong><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('config_example') ?>" download><i class="ri-download-2-line"></i><code>config.example.json</code></a><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('config_json') ?>" download><i class="ri-download-2-line"></i><code>config.json dari Finance</code></a><span class="small text-muted">Ambil file aktif setelah koneksi printer disimpan</span></div>
        <div class="printer-guide-file"><strong>Windows</strong><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('run_windows') ?>" download><i class="ri-download-2-line"></i><code>run_windows.bat</code></a><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('detect_windows') ?>" download><i class="ri-download-2-line"></i><code>detect_windows.bat</code></a><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('detect_py') ?>" download><i class="ri-download-2-line"></i><code>detect_printers.py</code></a></div>
        <div class="printer-guide-file"><strong>Linux</strong><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('run_linux') ?>" download><i class="ri-download-2-line"></i><code>run_linux.sh</code></a><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('detect_linux') ?>" download><i class="ri-download-2-line"></i><code>detect_linux.sh</code></a></div>
        <div class="printer-guide-file"><strong>Pemeriksaan</strong><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('check_saved_printers') ?>" download><i class="ri-download-2-line"></i><code>check_saved_printers.py</code></a><a class="printer-guide-file-link" href="<?= $printerDownloadUrl('readme') ?>" download><i class="ri-download-2-line"></i><code>README.md</code></a></div>
      </div></section>
      <section class="card print-config-card"><div class="card-body"><div class="print-config-kicker">Keperluan terbaru</div><h5 class="mb-2">Yang harus tersedia</h5><ul class="small text-muted mb-0 ps-3"><li class="mb-2">Python 3.10 atau lebih baru.</li><li class="mb-2">Akses ke printer USB, serial, atau Bluetooth yang sudah berfungsi dari sistem operasi.</li><li class="mb-2">Browser POS dan agent harus berjalan pada komputer kasir yang sama. Agent sengaja hanya membuka <code>127.0.0.1</code> demi keamanan.</li><li class="mb-2"><code>qrcode[pil]</code> dan <code>Pillow</code> dari <code>requirements.txt</code> tetap disarankan agar agent bisa mencetak QR sebagai gambar. Bila belum terpasang, agent terbaru akan mencoba fallback QR native ESC/POS pada printer yang mendukung.</li><li>Layout layar memakai aset QR lokal dari aplikasi, sehingga poster dan preview tidak menunggu layanan QR pihak ketiga.</li></ul></div></section>
    </aside>
  </div>

  <div class="row g-3 mb-3">
    <section class="col-xl-6"><div class="card print-config-card h-100"><div class="card-body"><div class="print-config-kicker">Windows</div><h5 class="mb-3">Pemasangan di komputer kasir Windows</h5>
      <ol class="small text-muted ps-3 mb-0"><li class="mb-2">Unduh Python dari <a href="https://www.python.org/downloads/windows/" target="_blank" rel="noopener">python.org</a>. Saat memasang, centang <strong>Add Python to PATH</strong>.</li><li class="mb-2">Salin folder agent menjadi misalnya <code>C:\NamuaPosPrinterAgent</code>.</li><li class="mb-2">Buka Command Prompt di folder tersebut, lalu jalankan:</li></ol>
      <pre class="printer-guide-code">cd C:\NamuaPosPrinterAgent
python -m venv .venv
.venv\Scripts\activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt</pre>
      <ol class="small text-muted ps-3 mb-0" start="4"><li class="mb-2">Jalankan <code>detect_windows.bat</code> untuk membantu melihat printer/port. Pastikan printer sudah terhubung dan menyala.</li><li class="mb-2">Salin <code>config.example.json</code> menjadi <code>config.json</code>, lalu isi host, port, API key, dan daftar perangkat sesuai hasil deteksi.</li><li class="mb-2">Uji tanpa mencetak transaksi nyata:</li></ol>
      <pre class="printer-guide-code">.venv\Scripts\python.exe agent.py --config config.json --once</pre>
      <ol class="small text-muted ps-3 mb-0" start="7"><li class="mb-2">Jalankan terus-menerus dengan <code>run_windows.bat</code>.</li><li>Untuk otomatis saat komputer menyala, buat tugas di <strong>Task Scheduler</strong>: Trigger <em>At log on</em>, Action menunjuk ke <code>run_windows.bat</code>, dan <em>Start in</em> ke folder agent.</li></ol>
    </div></div></section>
    <section class="col-xl-6"><div class="card print-config-card h-100"><div class="card-body"><div class="print-config-kicker">Linux</div><h5 class="mb-3">Pemasangan di komputer kasir Linux</h5>
      <ol class="small text-muted ps-3 mb-0"><li class="mb-2">Salin folder agent, misalnya ke <code>/opt/namua-pos-printer-agent</code>.</li><li class="mb-2">Pasang Python dan kebutuhan sistem:</li></ol>
      <pre class="printer-guide-code">sudo apt update
sudo apt install -y python3 python3-venv python3-pip bluez libjpeg-dev zlib1g-dev
sudo usermod -aG dialout $USER</pre>
      <p class="small text-muted">Setelah menambahkan grup <code>dialout</code>, keluar lalu masuk kembali agar akses perangkat serial/USB aktif.</p>
      <ol class="small text-muted ps-3 mb-0" start="3"><li class="mb-2">Buat virtual environment dan pasang dependensi:</li></ol>
      <pre class="printer-guide-code">cd /opt/namua-pos-printer-agent
python3 -m venv .venv
. .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
chmod +x run_linux.sh detect_linux.sh
./detect_linux.sh</pre>
      <ol class="small text-muted ps-3 mb-0" start="4"><li class="mb-2">Salin <code>config.example.json</code> menjadi <code>config.json</code>, lalu isi konfigurasi perangkat.</li><li class="mb-2">Uji agent:</li></ol>
      <pre class="printer-guide-code">./.venv/bin/python agent.py --config config.json --once
./run_linux.sh</pre>
      <p class="small text-muted mb-0">Untuk menjalankan saat boot, gunakan systemd. Isi <code>User=</code>, folder kerja, dan path agent sesuai komputer Anda.</p>
    </div></div></section>
  </div>

  <section class="card print-config-card mb-3"><div class="card-body"><div class="print-config-kicker">Autostart Linux dengan systemd</div><h5 class="mb-2">Contoh service yang aman</h5><p class="small text-muted">Buat file <code>/etc/systemd/system/namua-pos-printer.service</code>, lalu sesuaikan nama user dan lokasi folder bila berbeda.</p>
    <pre class="printer-guide-code">[Unit]
Description=Namua POS Printer Agent
After=network.target

[Service]
Type=simple
User=namua
WorkingDirectory=/opt/namua-pos-printer-agent
ExecStart=/opt/namua-pos-printer-agent/.venv/bin/python /opt/namua-pos-printer-agent/agent.py --config /opt/namua-pos-printer-agent/config.json
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

sudo systemctl daemon-reload
sudo systemctl enable --now namua-pos-printer
sudo systemctl status namua-pos-printer</pre>
  </div></section>

  <section class="card print-config-card"><div class="card-body"><div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-3"><div><div class="print-config-kicker">Pemeriksaan & masalah umum</div><h5 class="mb-0">Baca ini sebelum mengubah data secara acak</h5></div><div class="printer-guide-links"><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('pos/printers/preview-live') ?>">Buka Preview</a><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('pos/printers/monitor') ?>">Buka Monitor</a><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('pos/customer-reviews') ?>">Buka Ulasan Pelanggan</a></div></div>
    <div class="table-responsive"><table class="table table-sm printer-guide-table"><thead><tr><th>Gejala</th><th>Langkah pemeriksaan</th></tr></thead><tbody>
      <tr><th>Preview atau poster QR kosong</th><td>Lakukan hard refresh browser dengan <code>Ctrl + F5</code>. Pastikan file aplikasi <code>assets/vendor/qrcodejs/qrcode.min.js</code> dan <code>assets/js/pos-local-qr.js</code> ikut terunggah saat deploy.</td></tr>
      <tr><th>QR ada di preview, tetapi tidak keluar di kertas</th><td>Restart agent dulu agar fallback QR native ESC/POS ikut aktif. Jika printer tetap tidak mendukung QR native, di komputer agent jalankan ulang <code>python -m pip install -r requirements.txt</code>, lalu restart agent. Pastikan <code>qrcode[pil]</code> dan <code>Pillow</code> tidak gagal dipasang.</td></tr>
      <tr><th>QR tidak tampil pada struk</th><td>Pastikan dua saklar aktif: <strong>QR pada struk</strong> di Ulasan Pelanggan dan <strong>QR ulasan pelanggan pada struk</strong> di Layout Dokumen. QR memang tidak tampil di KOT/tiket produksi.</td></tr>
      <tr><th>Test printer gagal</th><td>Pastikan agent aktif, host/port pada Koneksi Printer benar, browser dapat menjangkau agent, dan printer tercatat pada <code>config.json</code>. Lihat detail percobaan di Monitor Cetak.</td></tr>
      <tr><th>Hasil sempit atau teks terpotong</th><td>Periksa lebar kertas dan jumlah karakter pada <strong>Koneksi Printer</strong>. Preview menghormati pengaturan tersebut; 58 mm dan 80 mm harus memakai nilai yang sesuai printer fisik.</td></tr>
      <tr><th>Kasir tidak ingin selalu mencetak</th><td>Atur mode pada Aturan Cetak menjadi <strong>Tanya dulu</strong>. Transaksi tetap tersimpan; kasir hanya memilih apakah kertas perlu dicetak.</td></tr>
    </tbody></table></div>
  </div></section>
</div>
