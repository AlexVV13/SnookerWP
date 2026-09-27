import fs from 'fs';
import { fileURLToPath } from 'url';
import path from 'path';

const pkgPath = path.join(path.dirname(fileURLToPath(import.meta.url)), '../package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const startedAt = new Date().toISOString();

export const APP_NAME = pkg.name || 'webhost-snooker';
export const APP_VERSION = process.env.APP_VERSION || pkg.version || '0.0.0';
export const APP_REVISION = process.env.APP_REVISION || process.env.GIT_SHA || 'local';
export const BUILD_DATE = process.env.BUILD_DATE || '';

export function appInfo() {
  return {
    name: APP_NAME,
    version: APP_VERSION,
    revision: String(APP_REVISION).slice(0, 40),
    buildDate: BUILD_DATE,
    node: process.version,
    env: process.env.NODE_ENV || 'development',
    startedAt,
    uptimeSec: Math.round(process.uptime()),
  };
}
