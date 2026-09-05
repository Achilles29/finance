# Runbook service-auth command grup WhatsApp

Callback `wa-engine` ke Finance `Whatsapp/api_group_command` menggunakan satu
credential service khusus bernama `FINANCE_WA_ENGINE_COMMAND_TOKEN`. Dokumen dan
template repository ini sengaja tidak memuat nilainya.

## Kontrak runtime

- Finance menerima hanya HTTP `POST` pada URL callback yang dikonfigurasi.
- `wa-engine` mengirim JSON ke nilai `FINANCE_COMMAND_URL` secara persis, tanpa
  token pada query string.
- Credential hanya dikirim lewat header
  `X-Finance-Group-Command-Token`.
- Nilai `FINANCE_WA_ENGINE_COMMAND_TOKEN` yang sama dan tidak kosong wajib
  tersedia pada process environment PHP/FPM dan `wa-engine`.
- Batch 52 memakai `FINANCE_WA_ENGINE_API_TOKEN` melalui header
  `X-Finance-Wa-Engine-Token` khusus untuk arah Finance ke `wa-engine`.
  `FINANCE_WA_ENGINE_COMMAND_TOKEN` tetap hanya untuk arah callback
  `wa-engine` ke Finance. Kedua credential tidak boleh dipertukarkan dan tidak
  memiliki fallback legacy.

## Provisioning tanpa menyimpan secret di web root

1. Buat credential acak berentropi tinggi di secret manager. Jangan mencetak
   atau menyalinnya ke source, URL, tiket, log, screenshot, maupun command line.
2. Inject `FINANCE_WA_ENGINE_COMMAND_TOKEN` ke environment pool PHP-FPM melalui
   secret manager atau konfigurasi service yang berada di luar document/web
   root. Pastikan kebijakan PHP-FPM mengizinkan variable itu diteruskan ke
   worker.
3. Inject credential yang sama ke environment service/process manager
   `wa-engine`, juga di luar web root. Set `FINANCE_COMMAND_URL` ke URL endpoint
   Finance final tanpa query string.
4. Jangan menaruh credential production pada `wa-engine/.env`; file
   `.env.example` hanya template nama variable tanpa nilai secret. Loader Node
   dan launcher PHP sengaja mengabaikan key credential dari file `.env` agar
   nilainya tetap process-only.

## Cutover terkoordinasi

1. Provision kedua process environment sebelum source Batch 50 diaktifkan.
2. Reload/restart worker PHP-FPM agar environment baru terbaca.
3. Restart `wa-engine` dalam window yang sama agar kedua sisi memakai credential
   yang sama.
4. Jalankan health check callback POST dari service terkontrol tanpa mencetak
   header. Verifikasi menu dan satu laporan read-only dari grup aktif.
5. Pastikan GET/PUT/DELETE, token query, `X-Sync-Token`, credential kosong, dan
   credential salah ditolak; pastikan command mutasi rekening tetap ditolak.
6. Setelah verifikasi berhasil, lanjutkan traffic normal. Jika salah satu sisi
   belum menerima environment baru, rollback deployment source atau selesaikan
   provisioning lalu restart keduanya; jangan mengaktifkan fallback credential.

## Rotasi

Rotasi membutuhkan update terkoordinasi pada PHP/FPM dan `wa-engine`, kemudian
restart keduanya. Endpoint bersifat fail-closed dan tidak menyediakan overlap
token lama/baru, jadi lakukan rotasi dalam maintenance window singkat. Jangan
menambahkan fallback ke `wa_session.bot_api_token`, `WA_TOKEN`, query token,
`X-Sync-Token`, atau token development lokal.
