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
const path  = require('path');
const mysql = require('mysql2/promise');
const pino  = require('pino');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');

// ─── Konfigurasi ────────────────────────────────────────────
const SYNC_PORT  = Number(process.env.WA_PORT  || 3070);
const SYNC_TOKEN = String(process.env.WA_TOKEN || 'local-dev-token');

const dbConfig = {
  host:             process.env.DB_HOST || 'localhost',
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
      `UPDATE wa_session SET status = ?, phone_number = COALESCE(?, phone_number), last_ping_at = NOW() WHERE id = 1`,
      [status, phone]
    );
  } catch (err) {
    // tabel belum ada atau koneksi gagal — lanjut saja
    console.warn('⚠️  wa_session update gagal:', err?.code || err?.message);
  }
}

// ─── Bot state helpers ───────────────────────────────────────
function setStatus(status, phone = null) {
  botStatus = status;
  if (phone) botPhone = phone;
  if (status !== 'WAITING_QR') latestQr = null;
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

      // POST /internal/send   body: { to: "62xxx", message: "..." }
      if (url.pathname === '/internal/send' && req.method === 'POST') {
        if (!currentSock || botStatus !== 'CONNECTED') {
          return jsonReply(res, 503, { ok: false, message: 'Bot tidak terhubung.' });
        }
        let payload;
        try { payload = JSON.parse(await readBody(req)); }
        catch { return jsonReply(res, 400, { ok: false, message: 'JSON tidak valid.' }); }

        const message = String(payload.message || '').trim();
        const toRaw   = String(payload.to || '');
        if (!message) return jsonReply(res, 400, { ok: false, message: 'Pesan kosong.' });

        const jid = toRaw.includes('@') ? toRaw : (toRaw.replace(/\D+/g, '').replace(/^0/, '62') + '@s.whatsapp.net');
        await currentSock.sendMessage(jid, { text: message });
        return jsonReply(res, 200, { ok: true, to: jid });
      }

      // POST /internal/send-group   body: { group_jid: "120363xxx@g.us", message: "..." }
      if (url.pathname === '/internal/send-group' && req.method === 'POST') {
        if (!currentSock || botStatus !== 'CONNECTED') {
          return jsonReply(res, 503, { ok: false, message: 'Bot tidak terhubung.' });
        }
        let payload;
        try { payload = JSON.parse(await readBody(req)); }
        catch { return jsonReply(res, 400, { ok: false, message: 'JSON tidak valid.' }); }

        const groupJid = String(payload.group_jid || '');
        const message  = String(payload.message   || '').trim();
        if (!groupJid || !message) {
          return jsonReply(res, 400, { ok: false, message: 'group_jid dan message wajib diisi.' });
        }
        await currentSock.sendMessage(groupJid, { text: message });
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
      jsonReply(res, 500, { ok: false, message: String(err?.message || err) });
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

  sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      console.log('📱  Scan QR ini dengan WhatsApp di HP kamu:');
      require('qrcode-terminal').generate(qr, { small: true });
      latestQr = qr;
      setStatus('WAITING_QR');
    }

    if (connection === 'open') {
      const phone = sock?.user?.id ? String(sock.user.id).split(':')[0].split('@')[0] : null;
      setStatus('CONNECTED', phone);
      console.log(`✅  WA terhubung${phone ? ' — nomor: ' + phone : ''}`);
      isStarting    = false;
      reconnectDelay = 3000;
      if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
    }

    if (connection === 'close') {
      setStatus('DISCONNECTED');
      const code          = lastDisconnect?.error?.output?.statusCode;
      const shouldReconnect = code !== DisconnectReason.loggedOut;
      reconnectDelay = code === 440
        ? Math.max(reconnectDelay, 60000)
        : Math.min(reconnectDelay * 2, 120000);
      console.log(`⚠️   Terputus (${code}). Reconnect: ${shouldReconnect}, delay: ${reconnectDelay}ms`);
      isStarting = false;
      if (shouldReconnect && !reconnectTimer) {
        reconnectTimer = setTimeout(() => { reconnectTimer = null; start(); }, reconnectDelay);
      }
    }
  });
}

// ─── Main ────────────────────────────────────────────────────
startServer();
start().catch(err => console.error('❌  Start error:', err));
