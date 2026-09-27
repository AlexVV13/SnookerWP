import { normalizeTheme } from './theme.js';

export const DEFAULT_BRAND = {
  clubName: 'SC De Merodesnookers',
  tagline: 'Clubavonden, tornooien en uitslagen in Biljart Palace.',
  accent: '#2ea85a',
  accentSoft: '#f0b429',
  logoUrl: '',
  heroUrl: 'hero.jpg',
  showSignatures: true,
  framesCount: 5,
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
  const frames = Number.parseInt(src.framesCount, 10);
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
    framesCount: frames >= 1 && frames <= 17 ? frames : 5,
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
