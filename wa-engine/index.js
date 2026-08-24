/**
 * wa-engine/index.js
 * Engine WhatsApp untuk Finance App (Baileys + MySQL)
 *
 * Jalankan dari dalam folder finance/wa-engine/:
 *   npm install   ← hanya pertama kali / setelah update
 *   node index.js
 *
 * Konfigurasi via environment variable (opsional, ada default semua):
 *   WA_PORT    port internal HTTP (default 3070)
 *   WA_TOKEN   token auth API (default local-dev-token)
 *   DB_HOST    host MySQL (default localhost)
 *   DB_USER    user MySQL (default root)
 *   DB_PASS    password MySQL
 *   DB_NAME    nama database (default db_finance)
 */

'use strict';

console.log('🚀 wa-engine dimulai...');

const http  = require('http');
const fs    = require('fs');
const path  = require('path');
const mysql = require('mysql2/promise');
const pino  = require('pino');

// libsignal logs full session objects when it rotates a ratchet. Those objects
// contain cryptographic material, so retain only an innocuous event marker.
const sensitiveLibsignalLog = /^(?:Closing session:|Opening session:|Removing old closed session:|Session already closed)/;
for (const method of ['info', 'warn']) {
  const original = console[method].bind(console);
  console[method] = (...args) => {
    if (sensitiveLibsignalLog.test(String(args[0] || ''))) {
      original(`ℹ libsignal: ${String(args[0]).replace(/:$/, '')} (detail disembunyikan)`);
      return;
    }
    original(...args);
  };
}

// Baileys 7 is ESM-only. Keep this engine CommonJS so the surrounding
// process management remains unchanged, then load the transport at startup.
let makeWASocket;
let useMultiFileAuthState;
let DisconnectReason;
let fetchLatestBaileysVersion;

async function loadBaileys() {
  const baileys = await import('@whiskeysockets/baileys');
  makeWASocket = baileys.default || baileys.makeWASocket;
  useMultiFileAuthState = baileys.useMultiFileAuthState;
  DisconnectReason = baileys.DisconnectReason;
  fetchLatestBaileysVersion = baileys.fetchLatestBaileysVersion;

  if (typeof makeWASocket !== 'function'
    || typeof useMultiFileAuthState !== 'function'
    || typeof fetchLatestBaileysVersion !== 'function') {
    throw new Error('Modul Baileys tidak memuat API koneksi WhatsApp yang diperlukan.');
  }
}

// ─── Load .env sederhana ───────────────────────────────────
try {
  const envFile = path.join(__dirname, '.env');
  if (fs.existsSync(envFile)) {
    const lines = fs.readFileSync(envFile, 'utf8').split(/\r?\n/);
    for (const rawLine of lines) {
      const line = String(rawLine || '').trim();
      if (!line || line.startsWith('#')) continue;
      const eqPos = line.indexOf('=');
      if (eqPos <= 0) continue;
      const key = line.slice(0, eqPos).trim();
      const value = line.slice(eqPos + 1).trim();
      if (!key) continue;
      if (typeof process.env[key] === 'undefined' || process.env[key] === '') {
        process.env[key] = value;
      }
    }
  }
} catch (err) {
  console.warn('⚠️  Gagal membaca .env:', err?.message || err);
}

// ─── Konfigurasi ────────────────────────────────────────────
const SYNC_PORT  = Number(process.env.WA_PORT  || 3070);
const SYNC_TOKEN = String(process.env.WA_TOKEN || 'local-dev-token');
const FINANCE_COMMAND_URL = String(process.env.FINANCE_COMMAND_URL || 'https://core.namuacoffee.com/wa/api/group-command');

const dbConfig = {
  host:             process.env.DB_HOST || '127.0.0.1',
  user:             process.env.DB_USER || 'root',
  password:         process.env.DB_PASS || '',
  database:         process.env.DB_NAME || 'db_finance',
  waitForConnections: true,
  connectionLimit:  5,
};

// ─── State ──────────────────────────────────────────────────
let db            = null;
let currentSock   = null;
let botStatus     = 'UNKNOWN';
let botPhone      = null;
let latestQr      = null;
let isStarting    = false;
let reconnectTimer = null;
let reconnectDelay = 3000;
// Baileys can receive a retry receipt when a recipient cannot decrypt an
// outgoing message. Keep the original payload so Baileys can re-encrypt it
// with fresh recipient keys instead of silently dropping that retry.
const outboundMessages = new Map();
const outboundAcknowledgementWaiters = new Map();
const outboundMessageStatuses = new Map();
const OUTBOUND_MESSAGE_TTL_MS = 2 * 60 * 60 * 1000;
const OUTBOUND_MESSAGE_LIMIT = 512;

// ─── DB ─────────────────────────────────────────────────────
async function getDb() {
  if (!db) db = mysql.createPool(dbConfig);
  return db;
}

async function updateSessionDb(status, phone = null) {
  try {
    const pool = await getDb();
    await pool.query(
      `UPDATE wa_session SET status = ?, phone_number = ?, last_ping_at = NOW() WHERE id = 1`,
      [status, phone]
    );
  } catch (err) {
    // tabel belum ada atau koneksi gagal — lanjut saja
    console.warn('⚠️  wa_session update gagal:', err?.code || err?.message);
  }
}

async function updateSessionQr(qr) {
  try {
    const pool = await getDb();
    await pool.query(
      `UPDATE wa_session
          SET qr_data = ?,
              last_ping_at = NOW()
        WHERE id = 1`,
      [qr]
    );
  } catch (err) {
    console.warn('⚠️  wa_session QR update gagal:', err?.code || err?.message);
  }
}

async function clearSessionQr() {
  try {
    const pool = await getDb();
    await pool.query(
      `UPDATE wa_session
          SET qr_data = NULL,
              last_ping_at = NOW()
        WHERE id = 1`
    );
  } catch (err) {
    console.warn('⚠️  wa_session QR clear gagal:', err?.code || err?.message);
  }
}

// ─── Bot state helpers ───────────────────────────────────────
function setStatus(status, phone = null) {
  botStatus = status;
  botPhone = status === 'CONNECTED' ? phone : null;
  // Simpan QR terakhir selama sesi belum benar-benar CONNECTED / logout,
  // agar panel web masih bisa menampilkan QR saat koneksi WA fluktuatif.
  if (status === 'CONNECTED') latestQr = null;
  updateSessionDb(status, phone).catch(() => {});
}

// ─── HTTP helpers ────────────────────────────────────────────
function readBody(req) {
  return new Promise((resolve) => {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => resolve(body));
  });
}

function jsonReply(res, code, data) {
  res.writeHead(code, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(data));
}

function buildOutgoingMessage(payload) {
  const message = String(payload.message || '').trim();
  const imagePath = String(payload.image_path || '').trim();

  if (imagePath) {
    const resolvedPath = path.resolve(imagePath);
    if (!fs.existsSync(resolvedPath)) {
      const err = new Error('File gambar tidak ditemukan.');
      err.httpCode = 400;
      throw err;
    }
    return {
      content: {
        image: { url: resolvedPath },
        caption: message || undefined,
      },
      hasContent: true,
    };
  }

  return {
    content: { text: message },
    hasContent: message !== '',
  };
}

function extractIncomingText(message) {
  if (!message) return '';
  if (message.conversation) return String(message.conversation || '');
  if (message.extendedTextMessage?.text) return String(message.extendedTextMessage.text || '');
  if (message.imageMessage?.caption) return String(message.imageMessage.caption || '');
  if (message.videoMessage?.caption) return String(message.videoMessage.caption || '');
  return '';
}

function normalizeIncomingCommand(text) {
  const raw = String(text || '').trim();
  if (!raw) return '';
  const normalized = raw.replace(/^[!\/.#]+/, '').trim().replace(/\s+/g, ' ').toLowerCase();
  const first = normalized.split(' ')[0] || '';
  const known = ['menu', 'help', 'bantuan', 'laporan', 'omzet', 'belanja', 'adjustment', 'penyesuaian', 'pengajuan', 'po', 'sr', 'keuangan', 'saldo', 'rekening', 'kas', 'mutasi', 'hutang', 'stok', 'stock', 'pos', 'refund', 'void', 'top', 'absen', 'batch', 'queue', 'estimasi'];
  const hasReportKeyword = /(omzet|belanja|purchase|adjustment|penyesuaian|pengajuan|keuangan|estimasi|financial\s+estimation|saldo|rekening|mutasi|hutang|belum\s+bayar|belum\s+paid|stok\s+kritis|stock\s+minus|stok\s+minus|stock\s+missmatch|stok\s+missmatch|stock\s+mismatch|stok\s+mismatch|pos\s+pending|queue\s+pos|pos\s+queue|refund\s+hari\s+ini|void\s+hari\s+ini|top\s+produk|produk\s+terbanyak|absen\s+bolong|pengajuan\s+absen\s+pending|absen\s+pending|batch\s+gagal|\bkas\b|\bpo\s*\/?\s*sr\b|\bpo\b|\bsr\b)/.test(normalized);
  if (/^[!\/.#]/.test(raw) || known.includes(first) || normalized.startsWith('po sr') || hasReportKeyword) {
    return normalized;
  }
  return '';
}

async function buildGroupCommandReply(groupJid, command) {
  const url = `${FINANCE_COMMAND_URL}?token=${encodeURIComponent(SYNC_TOKEN)}`;
  const resp = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Sync-Token': SYNC_TOKEN,
    },
    body: JSON.stringify({ group_jid: groupJid, command }),
  });
  const data = await resp.json().catch(() => null);
  if (!data || !data.ok || !data.message) {
    return '';
  }
  return String(data.message || '').trim();
}

async function sendMessageWithTimeout(jid, content, timeoutMs = 60000) {
  // Connection can close after the HTTP endpoint has accepted a request.
  // Check again immediately before every personal/group send.
  if (!currentSock || botStatus !== 'CONNECTED') {
    const err = new Error('Bot tidak terhubung. Pengiriman dihentikan.');
    err.httpCode = 503;
    throw err;
  }
  let timer = null;
  try {
    return await Promise.race([
      currentSock.sendMessage(jid, content),
      new Promise((_, reject) => {
        timer = setTimeout(() => {
          const err = new Error('Timeout mengirim pesan ke WhatsApp. Coba ulangi setelah koneksi stabil.');
          err.httpCode = 504;
          reject(err);
        }, timeoutMs);
      }),
    ]);
  } finally {
    if (timer) clearTimeout(timer);
  }
}

function pruneOutboundMessages() {
  const expiresAt = Date.now() - OUTBOUND_MESSAGE_TTL_MS;
  for (const [id, row] of outboundMessages) {
    if (row.storedAt < expiresAt || outboundMessages.size > OUTBOUND_MESSAGE_LIMIT) {
      outboundMessages.delete(id);
    }
  }
  for (const [id, row] of outboundMessageStatuses) {
    if (row.updatedAt < expiresAt) outboundMessageStatuses.delete(id);
  }
}

function rememberOutboundMessage(message) {
  const id = String(message?.key?.id || '');
  const body = message?.message;
  if (!id || !body) return;

  outboundMessages.set(id, { message: body, storedAt: Date.now() });
  pruneOutboundMessages();
}

async function getOutboundMessage(key) {
  const id = String(key?.id || '');
  const message = outboundMessages.get(id)?.message;
  console.log(message
    ? `↻ WhatsApp meminta retry enkripsi: ${id}`
    : `⚠️ WhatsApp meminta retry, tetapi payload tidak ditemukan: ${id}`);
  return message;
}

function deliveryFailure(messageId, detail = '') {
  const suffix = detail ? ` Kode WhatsApp: ${detail}.` : '';
  const err = new Error(`WhatsApp menolak pengiriman pesan personal.${suffix}`);
  err.httpCode = 502;
  err.messageId = messageId;
  err.accountRestricted = /account\s+(?:has\s+been\s+)?restricted/i.test(String(detail || ''));
  return err;
}

function rememberOutboundStatus(messageId, status, detail = '') {
  if (!messageId || !Number.isFinite(Number(status))) return;
  const normalizedStatus = Number(status);
  const normalizedDetail = String(detail || '').trim();
  const previous = outboundMessageStatuses.get(messageId);
  // Receipts can arrive out of order (for example DELIVERY_ACK before a
  // delayed SERVER_ACK). Never downgrade an already confirmed delivery.
  const storedStatus = previous
    && Number(previous.status) >= 3
    && normalizedStatus > 0
    && normalizedStatus < Number(previous.status)
    ? Number(previous.status)
    : normalizedStatus;
  outboundMessageStatuses.set(messageId, {
    status: storedStatus,
    detail: storedStatus === Number(previous?.status) ? previous.detail : normalizedDetail,
    updatedAt: Date.now(),
  });
  console.log(`ℹ Status pesan WhatsApp ${messageId}: ${normalizedStatus}${normalizedDetail ? ` (${normalizedDetail})` : ''}`);
  const waiter = outboundAcknowledgementWaiters.get(messageId);
  // SERVER_ACK=2 means WhatsApp has accepted the message. DELIVERY_ACK=3
  // depends on the recipient device and may arrive much later, so requiring
  // it here turns valid sends into false failures and stalls bulk queues.
  if (waiter && normalizedStatus >= 2) {
    clearTimeout(waiter.timer);
    outboundAcknowledgementWaiters.delete(messageId);
    waiter.resolve({
      receiptStatus: normalizedStatus,
      deliveryConfirmed: normalizedStatus >= 3,
    });
  } else if (waiter && normalizedStatus === 0) {
    clearTimeout(waiter.timer);
    outboundAcknowledgementWaiters.delete(messageId);
    waiter.reject(deliveryFailure(messageId, normalizedDetail));
  }
}

function waitForServerAcknowledgement(messageId, timeoutMs = 8000) {
  const known = outboundMessageStatuses.get(messageId);
  if (known && known.status >= 2) {
    return Promise.resolve({
      receiptStatus: Number(known.status),
      deliveryConfirmed: Number(known.status) >= 3,
    });
  }
  if (known && known.status === 0) return Promise.reject(deliveryFailure(messageId, known.detail));

  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      outboundAcknowledgementWaiters.delete(messageId);
      const err = new Error('WhatsApp belum memberi konfirmasi penerimaan server. Status pengiriman belum pasti.');
      err.httpCode = 504;
      reject(err);
    }, timeoutMs);
    outboundAcknowledgementWaiters.set(messageId, { resolve, reject, timer });
  });
}

async function sendPersonalMessageAccepted(jid, content) {
  const sent = await sendMessageWithTimeout(jid, content);
  rememberOutboundMessage(sent);
  const messageId = String(sent?.key?.id || '');
  if (!messageId) {
    const err = new Error('WhatsApp tidak mengembalikan ID pesan.');
    err.httpCode = 502;
    throw err;
  }
  console.log(`➜ Pesan personal diterima engine: ${messageId} -> ${jid}`);
  const acknowledgement = await waitForServerAcknowledgement(messageId);
  return { sent, messageId, ...acknowledgement };
}

function normalizePersonalJid(toRaw) {
  const raw = String(toRaw || '').trim();
  if (!raw) {
    const err = new Error('Nomor tujuan wajib diisi.');
    err.httpCode = 400;
    throw err;
  }

  if (raw.includes('@')) {
    if (raw.endsWith('@s.whatsapp.net')) return raw;
    const err = new Error('Endpoint kirim manual hanya untuk nomor personal. Untuk grup gunakan endpoint grup.');
    err.httpCode = 400;
    throw err;
  }

  let digits = raw.replace(/\D+/g, '');
  if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
  if (digits.startsWith('8')) digits = `62${digits}`;

  if (digits.length < 10 || digits.length > 16 || !digits.startsWith('62')) {
    const err = new Error(`Format nomor WA tidak valid: ${raw}. Gunakan format 628xxxxxxxxxx.`);
    err.httpCode = 400;
    throw err;
  }

  return `${digits}@s.whatsapp.net`;
}

async function lookupPersonalAddress(jid) {
  if (!currentSock || typeof currentSock.onWhatsApp !== 'function') {
    return { jid, lid: null };
  }

  let timer = null;
  try {
    const rows = await Promise.race([
      currentSock.onWhatsApp(jid),
      new Promise((resolve) => {
        timer = setTimeout(() => resolve(null), 5000);
      }),
    ]);

    if (!rows) {
      const err = new Error('WhatsApp tidak merespons verifikasi nomor. Coba lagi saat koneksi bot stabil.');
      err.httpCode = 503;
      throw err;
    }
    const first = Array.isArray(rows) ? rows[0] : rows;
    if (!first || first.exists !== true) {
      const err = new Error(`Nomor ${jid.replace('@s.whatsapp.net', '')} tidak terdaftar di WhatsApp.`);
      err.httpCode = 400;
      throw err;
    }
    if (!first.jid || !String(first.jid).endsWith('@s.whatsapp.net')) {
      const err = new Error('WhatsApp tidak mengembalikan JID personal yang valid untuk nomor tujuan.');
      err.httpCode = 502;
      throw err;
    }
    return {
      jid: String(first.jid),
      lid: first.lid && String(first.lid).endsWith('@lid') ? String(first.lid) : null,
    };
  } finally {
    if (timer) clearTimeout(timer);
  }
}

// ─── Internal HTTP server ────────────────────────────────────
function startServer() {
  const server = http.createServer(async (req, res) => {
    try {
      const url   = new URL(req.url || '/', `http://127.0.0.1`);
      const token = url.searchParams.get('token') || req.headers['x-sync-token'] || '';

      if (token !== SYNC_TOKEN) {
        return jsonReply(res, 403, { ok: false, message: 'Forbidden' });
      }

      // GET /internal/status
      if (url.pathname === '/internal/status' && req.method === 'GET') {
        return jsonReply(res, 200, {
          ok:        true,
          status:    botStatus,
          phone:     botPhone,
          uptime:    Math.floor(process.uptime()),
          timestamp: new Date().toISOString(),
        });
      }

      // GET /internal/qr
      if (url.pathname === '/internal/qr' && req.method === 'GET') {
        return jsonReply(res, 200, {
          ok:     true,
          status: botStatus,
          qr:     latestQr,
          has_qr: !!latestQr,
        });
      }

      // POST /internal/check-number body: { to: "62xxx" }
      // Internal diagnostic only: validate the exact personal JID without
      // sending a message.
      if (url.pathname === '/internal/check-number' && req.method === 'POST') {
        if (!currentSock || botStatus !== 'CONNECTED') {
          return jsonReply(res, 503, { ok: false, message: 'Bot tidak terhubung.' });
        }
        let payload;
        try { payload = JSON.parse(await readBody(req)); }
        catch { return jsonReply(res, 400, { ok: false, message: 'JSON tidak valid.' }); }
        const address = await lookupPersonalAddress(normalizePersonalJid(String(payload.to || '')));
        return jsonReply(res, 200, { ok: true, jid: address.jid, lid: address.lid });
      }

      // POST /internal/send   body: { to: "62xxx", message: "...", image_path?: "/path/file.jpg" }
      if (url.pathname === '/internal/send' && req.method === 'POST') {
        if (!currentSock || botStatus !== 'CONNECTED') {
          return jsonReply(res, 503, { ok: false, message: 'Bot tidak terhubung.' });
        }
        let payload;
        try { payload = JSON.parse(await readBody(req)); }
        catch { return jsonReply(res, 400, { ok: false, message: 'JSON tidak valid.' }); }

        const toRaw   = String(payload.to || '');
        const outgoing = buildOutgoingMessage(payload);
        if (!outgoing.hasContent) return jsonReply(res, 400, { ok: false, message: 'Pesan atau gambar wajib diisi.' });

        const address = await lookupPersonalAddress(normalizePersonalJid(toRaw));
        // Baileys' official direct-message API uses the verified phone JID.
        // LID is retained only for diagnostics because it can break local
        // echo or delivery on some WhatsApp clients.
        const jid = address.jid;
        const accepted = await sendPersonalMessageAccepted(jid, outgoing.content);
        return jsonReply(res, 200, {
          ok: true,
          to: jid,
          message_id: accepted.messageId,
          receipt_status: accepted.receiptStatus,
          delivery_confirmed: accepted.deliveryConfirmed,
          delivery_status: accepted.deliveryConfirmed ? 'DELIVERED' : 'SERVER_ACCEPTED',
        });
      }

      // POST /internal/send-group   body: { group_jid: "120363xxx@g.us", message: "...", image_path?: "/path/file.jpg" }
      if (url.pathname === '/internal/send-group' && req.method === 'POST') {
        if (!currentSock || botStatus !== 'CONNECTED') {
          return jsonReply(res, 503, { ok: false, message: 'Bot tidak terhubung.' });
        }
        let payload;
        try { payload = JSON.parse(await readBody(req)); }
        catch { return jsonReply(res, 400, { ok: false, message: 'JSON tidak valid.' }); }

        const groupJid = String(payload.group_jid || '');
        const outgoing = buildOutgoingMessage(payload);
        if (!groupJid || !outgoing.hasContent) {
          return jsonReply(res, 400, { ok: false, message: 'group_jid dan pesan/gambar wajib diisi.' });
        }
        await sendMessageWithTimeout(groupJid, outgoing.content);
        return jsonReply(res, 200, { ok: true, group_jid: groupJid });
      }

      // POST /internal/list-groups  — kembalikan daftar grup yang diikuti bot
      if (url.pathname === '/internal/list-groups' && req.method === 'POST') {
        if (!currentSock || botStatus !== 'CONNECTED') {
          return jsonReply(res, 503, { ok: false, message: 'Bot tidak terhubung.' });
        }
        const groups = await currentSock.groupFetchAllParticipating();
        const list = Object.values(groups || {}).map(g => ({
          jid:     g.id,
          name:    g.subject,
          members: (g.participants || []).length,
        }));
        return jsonReply(res, 200, { ok: true, groups: list });
      }

      jsonReply(res, 404, { ok: false, message: 'Endpoint tidak ditemukan.' });

    } catch (err) {
      jsonReply(res, Number(err?.httpCode || 500), {
        ok: false,
        message: String(err?.message || err),
        account_restricted: !!err?.accountRestricted,
      });
    }
  });

  server.listen(SYNC_PORT, '127.0.0.1', () => {
    console.log(`🔄  Internal API siap di http://127.0.0.1:${SYNC_PORT}`);
    console.log('🔑  Token internal dikonfigurasi.');
  });
}

// ─── Start WA Bot ────────────────────────────────────────────
async function start() {
  if (isStarting) return;
  isStarting = true;

  const authDir = path.join(__dirname, 'auth_info');
  const { state, saveCreds } = await useMultiFileAuthState(authDir);
  const { version }          = await fetchLatestBaileysVersion();

  const sock = makeWASocket({
    version,
    auth:   state,
    logger: pino({ level: 'silent' }),
    // Do not sync full history after deploy. It is unnecessary for the bot
    // and avoids a long reconnect while keeping the existing linked session.
    syncFullHistory: false,
    // Lets Baileys recreate only a stale recipient Signal session when WA
    // asks for it, without resetting the bot's QR-linked account.
    enableAutoSessionRecreation: true,
    // Required for automatic resend after a recipient asks for fresh Signal
    // encryption keys. The default implementation always returns undefined.
    getMessage: getOutboundMessage,
  });

  currentSock = sock;
  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('messages.update', (updates) => {
    for (const update of updates || []) {
      const id = String(update?.key?.id || '');
      const updateData = update?.update || {};
      const status = updateData.status;
      const detail = Array.isArray(updateData.messageStubParameters)
        ? updateData.messageStubParameters.filter(value => value !== null && value !== undefined && value !== '').join(', ')
        : String(updateData.messageStubParameters || '');
      rememberOutboundStatus(id, status, detail);
      if (id && Number(status) >= 3) {
        console.log(`✓ Delivery WhatsApp dikonfirmasi: ${id}`);
      }
    }
  });

  sock.ev.on('messages.upsert', async ({ messages }) => {
    try {
      for (const msg of messages || []) {
        if (msg?.key?.fromMe) {
          rememberOutboundMessage(msg);
          continue;
        }
        const remoteJid = msg?.key?.remoteJid || '';
        if (!remoteJid.endsWith('@g.us')) continue;
        const command = normalizeIncomingCommand(extractIncomingText(msg.message));
        if (!command) continue;

        const reply = await buildGroupCommandReply(remoteJid, command);
        if (!reply) continue;
        await sendMessageWithTimeout(remoteJid, { text: reply }, 15000);
      }
    } catch (err) {
      console.warn('⚠️  Gagal memproses command grup:', err?.message || err);
    }
  });

  sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      // QR is exposed through the authenticated settings page. Do not print
      // it to the process log because a QR can link a WhatsApp account.
      console.log('📱 QR WhatsApp tersedia di Pengaturan WA Bot.');
      latestQr = qr;
      setStatus('WAITING_QR');
      updateSessionQr(qr).catch(() => {});
    }

    if (connection === 'open') {
      const phone = sock?.user?.id ? String(sock.user.id).split(':')[0].split('@')[0] : null;
      setStatus('CONNECTED', phone);
      clearSessionQr().catch(() => {});
      console.log(`✅  WA terhubung${phone ? ' — nomor: ' + phone : ''}`);
      isStarting    = false;
      reconnectDelay = 3000;
      if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
    }

    if (connection === 'close') {
      setStatus('DISCONNECTED');
      const code          = lastDisconnect?.error?.output?.statusCode;
      const shouldReconnect = code !== DisconnectReason.loggedOut;
      if (!shouldReconnect) {
        latestQr = null;
        clearSessionQr().catch(() => {});
        try {
          fs.rmSync(authDir, { recursive: true, force: true });
          console.log('🧹  Sesi WA logged out. Auth lama dibersihkan, engine akan meminta QR baru.');
        } catch (err) {
          console.warn('⚠️  Gagal membersihkan auth_info:', err?.message || err);
        }
      }
      reconnectDelay = code === 440
        ? Math.max(reconnectDelay, 60000)
        : Math.min(reconnectDelay * 2, 120000);
      console.log(`⚠️   Terputus (${code}). Reconnect: ${shouldReconnect}, delay: ${reconnectDelay}ms`);
      isStarting = false;
      if ((shouldReconnect || code === DisconnectReason.loggedOut) && !reconnectTimer) {
        reconnectTimer = setTimeout(() => { reconnectTimer = null; start(); }, reconnectDelay);
      }
    }
  });
}

// ─── Main ────────────────────────────────────────────────────
async function main() {
  await loadBaileys();
  startServer();
  await start();
}

main().catch(err => console.error('❌  Start error:', err));

process.on('unhandledRejection', (err) => {
  console.error('❌  Unhandled rejection:', err);
});

process.on('uncaughtException', (err) => {
  console.error('❌  Uncaught exception:', err);
});
