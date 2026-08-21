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
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');

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

async function resolveExistingPersonalJid(jid) {
  if (!currentSock || typeof currentSock.onWhatsApp !== 'function') {
    return jid;
  }

  let timer = null;
  try {
    const rows = await Promise.race([
      currentSock.onWhatsApp(jid),
      new Promise((resolve) => {
        timer = setTimeout(() => resolve(null), 5000);
      }),
    ]);

    if (!rows) return jid;
    const first = Array.isArray(rows) ? rows[0] : rows;
    if (first && first.exists === false) {
      const err = new Error(`Nomor ${jid.replace('@s.whatsapp.net', '')} tidak terdaftar di WhatsApp.`);
      err.httpCode = 400;
      throw err;
    }
    return first?.jid || jid;
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

        const jid = await resolveExistingPersonalJid(normalizePersonalJid(toRaw));
        await sendMessageWithTimeout(jid, outgoing.content);
        return jsonReply(res, 200, { ok: true, to: jid });
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
      jsonReply(res, Number(err?.httpCode || 500), { ok: false, message: String(err?.message || err) });
    }
  });

  server.listen(SYNC_PORT, '127.0.0.1', () => {
    console.log(`🔄  Internal API siap di http://127.0.0.1:${SYNC_PORT}`);
    console.log(`🔑  Token: ${SYNC_TOKEN}`);
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
  });

  currentSock = sock;
  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('messages.upsert', async ({ messages }) => {
    try {
      for (const msg of messages || []) {
        const remoteJid = msg?.key?.remoteJid || '';
        if (!remoteJid.endsWith('@g.us') || msg?.key?.fromMe) continue;
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
      console.log('📱  Scan QR ini dengan WhatsApp di HP kamu:');
      require('qrcode-terminal').generate(qr, { small: true });
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
startServer();
start().catch(err => console.error('❌  Start error:', err));

process.on('unhandledRejection', (err) => {
  console.error('❌  Unhandled rejection:', err);
});

process.on('uncaughtException', (err) => {
  console.error('❌  Uncaught exception:', err);
});
