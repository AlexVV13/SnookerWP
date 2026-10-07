import { normalizeTheme } from './theme.js';

/** Match length presets: best-of (race) or fixed poule/voorronde frames. */
export const FRAME_FORMATS = [
  { mode: 'bestof', count: 1, label: '1 frame' },
  { mode: 'bestof', count: 3, label: 'Best of 3' },
  { mode: 'bestof', count: 5, label: 'Best of 5' },
  { mode: 'bestof', count: 7, label: 'Best of 7' },
  { mode: 'bestof', count: 9, label: 'Best of 9' },
  { mode: 'bestof', count: 11, label: 'Best of 11' },
  { mode: 'bestof', count: 17, label: 'Best of 17' },
  { mode: 'fixed', count: 2, label: '2 frames (poule)' },
  { mode: 'fixed', count: 3, label: '3 frames (poule / voorronde)' },
  { mode: 'fixed', count: 4, label: '4 frames (poule)' },
  { mode: 'fixed', count: 5, label: '5 frames (vast)' },
];

export function parseFrameFormat(value, fallbackCount = 5, fallbackMode = 'bestof') {
  const raw = String(value || '').trim();
  const match = raw.match(/^(bestof|fixed)[:|-](\d+)$/i);
  if (match) {
    const count = Number.parseInt(match[2], 10);
    if (count >= 1 && count <= 17) {
      return { frameMode: match[1].toLowerCase(), framesCount: count };
    }
  }
  const n = Number.parseInt(raw, 10);
  if (n >= 1 && n <= 17) return { frameMode: fallbackMode === 'fixed' ? 'fixed' : 'bestof', framesCount: n };
  return {
    frameMode: fallbackMode === 'fixed' ? 'fixed' : 'bestof',
    framesCount: fallbackCount >= 1 && fallbackCount <= 17 ? fallbackCount : 5,
  };
}

export function frameFormatValue(brand = {}) {
  const mode = brand.frameMode === 'fixed' ? 'fixed' : 'bestof';
  const count = Number.parseInt(brand.framesCount, 10);
  return `${mode}:${count >= 1 && count <= 17 ? count : 5}`;
}

export const DEFAULT_BRAND = {
  clubName: 'SC De Merodesnookers',
  tagline: 'Clubavonden, tornooien en uitslagen in Biljart Palace.',
  accent: '#2ea85a',
  accentSoft: '#f0b429',
  logoUrl: '',
  heroUrl: 'hero.jpg',
  showSignatures: true,
  showAvgHandicap: true,
  framesCount: 5,
  frameMode: 'bestof',
  tournaments: [
    'Potblack',
    'Rankingtornooi',
    'Kersttornooi',
    'Handicaptornooi',
    '6 Red',
    'Open Merode',
    'Clubkampioenschap',
  ],
  notice: '',
  venue: 'Biljart Palace, Merodecenter 19, 2300 Turnhout',
  openingHours: 'Clubavond donderdag vanaf 19u',
  nextEvent: '',
  goalMatchesMonth: 12,
  goalMatchesWeek: 4,
  goalFramesMonth: 40,
  goalCenturies: 3,
  goalNewMembersMonth: 5,
  goalClubNightsMonth: 4,
  themePreset: 'baize',
  font: 'club',
  radius: 'club',
  density: 'comfortable',
  inheritWp: false,
};

function safeUrl(value, fallback = '') {
  const raw = String(value || '').trim().slice(0, 400);
  if (!raw) return fallback;
  if (raw.startsWith('data:')) return '';
  if (/[\r\n<>]/.test(raw)) return fallback;
  return raw;
}

function goal(value, fallback, max) {
  const n = Number.parseInt(value, 10);
  if (!Number.isFinite(n) || n < 1) return fallback;
  return Math.min(max, n);
}

export function normalizeBrand(input = {}) {
  const src = input && typeof input === 'object' ? input : {};
  const parsed = parseFrameFormat(
    src.frameFormat || `${src.frameMode || 'bestof'}:${src.framesCount || DEFAULT_BRAND.framesCount}`,
    DEFAULT_BRAND.framesCount,
    src.frameMode || DEFAULT_BRAND.frameMode,
  );
  const tournaments = Array.isArray(src.tournaments)
    ? src.tournaments.map((name) => String(name || '').trim().slice(0, 80)).filter(Boolean)
    : String(src.tournaments || '')
      .split(/\n|,/)
      .map((name) => name.trim().slice(0, 80))
      .filter(Boolean);
  const theme = normalizeTheme(src);
  return {
    clubName: String(src.clubName || DEFAULT_BRAND.clubName).trim().slice(0, 80) || DEFAULT_BRAND.clubName,
    tagline: String(src.tagline || DEFAULT_BRAND.tagline).trim().slice(0, 160),
    accent: theme.accent,
    accentSoft: theme.accentSoft,
    themePreset: theme.themePreset,
    bg: theme.bg,
    bgSide: theme.bgSide,
    surface: theme.surface,
    surface2: theme.surface2,
    ink: theme.ink,
    muted: theme.muted,
    topbarInk: theme.topbarInk,
    font: theme.font,
    radius: theme.radius,
    density: theme.density,
    inheritWp: theme.inheritWp,
    logoUrl: safeUrl(src.logoUrl),
    heroUrl: safeUrl(src.heroUrl, DEFAULT_BRAND.heroUrl) || DEFAULT_BRAND.heroUrl,
    showSignatures: src.showSignatures !== false && src.showSignatures !== '0',
    showAvgHandicap: src.showAvgHandicap !== false && src.showAvgHandicap !== '0',
    framesCount: parsed.framesCount,
    frameMode: parsed.frameMode,
    tournaments: tournaments.slice(0, 40),
    notice: String(src.notice ?? DEFAULT_BRAND.notice).trim().slice(0, 240),
    venue: String(src.venue ?? DEFAULT_BRAND.venue).trim().slice(0, 120),
    openingHours: String(src.openingHours ?? DEFAULT_BRAND.openingHours).trim().slice(0, 120),
    nextEvent: String(src.nextEvent ?? DEFAULT_BRAND.nextEvent).trim().slice(0, 160),
    goalMatchesMonth: goal(src.goalMatchesMonth, DEFAULT_BRAND.goalMatchesMonth, 200),
    goalMatchesWeek: goal(src.goalMatchesWeek, DEFAULT_BRAND.goalMatchesWeek, 50),
    goalFramesMonth: goal(src.goalFramesMonth, DEFAULT_BRAND.goalFramesMonth, 500),
    goalCenturies: goal(src.goalCenturies, DEFAULT_BRAND.goalCenturies, 50),
    goalNewMembersMonth: goal(src.goalNewMembersMonth, DEFAULT_BRAND.goalNewMembersMonth, 80),
    goalClubNightsMonth: goal(src.goalClubNightsMonth, DEFAULT_BRAND.goalClubNightsMonth, 40),
  };
}

export function resolveHeroUrl(brand, base = '/webhost/snooker') {
  const raw = String(brand?.heroUrl || '').trim();
  if (!raw || raw === 'hero.jpg') return `${base}/hero.jpg`;
  if (/^https?:\/\//i.test(raw) || raw.startsWith('/')) return raw;
  return `${base}/${raw.replace(/^\//, '')}`;
}
