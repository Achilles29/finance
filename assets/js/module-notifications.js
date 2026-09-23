(function () {
  'use strict';
  if (window.financeModuleNotificationsBound) return;
  window.financeModuleNotificationsBound = true;
  document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-module-notify]');
    if (!button || button.disabled) return;
    event.preventDefault();
    if (!window.confirm('Kirim ringkasan pengajuan ke tujuan ' + button.dataset.moduleNotify + ' yang sudah diatur admin?')) return;
    button.disabled = true;
    let notice = button.parentElement.querySelector('[data-notification-result]');
    if (!notice) {
      notice = document.createElement('div');
      notice.dataset.notificationResult = '1';
      notice.setAttribute('role', 'status');
      notice.className = 'small text-wrap text-start w-100 mt-2';
      button.parentElement.appendChild(notice);
    }
    notice.textContent = 'Memasukkan notifikasi ke antrean…';
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(button.dataset.notifyUrl, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Procurement-Mutation-Csrf': button.dataset.notifyCsrf},
        body: JSON.stringify({channel: button.dataset.moduleNotify})
      });
      const data = await response.json();
      notice.textContent = data.message || (response.ok && data.ok ? 'Notifikasi masuk antrean.' : 'Belum dapat mengirim. Muat ulang dan periksa izin Anda.');
    } catch (error) {
      notice.textContent = 'Koneksi terputus; status antrean belum pasti. Periksa status di pengaturan kanal. Klik ulang untuk pengajuan yang sama tidak menggandakan pesan.';
    } finally {
      clearTimeout(timer);
      button.disabled = false;
    }
  });
})();
