import http from 'http';
import path from 'path';
import { fileURLToPath } from 'url';
import express from 'express';
import rateLimit from 'express-rate-limit';
import { createStore, frameWins, matchHighestBreak } from './lib/store.js';
import { appInfo, APP_VERSION } from './lib/version.js';
import { pluginManifest, readPluginZip, ensurePluginZip } from './lib/wpPlugin.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export function normalizeBasePath(raw) {
  let value = String(raw || '/').trim() || '/';
  if (!value.startsWith('/')) value = `/${value}`;
  if (value.length > 1 && value.endsWith('/')) value = value.slice(0, -1);
  if (value.includes('..') || value.includes('//') || value.includes('\\')) return '/';
  return value;
}

function publicOrigin(req) {
  const proto = String(req.get('x-forwarded-proto') || req.protocol || 'https').split(',')[0].trim();
  const host = String(req.get('x-forwarded-host') || req.get('host') || '').split(',')[0].trim();
  return host ? `${proto}://${host}` : '';
}

function portalUser(req) {
  const id = String(req.get('x-webhost-user-id') || '').trim();
  const username = String(req.get('x-webhost-user') || '').trim();
  const role = String(req.get('x-webhost-role') || '').trim();
  if (!id && !username) return null;
  return { id, username, role };
}

function sendError(res, err, fallback) {
  const status = err.code === 'INVALID' ? 400 : 500;
  return res.status(status).json({ error: err.message || fallback });
}

function allowPublic(req, res, next) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.status(204).end();
  return next();
}

export function createApp({
  dataDir = process.env.SNOOKER_DATA_DIR || path.join(__dirname, 'data'),
  basePath = process.env.WEBHOST_BASE_PATH || '/webhost/snooker',
} = {}) {
  const app = express();
  const store = createStore(dataDir);
  const base = normalizeBasePath(basePath);
  const router = express.Router();

  app.disable('x-powered-by');
  app.use(express.json({ limit: '1mb' }));

  const guestLimit = rateLimit({
    windowMs: 60 * 60 * 1000,
    max: 30,
    standardHeaders: true,
    legacyHeaders: false,
    message: { error: 'Te veel inzendingen. Probeer later opnieuw.' },
  });

  function requireAdmin(req, res, next) {
    const user = portalUser(req);
    if (!user) {
      return res.status(401).json({ error: 'Aanmelden via het webhost-portaal is vereist.' });
    }
    req.portalUser = user;
    return next();
  }

  router.use((req, res, next) => {
    res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-App-Version', APP_VERSION);
    next();
  });

  router.get('/health', (_req, res) => {
    res.json({ ok: true, area: 'snooker', base, ...appInfo() });
  });

  router.get('/readyz', async (_req, res) => {
    try {
      await store.listPlayers();
      res.json({ ok: true, ready: true, version: APP_VERSION });
    } catch {
      res.status(503).json({ ok: false, ready: false, version: APP_VERSION });
    }
  });

  router.get('/api/public/meta', allowPublic, (_req, res) => {
    res.json({ app: appInfo() });
  });

  router.get('/api/public/wp-plugin', allowPublic, (req, res) => {
    res.json(pluginManifest({ origin: publicOrigin(req), base }));
  });

  router.get('/plugin/snookerclub.zip', allowPublic, (_req, res) => {
    try {
      const zip = readPluginZip();
      res.setHeader('Content-Type', 'application/zip');
      res.setHeader('Content-Length', String(zip.length));
      res.setHeader('Content-Disposition', 'attachment; filename="snookerclub.zip"');
      res.send(zip);
    } catch (err) {
      res.status(500).json({ error: err.message || 'Plugin-zip bouwen mislukt.' });
    }
  });

  router.use(express.static(path.join(__dirname, 'public'), {
    index: false,
    redirect: false,
    maxAge: process.env.NODE_ENV === 'production' ? '1d' : 0,
  }));

  router.get('/', (req, res) => {
    const raw = String(req.originalUrl || '').split('?')[0];
    if (raw === base) {
      const query = String(req.originalUrl).includes('?')
        ? String(req.originalUrl).slice(String(req.originalUrl).indexOf('?'))
        : '';
      return res.redirect(308, `${base}/${query}`);
    }
    res.sendFile(path.join(__dirname, 'public/guest.html'));
  });

  router.get('/admin', (_req, res) => {
    res.sendFile(path.join(__dirname, 'public/admin.html'));
  });

  router.get('/live', (_req, res) => {
    res.sendFile(path.join(__dirname, 'public/live.html'));
  });

  router.get('/live/players', (_req, res) => {
    res.sendFile(path.join(__dirname, 'public/live-players.html'));
  });

  router.get('/live/agenda', (_req, res) => {
    res.sendFile(path.join(__dirname, 'public/live-agenda.html'));
  });

  router.get('/media/hero', async (_req, res) => {
    const buf = await store.readHero();
    if (!buf) return res.redirect(`${base}/hero.jpg`);
    res.setHeader('Cache-Control', 'no-store');
    res.type(buf[0] === 0x89 ? 'png' : 'jpeg').send(buf);
  });

  router.get('/api/public/brand', allowPublic, async (_req, res) => {
    res.json(await store.getBrand());
  });

  router.get('/api/public/overview', allowPublic, async (_req, res) => {
    res.json(await store.overview(base));
  });

  router.get('/api/public/players', allowPublic, async (_req, res) => {
    const overview = await store.overview(base);
    res.json({
      brand: overview.brand,
      players: overview.players,
      top3: overview.top3,
    });
  });

  router.get('/api/public/live', allowPublic, async (_req, res) => {
    const overview = await store.overview(base);
    res.json({
      brand: overview.brand,
      highestBreak: overview.highestBreak,
      latest: overview.recent[0] || null,
      recent: overview.recent.slice(0, 4),
      top3: overview.top3,
      players: overview.players.slice(0, 8),
      matchCount: overview.matchCount,
      playerCount: overview.playerCount,
      centuries: overview.centuries,
      closest: overview.closest,
      kpis: overview.kpis,
      nextEvent: overview.nextEvent,
      nextEventLabel: overview.nextEventLabel,
      agenda: overview.agenda,
    });
  });

  router.get('/api/public/tournaments', allowPublic, async (_req, res) => {
    const overview = await store.overview();
    const names = [...new Set([...(overview.brand.tournaments || []), ...overview.tournaments])];
    res.json({ tournaments: names });
  });

  router.get('/api/public/roster', allowPublic, async (_req, res) => {
    res.json({ players: await store.listPlayers() });
  });

  router.get('/api/public/agenda', allowPublic, async (req, res) => {
    const brand = await store.getBrand();
    const agenda = await store.agenda(req.query.year, req.query.month);
    res.json({ brand, agenda });
  });

  router.get('/api/public/rapport', allowPublic, async (req, res) => {
    res.json(await store.report(req.query.season || ''));
  });

  router.get('/api/public/dossier', allowPublic, async (req, res) => {
    try {
      res.json(await store.playerDossier(req.query.player || '', req.query.season || ''));
    } catch (err) {
      sendError(res, err, 'Dossier laden mislukt.');
    }
  });

  router.get('/api/public/h2h', allowPublic, async (req, res) => {
    try {
      res.json(await store.headToHead(req.query.a || '', req.query.b || ''));
    } catch (err) {
      sendError(res, err, 'Head-to-head laden mislukt.');
    }
  });

  router.post('/api/matches', guestLimit, async (req, res) => {
    try {
      const match = await store.createMatch(req.body || {});
      res.status(201).json({
        match,
        wins: match.wins || frameWins(match.frames),
        highestBreak: match.highestBreak || matchHighestBreak(match),
      });
    } catch (err) {
      sendError(res, err, 'Opslaan mislukt.');
    }
  });

  router.get('/api/admin/me', requireAdmin, (req, res) => {
    res.json({ user: req.portalUser, app: appInfo() });
  });

  router.get('/api/admin/players', requireAdmin, async (_req, res) => {
    res.json({ players: await store.listPlayers() });
  });

  router.post('/api/admin/players', requireAdmin, async (req, res) => {
    try {
      const player = await store.createPlayer(req.body || {});
      res.status(201).json({ player });
    } catch (err) {
      sendError(res, err, 'Speler opslaan mislukt.');
    }
  });

  router.put('/api/admin/players/:id', requireAdmin, async (req, res) => {
    try {
      const player = await store.updatePlayer(req.params.id, req.body || {});
      if (!player) return res.status(404).json({ error: 'Speler niet gevonden.' });
      res.json({ player });
    } catch (err) {
      sendError(res, err, 'Speler bijwerken mislukt.');
    }
  });

  router.delete('/api/admin/players/:id', requireAdmin, async (req, res) => {
    try {
      const ok = await store.deletePlayer(req.params.id);
      if (!ok) return res.status(404).json({ error: 'Speler niet gevonden.' });
      res.json({ ok: true });
    } catch (err) {
      sendError(res, err, 'Speler verwijderen mislukt.');
    }
  });

  router.get('/api/admin/matches', requireAdmin, async (_req, res) => {
    const matches = await store.listMatches();
    res.json({ matches });
  });

  router.post('/api/admin/matches', requireAdmin, async (req, res) => {
    try {
      const body = req.body || {};
      const match = body.source === 'paper' || body.result || body.framesFor != null
        ? await store.createPaperMatch(body)
        : await store.createMatch(body, { requireSignatures: false });
      res.status(201).json({ match, wins: match.wins, highestBreak: match.highestBreak });
    } catch (err) {
      sendError(res, err, 'Wedstrijd opslaan mislukt.');
    }
  });

  router.post('/api/admin/paper', requireAdmin, async (req, res) => {
    try {
      res.status(201).json(await store.importPaper(req.body || {}));
    } catch (err) {
      sendError(res, err, 'Papierimport mislukt.');
    }
  });

  router.get('/api/admin/rapport', requireAdmin, async (req, res) => {
    res.json(await store.report(req.query.season || ''));
  });

  router.get('/api/admin/dossier', requireAdmin, async (req, res) => {
    try {
      res.json(await store.playerDossier(req.query.player || '', req.query.season || ''));
    } catch (err) {
      sendError(res, err, 'Dossier laden mislukt.');
    }
  });

  router.get('/api/admin/h2h', requireAdmin, async (req, res) => {
    try {
      res.json(await store.headToHead(req.query.a || '', req.query.b || ''));
    } catch (err) {
      sendError(res, err, 'Head-to-head laden mislukt.');
    }
  });

  router.get('/api/admin/matches/:id', requireAdmin, async (req, res) => {
    const match = await store.getMatch(req.params.id);
    if (!match) return res.status(404).json({ error: 'Wedstrijd niet gevonden.' });
    res.json({ match, wins: match.wins, highestBreak: match.highestBreak });
  });

  router.put('/api/admin/matches/:id', requireAdmin, async (req, res) => {
    try {
      const match = await store.updateMatch(req.params.id, req.body || {});
      if (!match) return res.status(404).json({ error: 'Wedstrijd niet gevonden.' });
      res.json({ match, wins: match.wins, highestBreak: match.highestBreak });
    } catch (err) {
      sendError(res, err, 'Bijwerken mislukt.');
    }
  });

  router.delete('/api/admin/matches/:id', requireAdmin, async (req, res) => {
    const ok = await store.deleteMatch(req.params.id);
    if (!ok) return res.status(404).json({ error: 'Wedstrijd niet gevonden.' });
    res.json({ ok: true });
  });

  router.get('/api/admin/events', requireAdmin, async (_req, res) => {
    res.json({ events: await store.listEvents() });
  });

  router.post('/api/admin/events', requireAdmin, async (req, res) => {
    try {
      const event = await store.createEvent(req.body || {});
      res.status(201).json({ event });
    } catch (err) {
      sendError(res, err, 'Agenda-item opslaan mislukt.');
    }
  });

  router.put('/api/admin/events/:id', requireAdmin, async (req, res) => {
    try {
      const event = await store.updateEvent(req.params.id, req.body || {});
      if (!event) return res.status(404).json({ error: 'Agenda-item niet gevonden.' });
      res.json({ event });
    } catch (err) {
      sendError(res, err, 'Agenda-item bijwerken mislukt.');
    }
  });

  router.delete('/api/admin/events/:id', requireAdmin, async (req, res) => {
    const ok = await store.deleteEvent(req.params.id);
    if (!ok) return res.status(404).json({ error: 'Agenda-item niet gevonden.' });
    res.json({ ok: true });
  });

  router.get('/api/admin/brand', requireAdmin, async (_req, res) => {
    res.json(await store.getBrand());
  });

  router.put('/api/admin/brand', requireAdmin, async (req, res) => {
    try {
      res.json(await store.saveBrand(req.body || {}));
    } catch (err) {
      sendError(res, err, 'Branding opslaan mislukt.');
    }
  });

  router.put('/api/admin/hero', requireAdmin, async (req, res) => {
    try {
      res.json(await store.saveHero(req.body?.image, `${base}/media/hero`));
    } catch (err) {
      sendError(res, err, 'Achtergrond opslaan mislukt.');
    }
  });

  router.get('/api/admin/embed', requireAdmin, (req, res) => {
    const origin = publicOrigin(req);
    const root = `${origin}${base}`;
    res.json({
      iframe: `<iframe src="${root}/live" title="Snooker live" style="width:100%;height:460px;border:0;border-radius:16px" loading="lazy"></iframe>`,
      script: `<div data-snooker-live></div>\n<script src="${root}/embed.js" async></script>`,
      playersIframe: `<iframe src="${root}/live/players" title="Spelerslijst" style="width:100%;height:520px;border:0;border-radius:16px" loading="lazy"></iframe>`,
      playersScript: `<div data-snooker-players></div>\n<script src="${root}/embed.js" async></script>`,
      agendaIframe: `<iframe src="${root}/live/agenda" title="Clubagenda" style="width:100%;height:640px;border:0;border-radius:16px" loading="lazy"></iframe>`,
      agendaScript: `<div data-snooker-agenda></div>\n<script src="${root}/embed.js" async></script>`,
      liveUrl: `${root}/live`,
      playersUrl: `${root}/live/players`,
      agendaUrl: `${root}/live/agenda`,
      embedJs: `${root}/embed.js`,
    });
  });

  app.use(base, router);
  if (base !== '/') {
    app.get('/', (_req, res) => res.redirect(base));
  }
  return app;
}

function start() {
  const app = createApp();
  const port = Number.parseInt(process.env.PORT || '9091', 10);
  const host = process.env.HOST || '127.0.0.1';
  const server = http.createServer(app);
  try {
    const zipFile = ensurePluginZip();
    console.log(`WordPress-plugin klaar: ${zipFile}`);
  } catch (err) {
    console.warn(`WordPress-plugin-zip niet gebouwd: ${err.message || err}`);
  }
  server.listen(port, host, () => {
    const base = normalizeBasePath(process.env.WEBHOST_BASE_PATH || '/webhost/snooker');
    console.log(`Snooker ${APP_VERSION} luistert op http://${host}:${port}${base}`);
    console.log(`Plugin-zip: ${base}/plugin/snookerclub.zip`);
  });

  let stopping = false;
  function stop(signal) {
    if (stopping) return;
    stopping = true;
    console.log(`${signal}: snooker stopt netjes.`);
    server.close((err) => {
      if (err) {
        console.error(err);
        process.exit(1);
      }
      process.exit(0);
    });
    setTimeout(() => process.exit(1), 10_000).unref();
  }
  process.on('SIGTERM', () => stop('SIGTERM'));
  process.on('SIGINT', () => stop('SIGINT'));
}

const invoked = process.argv[1] && path.resolve(fileURLToPath(import.meta.url)) === path.resolve(process.argv[1]);
if (invoked) start();
