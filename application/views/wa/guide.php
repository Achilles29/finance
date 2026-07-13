<?php
$session  = (array)($session ?? []);
$waStatus = strtoupper($session['status'] ?? 'UNKNOWN');
$waPhone  = $session['phone_number'] ?? '';
?>

<div class="container-xxl py-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-book-open-line me-1"></i>Panduan WhatsApp Bot</h4>
      <p class="text-muted mb-0 small">Instalasi, konfigurasi, cara menautkan WA, dan panduan penggunaan.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= site_url('wa/settings') ?>" class="btn btn-outline-primary btn-sm">
        <i class="ri ri-settings-3-line me-1"></i>Pengaturan
      </a>
      <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-success btn-sm">
        <i class="ri ri-dashboard-line me-1"></i>Dashboard
      </a>
    </div>
  </div>

  <!-- Status Bar -->
  <?php
  $sBadge = match($waStatus) { 'CONNECTED' => 'bg-success', 'WAITING_QR' => 'bg-warning', 'DISCONNECTED' => 'bg-danger', default => 'bg-secondary' };
  $sLabel = match($waStatus) { 'CONNECTED' => 'Terhubung', 'WAITING_QR' => 'Menunggu QR', 'DISCONNECTED' => 'Terputus', default => 'Tidak Diketahui' };
  ?>
  <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
    <span class="badge <?= $sBadge ?> fs-6"><?= $sLabel ?></span>
    <div>
      Status bot saat ini: <strong><?= $sLabel ?></strong>
      <?= $waPhone ? '— Nomor terhubung: <strong>' . html_escape($waPhone) . '</strong>' : '' ?>
    </div>
    <?php if ($waStatus !== 'CONNECTED'): ?>
    <a href="#section-connect" class="btn btn-warning btn-sm ms-auto">
      <i class="ri ri-qr-code-line me-1"></i>Scan QR Sekarang →
    </a>
    <?php endif; ?>
  </div>

  <!-- Navigasi Cepat -->
  <div class="row g-2 mb-4">
    <?php $navItems = [
      ['#section-install-windows', 'ri-windows-line', 'Instalasi Windows', 'bg-label-primary'],
      ['#section-install-ubuntu',  'ri-terminal-box-line', 'Instalasi Ubuntu', 'bg-label-success'],
      ['#section-connect',         'ri-qr-code-line', 'Menautkan WA', 'bg-label-warning'],
      ['#section-broadcast',       'ri-broadcast-line', 'Broadcast', 'bg-label-danger'],
      ['#section-template',        'ri-file-text-line', 'Template', 'bg-label-info'],
      ['#section-group',           'ri-group-2-line', 'Grup WA', 'bg-label-secondary'],
    ]; ?>
    <?php foreach ($navItems as [$href, $icon, $label, $bg]): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <a href="<?= $href ?>" class="card border-0 shadow-sm text-decoration-none h-100">
        <div class="card-body text-center py-3">
          <div class="avatar avatar-md mx-auto mb-1">
            <span class="avatar-initial rounded-circle <?= $bg ?>">
              <i class="ri <?= $icon ?>"></i>
            </span>
          </div>
          <div class="small fw-semibold"><?= $label ?></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- INSTALASI WINDOWS -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-install-windows">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="ri ri-windows-line me-2"></i>Instalasi di Windows</h5>
    </div>
    <div class="card-body">

      <h6 class="fw-bold">1. Install Node.js</h6>
      <ol class="mb-3">
        <li>Download Node.js versi LTS dari <strong>nodejs.org</strong> (pilih Windows Installer .msi)</li>
        <li>Jalankan installer, centang opsi <em>"Add to PATH"</em></li>
        <li>Verifikasi instalasi di Command Prompt / PowerShell:</li>
      </ol>
      <pre class="bg-dark text-white rounded p-3 small">node --version
npm --version</pre>

      <h6 class="fw-bold mt-3">2. Setup wa-bot</h6>
      <ol class="mb-3">
        <li>Buka folder <code>wa-bot</code> di terminal/PowerShell</li>
        <li>Install dependensi:</li>
      </ol>
      <pre class="bg-dark text-white rounded p-3 small">cd C:\xampp\htdocs\wa-bot
npm install</pre>

      <h6 class="fw-bold mt-3">3. Konfigurasi Database</h6>
      <p class="small">Edit bagian <code>db</code> di awal file <code>index.js</code> sesuai kredensial MySQL Anda:</p>
      <pre class="bg-dark text-white rounded p-3 small">db: {
  host: 'localhost',
  user: 'root',
  password: 'password_anda',
  database: 'namua'
}</pre>

      <h6 class="fw-bold mt-3">4. Jalankan wa-bot</h6>
      <pre class="bg-dark text-white rounded p-3 small">node index.js</pre>
      <p class="small text-muted">Terminal akan menampilkan QR code. Scan dengan WhatsApp di HP. Setelah terhubung, bot berjalan di background.</p>

      <h6 class="fw-bold mt-3">5. (Opsional) Jalankan Otomatis dengan PM2</h6>
      <p class="small">Agar bot tetap berjalan meskipun terminal ditutup:</p>
      <pre class="bg-dark text-white rounded p-3 small">npm install -g pm2
pm2 start index.js --name wa-bot
pm2 save
pm2 startup</pre>
      <p class="small text-muted">Ikuti instruksi yang ditampilkan PM2 setelah <code>pm2 startup</code>.</p>

      <div class="alert alert-info small mt-3 mb-0">
        <i class="ri ri-information-line me-1"></i>
        <strong>Token Sinkronisasi:</strong> Secara default, wa-bot menggunakan token <code>local-dev-token</code>.
        Pastikan nilai ini sama dengan yang ada di halaman <a href="<?= site_url('wa/settings') ?>">Pengaturan WA</a>.
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- INSTALASI UBUNTU -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-install-ubuntu">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="ri ri-terminal-box-line me-2"></i>Instalasi di Ubuntu / Linux Server</h5>
    </div>
    <div class="card-body">

      <h6 class="fw-bold">1. Install Node.js via NVM (Rekomendasi)</h6>
      <pre class="bg-dark text-white rounded p-3 small"># Install NVM
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
source ~/.bashrc

# Install Node.js LTS
nvm install --lts
nvm use --lts

# Verifikasi
node --version
npm --version</pre>

      <h6 class="fw-bold mt-3">2. Install PM2 (Process Manager)</h6>
      <pre class="bg-dark text-white rounded p-3 small">npm install -g pm2</pre>

      <h6 class="fw-bold mt-3">3. Setup wa-bot</h6>
      <pre class="bg-dark text-white rounded p-3 small">cd /var/www/html/wa-bot    # atau path sesuai server Anda
npm install</pre>

      <h6 class="fw-bold mt-3">4. Konfigurasi</h6>
      <p class="small">Edit <code>index.js</code> — sesuaikan kredensial database di bagian <code>config.db</code>:</p>
      <pre class="bg-dark text-white rounded p-3 small">nano index.js
# atau
vi index.js</pre>

      <h6 class="fw-bold mt-3">5. Jalankan Pertama Kali (untuk Scan QR)</h6>
      <p class="small text-warning"><i class="ri ri-alert-line me-1"></i>Harus dijalankan di terminal interaktif agar QR code bisa dilihat atau gunakan fitur Scan QR di halaman ini.</p>
      <pre class="bg-dark text-white rounded p-3 small">node index.js</pre>
      <p class="small text-muted">Setelah scan QR dan terhubung, tekan <kbd>Ctrl+C</kbd> untuk stop, lalu jalankan dengan PM2.</p>

      <h6 class="fw-bold mt-3">6. Jalankan dengan PM2</h6>
      <pre class="bg-dark text-white rounded p-3 small"># Jalankan
pm2 start index.js --name wa-bot

# Auto-start saat server reboot
pm2 save
pm2 startup systemd
# Jalankan perintah yang ditampilkan PM2

# Cek status
pm2 status
pm2 logs wa-bot</pre>

      <h6 class="fw-bold mt-3">7. (Opsional) Systemd Service</h6>
      <p class="small">Alternatif PM2 — buat file service:</p>
      <pre class="bg-dark text-white rounded p-3 small">sudo nano /etc/systemd/system/wa-bot.service</pre>
      <pre class="bg-dark text-white rounded p-3 small">[Unit]
Description=WA Bot Finance
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/wa-bot
ExecStart=/usr/bin/node index.js
Restart=always
RestartSec=10
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target</pre>
      <pre class="bg-dark text-white rounded p-3 small">sudo systemctl daemon-reload
sudo systemctl enable wa-bot
sudo systemctl start wa-bot
sudo systemctl status wa-bot</pre>

      <div class="alert alert-warning small mt-3 mb-0">
        <i class="ri ri-alert-line me-1"></i>
        <strong>Izin folder:</strong> Pastikan folder <code>auth_info</code> di dalam <code>wa-bot/</code> dapat ditulis oleh user yang menjalankan bot:
        <pre class="mb-0 mt-1">sudo chown -R www-data:www-data /var/www/html/wa-bot/auth_info
sudo chmod 755 /var/www/html/wa-bot/auth_info</pre>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- MENAUTKAN WHATSAPP / SCAN QR -->
  <!-- ═══════════════════════════════════════════════════════ -->
  <div class="card border-0 shadow-sm mb-4" id="section-connect">
    <div class="card-header bg-warning">
      <h5 class="mb-0"><i class="ri ri-qr-code-line me-2"></i>Menautkan WhatsApp (Scan QR)</h5>
    </div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-5">
          <h6 class="fw-bold">Cara Menautkan</h6>
          <ol class="small">
            <li class="mb-2">Pastikan <strong>wa-bot sudah berjalan</strong> (cek dengan Ping Bot di halaman Pengaturan).</li>
            <li class="mb-2">Klik tombol <strong>"Muat QR Code"</strong> di bawah. QR akan muncul jika bot siap.</li>
            <li class="mb-2">Buka <strong>WhatsApp</strong> di HP → <strong>Perangkat Tertaut</strong> → <strong>Tautkan Perangkat</strong>.</li>
            <li class="mb-2">Arahkan kamera ke QR Code yang tampil.</li>
            <li class="mb-2">Tunggu beberapa detik hingga status berubah menjadi <span class="badge bg-success">Terhubung</span>.</li>
          </ol>

          <div class="alert alert-info small mb-0">
            <strong>Catatan:</strong>
            <ul class="mb-0 ps-3">
              <li>QR Code berlaku ±60 detik. Jika kadaluarsa, klik "Muat QR Code" lagi.</li>
              <li>Setelah terhubung, sesi disimpan otomatis di folder <code>auth_info/</code>. Bot tidak perlu scan ulang setelah restart.</li>
              <li>Untuk logout: hapus folder <code>auth_info/</code> dan restart bot.</li>
            </ul>
          </div>
        </div>

        <div class="col-md-7">
          <!-- QR Code Live -->
          <div class="card border shadow-none">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <span class="fw-semibold">QR Code Live</span>
              <div class="d-flex align-items-center gap-2">
                <span class="badge" id="guide-qr-status-badge" class="bg-secondary">Belum dimuat</span>
                <button class="btn btn-success btn-sm" id="guide-btn-load-qr">
                  <i class="ri ri-qr-code-line me-1"></i>Muat QR Code
                </button>
              </div>
            </div>
            <div class="card-body text-center py-4">
              <div id="guide-qr-msg" class="text-muted small mb-3">
                Klik "Muat QR Code" untuk memulai. Bot harus berjalan dan belum terhubung.
              </div>
              <div id="guide-qr-container" class="d-flex justify-content-center mb-3"></div>
              <div id="guide-qr-countdown" class="text-muted small d-none">
                <i class="ri ri-time-line me-1"></i>QR kadaluarsa dalam <span id="guide-qr-seconds">60</span>s. Refresh otomatis…
              </div>
              <div id="guide-qr-connected" class="d-none py-3">
                <i class="ri ri-checkbox-circle-line text-success" style="font-size:3rem;"></i>
                <div class="text-success fw-bold fs-5 mt-2">WhatsApp Berhasil Terhubung!</div>
                <div class="text-muted small mt-1" id="guide-qr-phone"></div>
                <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-primary btn-sm mt-3">
                  <i class="ri ri-dashboard-line me-1"></i>Buka Dashboard
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- PANDUAN PENGGUNAAN -->
  <!-- ═══════════════════════════════════════════════════════ -->

  <!-- BROADCAST -->
  <div class="card border-0 shadow-sm mb-4" id="section-broadcast">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ri ri-broadcast-line me-2"></i>Broadcast — Kirim Pesan Massal</h5>
      <a href="<?= site_url('wa/broadcast/create') ?>" class="btn btn-light btn-sm">Buat Sekarang</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="fw-bold">Apa itu Broadcast?</h6>
          <p class="small">Broadcast memungkinkan pengiriman pesan ke banyak nomor sekaligus — misalnya promosi, informasi menu baru, atau pengingat kepada pelanggan.</p>

          <h6 class="fw-bold">Langkah Membuat Broadcast</h6>
          <ol class="small">
            <li class="mb-1">Buka menu <strong>Broadcast</strong> → klik <strong>Buat Broadcast</strong></li>
            <li class="mb-1">Isi <strong>Nama Broadcast</strong> (untuk referensi internal)</li>
            <li class="mb-1">Pilih <strong>Tipe Target</strong>:
              <ul>
                <li><code>Manual</code> — input nomor HP satu per satu</li>
                <li><code>Semua Member</code> — otomatis dari database member loyalty</li>
                <li><code>Member Aktif</code> — hanya member dengan status aktif</li>
              </ul>
            </li>
            <li class="mb-1">Pilih <strong>Template Pesan</strong> atau ketik pesan custom</li>
            <li class="mb-1">Klik <strong>Simpan</strong> → akan masuk mode DRAFT</li>
            <li class="mb-1">Di halaman detail, klik <strong>Mulai Kirim</strong></li>
          </ol>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold">Format Input Nomor Manual</h6>
          <pre class="bg-light rounded p-2 small">081234567890
081234567890|Budi Santoso
6281234567890|Sari Dewi
08111222333</pre>
          <p class="small text-muted">Satu nomor per baris. Nomor <code>08xxx</code> otomatis dikonversi ke <code>62xxx</code>. Tambahkan nama setelah <code>|</code> (opsional) — nama bisa dipakai sebagai variabel <code>&#123;&#123;nama&#125;&#125;</code>.</p>

          <h6 class="fw-bold mt-2">Status Broadcast</h6>
          <table class="table table-sm small">
            <tr><td><span class="badge bg-secondary">DRAFT</span></td><td>Dibuat, belum dikirim</td></tr>
            <tr><td><span class="badge bg-warning">SENDING</span></td><td>Sedang dalam proses kirim</td></tr>
            <tr><td><span class="badge bg-success">DONE</span></td><td>Semua pesan terkirim</td></tr>
            <tr><td><span class="badge bg-danger">FAILED</span></td><td>Ada error — bisa dicoba ulang</td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- TEMPLATE -->
  <div class="card border-0 shadow-sm mb-4" id="section-template">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ri ri-file-text-line me-2"></i>Template Pesan</h5>
      <a href="<?= site_url('wa/template') ?>" class="btn btn-light btn-sm">Kelola Template</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="fw-bold">Variabel Dinamis</h6>
          <p class="small">Template mendukung variabel dengan format <code>&#123;&#123;nama_variabel&#125;&#125;</code>. Variabel akan digantikan dengan nilai sebenarnya saat pesan dikirim.</p>
          <p class="small">Variabel yang didukung:</p>
          <table class="table table-sm small">
            <tr><td><code>&#123;&#123;nama&#125;&#125;</code></td><td>Nama penerima</td></tr>
            <tr><td><code>&#123;&#123;judul_promo&#125;&#125;</code></td><td>Judul promo</td></tr>
            <tr><td><code>&#123;&#123;tanggal_promo&#125;&#125;</code></td><td>Tanggal berlaku promo</td></tr>
            <tr><td><code>&#123;&#123;deskripsi&#125;&#125;</code></td><td>Deskripsi bebas</td></tr>
          </table>
          <p class="small text-muted">Variabel bisa disesuaikan dengan nama apapun. Pastikan nama variabel konsisten di template dan di baris broadcast.</p>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold">Format WA Markdown</h6>
          <table class="table table-sm small">
            <tr><td><code>*teks*</code></td><td><strong>Bold</strong></td></tr>
            <tr><td><code>_teks_</code></td><td><em>Italic</em></td></tr>
            <tr><td><code>~teks~</code></td><td><s>Strikethrough</s></td></tr>
            <tr><td><code>```teks```</code></td><td><code>Monospace</code></td></tr>
          </table>
          <h6 class="fw-bold mt-2">Contoh Template</h6>
          <pre class="bg-light rounded p-2 small" style="white-space:pre-wrap;">Halo *&#123;&#123;nama&#125;&#125;*! 👋

Promo spesial dari *Namua Coffee*:
🎉 *&#123;&#123;judul_promo&#125;&#125;*
📅 &#123;&#123;tanggal_promo&#125;&#125;

&#123;&#123;deskripsi&#125;&#125;

_Namua Coffee_</pre>
        </div>
      </div>
    </div>
  </div>

  <!-- GRUP -->
  <div class="card border-0 shadow-sm mb-4" id="section-group">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ri ri-group-2-line me-2"></i>Grup WhatsApp</h5>
      <a href="<?= site_url('wa/group') ?>" class="btn btn-light btn-sm">Kelola Grup</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h6 class="fw-bold">Cara Mendapatkan Group JID</h6>
          <p class="small">Group JID (identitas unik grup WA) diperlukan untuk mengirim pesan ke grup tertentu dari Finance App.</p>
          <ol class="small">
            <li class="mb-1">Pastikan nomor bot <strong>sudah menjadi anggota</strong> grup yang dituju</li>
            <li class="mb-1">Kirim perintah <code>!listgroup</code> ke nomor bot via WA (dari nomor admin)</li>
            <li class="mb-1">Bot akan membalas daftar grup beserta JID-nya</li>
            <li class="mb-1">Copy JID (format: <code>120363xxx@g.us</code>) ke halaman Grup WA</li>
          </ol>
          <div class="alert alert-info small mb-0">
            <strong>Alternatif:</strong> JID sudah pre-filled untuk 3 grup utama (CAFE PUSAT, HOD NAMUA, SUPERTEAM NAMUA) sesuai konfigurasi wa-bot lama. Cek dan update jika JID berbeda.
          </div>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold">Kirim Pesan ke Grup</h6>
          <ol class="small">
            <li class="mb-1">Buka menu <strong>Grup WA</strong></li>
            <li class="mb-1">Pastikan grup memiliki <strong>Group JID</strong> yang terisi</li>
            <li class="mb-1">Klik tombol <strong>Kirim Pesan</strong> pada kartu grup</li>
            <li class="mb-1">Ketik pesan dan klik <strong>Kirim</strong></li>
          </ol>
          <h6 class="fw-bold mt-2">Label Tujuan (Purpose)</h6>
          <p class="small text-muted">Label seperti <code>OMZET</code>, <code>HOD</code>, <code>TEAM</code>, <code>PROMO</code> berguna sebagai referensi internal. Tidak berpengaruh ke pengiriman.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- TROUBLESHOOTING -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ri ri-tools-line me-2"></i>Troubleshooting</h5>
    </div>
    <div class="card-body">
      <div class="accordion" id="troubleshoot-accordion">

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts1">
              QR Code tidak muncul setelah klik "Muat QR Code"
            </button>
          </h2>
          <div id="ts1" class="accordion-collapse collapse" data-bs-parent="#troubleshoot-accordion">
            <div class="accordion-body small">
              <ul>
                <li>Pastikan wa-bot berjalan: jalankan <code>node index.js</code> di terminal</li>
                <li>Cek URL dan token di <a href="<?= site_url('wa/settings') ?>">Pengaturan WA</a> — harus sesuai dengan konfigurasi di <code>index.js</code></li>
                <li>Jika bot sudah terhubung sebelumnya (ada folder <code>auth_info/</code>), bot akan langsung CONNECTED dan tidak menampilkan QR. Hapus folder <code>auth_info/</code> jika ingin scan ulang</li>
                <li>Cek log terminal wa-bot untuk pesan error</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts2">
              Status bot CONNECTED tapi pesan gagal dikirim
            </button>
          </h2>
          <div id="ts2" class="accordion-collapse collapse" data-bs-parent="#troubleshoot-accordion">
            <div class="accordion-body small">
              <ul>
                <li>Pastikan nomor tujuan dalam format <code>62xxx</code> (tanpa tanda + atau 0 di awal)</li>
                <li>Nomor harus aktif dan terdaftar di WhatsApp</li>
                <li>Group JID harus dalam format <code>120363xxx@g.us</code> — cek apakah JID masih valid</li>
                <li>Cek log di halaman <a href="<?= site_url('wa/log') ?>">Log Pengiriman</a> untuk detail error</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts3">
              Bot terputus / disconnect terus-menerus
            </button>
          </h2>
          <div id="ts3" class="accordion-collapse collapse" data-bs-parent="#troubleshoot-accordion">
            <div class="accordion-body small">
              <ul>
                <li>WA membatasi jumlah perangkat tertaut. Pastikan akun WA tidak melebihi batas (biasanya 4 perangkat)</li>
                <li>Hindari broadcast terlalu cepat — ada jeda 500ms antar pesan sudah dikonfigurasi</li>
                <li>Jika sering disconnect, coba hapus <code>auth_info/</code> dan scan QR ulang</li>
                <li>Pastikan server memiliki koneksi internet yang stabil</li>
                <li>Di Ubuntu, cek log PM2: <code>pm2 logs wa-bot</code></li>
              </ul>
            </div>
          </div>
        </div>

        <div class="accordion-item border-0">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts4">
              Error: "Tidak dapat menghubungi WA Bot" saat Ping
            </button>
          </h2>
          <div id="ts4" class="accordion-collapse collapse" data-bs-parent="#troubleshoot-accordion">
            <div class="accordion-body small">
              <ul>
                <li>wa-bot tidak berjalan — jalankan <code>node index.js</code> atau <code>pm2 start wa-bot</code></li>
                <li>URL bot salah di pengaturan. Default: <code>http://127.0.0.1:3070</code></li>
                <li>Di server yang berbeda: pastikan port 3070 tidak diblokir firewall</li>
                <li>Token tidak cocok antara Finance App dan konfigurasi bot</li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

</div>

<!-- QR Code library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// ─── Guide page QR Code ─────────────────────────────────────
let guideQrInterval   = null;
let guideQrCountdown  = 60;
let guideQrCdTimer    = null;

function clearGuideQrTimers() {
  if (guideQrInterval)  { clearInterval(guideQrInterval);  guideQrInterval = null; }
  if (guideQrCdTimer)   { clearInterval(guideQrCdTimer);   guideQrCdTimer = null; }
}

function guideStartCountdown() {
  guideQrCountdown = 60;
  const el = document.getElementById('guide-qr-countdown');
  const sec = document.getElementById('guide-qr-seconds');
  el.classList.remove('d-none');
  sec.textContent = guideQrCountdown;
  guideQrCdTimer = setInterval(() => {
    guideQrCountdown--;
    sec.textContent = guideQrCountdown;
    if (guideQrCountdown <= 0) { clearInterval(guideQrCdTimer); el.classList.add('d-none'); }
  }, 1000);
}

function guideRenderQr(qrString) {
  const container = document.getElementById('guide-qr-container');
  container.innerHTML = '';
  new QRCode(container, { text: qrString, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
}

function guideShowConnected(phone) {
  clearGuideQrTimers();
  document.getElementById('guide-qr-container').innerHTML = '';
  document.getElementById('guide-qr-countdown').classList.add('d-none');
  document.getElementById('guide-qr-msg').classList.add('d-none');
  const el = document.getElementById('guide-qr-connected');
  el.classList.remove('d-none');
  if (phone) document.getElementById('guide-qr-phone').textContent = '📱 ' + phone;
  const badge = document.getElementById('guide-qr-status-badge');
  badge.className = 'badge bg-success';
  badge.textContent = 'Terhubung';
}

function guideFetchQr() {
  fetch('<?= site_url('wa/api/qr') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
      const status = (data.status || 'UNKNOWN').toUpperCase();
      const badge  = document.getElementById('guide-qr-status-badge');
      const labelMap = { CONNECTED: 'Terhubung', WAITING_QR: 'Menunggu QR', DISCONNECTED: 'Terputus', UNKNOWN: 'Tidak Diketahui' };
      const classMap = { CONNECTED: 'bg-success', WAITING_QR: 'bg-warning text-dark', DISCONNECTED: 'bg-danger', UNKNOWN: 'bg-secondary' };
      badge.className = 'badge ' + (classMap[status] || 'bg-secondary');
      badge.textContent = labelMap[status] || status;

      if (status === 'CONNECTED') {
        guideShowConnected(data.phone || '');
        return;
      }
      if (status === 'WAITING_QR' && data.qr) {
        document.getElementById('guide-qr-msg').textContent = 'Scan dengan WhatsApp di HP Anda → Perangkat Tertaut → Tautkan Perangkat';
        clearInterval(guideQrCdTimer);
        guideStartCountdown();
        guideRenderQr(data.qr);
      } else {
        document.getElementById('guide-qr-msg').textContent = 'Bot belum dalam mode QR. Pastikan wa-bot berjalan dan belum terhubung.';
        document.getElementById('guide-qr-container').innerHTML = '';
      }
    })
    .catch(() => {
      document.getElementById('guide-qr-msg').textContent = 'Tidak dapat menghubungi WA Bot.';
    });
}

document.getElementById('guide-btn-load-qr')?.addEventListener('click', function () {
  document.getElementById('guide-qr-connected').classList.add('d-none');
  document.getElementById('guide-qr-msg').classList.remove('d-none');
  document.getElementById('guide-qr-msg').textContent = 'Memuat QR Code…';
  clearGuideQrTimers();
  guideFetchQr();
  guideQrInterval = setInterval(guideFetchQr, 5000);
});

// Auto-load QR jika status belum connected
<?php if ($waStatus !== 'CONNECTED'): ?>
// Status saat ini bukan CONNECTED — auto-muat QR setelah 1 detik
setTimeout(() => {
  const btn = document.getElementById('guide-btn-load-qr');
  if (btn) btn.click();
}, 1000);
<?php endif; ?>
</script>
