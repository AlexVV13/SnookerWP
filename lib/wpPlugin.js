import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import os from 'os';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const SNOOKER_ROOT = path.join(__dirname, '..');
export const PLUGIN_DIR = path.join(SNOOKER_ROOT, 'wordpress', 'snookerclub');
export const PUBLIC_DIR = path.join(SNOOKER_ROOT, 'public');
export const DIST_DIR = path.join(SNOOKER_ROOT, 'wordpress', 'dist');
export const PUBLIC_ZIP = path.join(PUBLIC_DIR, 'plugin', 'snookerclub.zip');
export const DIST_ZIP = PUBLIC_ZIP;

export function pluginHeaderVersion(source = fs.readFileSync(path.join(PLUGIN_DIR, 'snookerclub.php'), 'utf8')) {
  const match = source.match(/^\s*\*\s*Version:\s*([0-9.]+)/m);
  return match ? match[1] : '0.0.0';
}

export function pluginManifest({ origin = '', base = '/webhost/snooker', version } = {}) {
  const ver = version || pluginHeaderVersion();
  let root = `${String(origin || '').replace(/\/$/, '')}${base}`;
  // WordPress weigert http-packages; forceer https achter proxies.
  root = root.replace(/^http:\/\//i, 'https://');
  return {
    name: 'Snookerclub',
    slug: 'snookerclub',
    plugin: 'snookerclub/snookerclub.php',
    version: ver,
    new_version: ver,
    url: root,
    homepage: root,
    package: `${root}/plugin/snookerclub.zip`,
    download_url: `${root}/plugin/snookerclub.zip`,
    requires: '6.2',
    tested: '6.8',
    requires_php: '8.1',
    last_updated: new Date().toISOString().slice(0, 10),
    author: 'Alex van Vught',
    sections: {
      description: 'Snookerclub voor WordPress: guest-uitslagen, clubbeheer, agenda en liveblokken.',
      changelog: `Versie ${ver}.`,
    },
  };
}

function copyDir(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    if (entry.name === '.' || entry.name === '..') continue;
    if (src === PUBLIC_DIR && entry.name === 'plugin') continue;
    const from = path.join(src, entry.name);
    const to = path.join(dest, entry.name);
    if (entry.isDirectory()) copyDir(from, to);
    else fs.copyFileSync(from, to);
  }
}

export function stagePlugin(targetDir) {
  fs.rmSync(targetDir, { recursive: true, force: true });
  copyDir(PLUGIN_DIR, targetDir);
  copyDir(PUBLIC_DIR, path.join(targetDir, 'public'));
  return targetDir;
}

function walkFiles(dir, prefix = '') {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === '.' || entry.name === '..') continue;
    const rel = prefix ? `${prefix}/${entry.name}` : entry.name;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...walkFiles(full, rel));
    else out.push({ name: rel, data: fs.readFileSync(full) });
  }
  return out;
}

const CRC_TABLE = (() => {
  const table = new Uint32Array(256);
  for (let i = 0; i < 256; i += 1) {
    let crc = i;
    for (let j = 0; j < 8; j += 1) crc = crc & 1 ? 0xedb88320 ^ (crc >>> 1) : crc >>> 1;
    table[i] = crc >>> 0;
  }
  return table;
})();

function crc32(buf) {
  let crc = 0xffffffff;
  for (const byte of buf) crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}

function dosDateTime(date = new Date()) {
  const year = Math.max(1980, date.getFullYear());
  const dosTime = (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2);
  const dosDate = ((year - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();
  return { dosTime, dosDate };
}

/** Store-method ZIP so packaging works without the `zip` binary. */
export function writeZip(files) {
  const { dosTime, dosDate } = dosDateTime();
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const file of files) {
    const name = Buffer.from(file.name.replace(/\\/g, '/'), 'utf8');
    const data = Buffer.isBuffer(file.data) ? file.data : Buffer.from(file.data);
    const crc = crc32(data);
    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0, 6);
    local.writeUInt16LE(0, 8);
    local.writeUInt16LE(dosTime, 10);
    local.writeUInt16LE(dosDate, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(data.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(name.length, 26);
    local.writeUInt16LE(0, 28);
    const localChunk = Buffer.concat([local, name, data]);
    locals.push(localChunk);
    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0, 8);
    central.writeUInt16LE(0, 10);
    central.writeUInt16LE(dosTime, 12);
    central.writeUInt16LE(dosDate, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(data.length, 20);
    central.writeUInt32LE(data.length, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt16LE(0, 30);
    central.writeUInt16LE(0, 32);
    central.writeUInt16LE(0, 34);
    central.writeUInt16LE(0, 36);
    central.writeUInt32LE(0, 38);
    central.writeUInt32LE(offset, 42);
    centrals.push(Buffer.concat([central, name]));
    offset += localChunk.length;
  }
  const central = Buffer.concat(centrals);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(files.length, 8);
  end.writeUInt16LE(files.length, 10);
  end.writeUInt32LE(central.length, 12);
  end.writeUInt32LE(offset, 16);
  end.writeUInt16LE(0, 20);
  return Buffer.concat([...locals, central, end]);
}

export function zipTargets() {
  return [
    PUBLIC_ZIP,
    path.join(SNOOKER_ROOT, 'wordpress', 'dist', 'snookerclub.zip'),
    path.join(os.tmpdir(), 'snookerclub.zip'),
  ];
}

export function ensurePluginZip() {
  for (const file of zipTargets()) {
    try {
      if (fs.existsSync(file) && fs.statSync(file).size > 1000) return file;
    } catch {
      // try next
    }
  }
  let lastErr;
  for (const file of zipTargets()) {
    try {
      return buildPluginZip(file);
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr || new Error('Kon de plugin-zip nergens wegschrijven.');
}

export function buildPluginZip(outFile = PUBLIC_ZIP) {
  if (!fs.existsSync(path.join(PLUGIN_DIR, 'snookerclub.php'))) {
    throw new Error(`Pluginbron ontbreekt: ${PLUGIN_DIR}`);
  }
  if (!fs.existsSync(path.join(PUBLIC_DIR, 'guest.html'))) {
    throw new Error(`Frontend ontbreekt: ${PUBLIC_DIR}`);
  }
  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'snookerclub-'));
  try {
    const staged = path.join(tmp, 'snookerclub');
    stagePlugin(staged);
    const files = walkFiles(staged, 'snookerclub');
    if (!files.length) throw new Error('Geen pluginbestanden om te zippen.');
    fs.writeFileSync(outFile, writeZip(files));
  } finally {
    fs.rmSync(tmp, { recursive: true, force: true });
  }
  return outFile;
}

export function readPluginZip(outFile) {
  const file = outFile && fs.existsSync(outFile) ? outFile : ensurePluginZip();
  return fs.readFileSync(file);
}
