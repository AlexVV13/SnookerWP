#!/usr/bin/env node
import fs from 'fs';
import { buildPluginZip, pluginHeaderVersion, PUBLIC_ZIP } from '../lib/wpPlugin.js';

try {
  const zip = buildPluginZip();
  console.log(`Snookerclub ${pluginHeaderVersion()} → ${zip}`);
  console.log(`bytes ${fs.statSync(PUBLIC_ZIP).size}`);
} catch (err) {
  console.error(err.message || err);
  process.exit(1);
}
