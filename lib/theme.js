/** Club appearance presets — same values as public/theme.js and PHP class-theme. */

export const THEME_PRESETS = {
  baize: {
    label: 'Baize',
    bg: '#f3f7f0',
    bgSide: '#163524',
    surface: '#ffffff',
    surface2: '#eef6ee',
    ink: '#1d2a22',
    muted: '#5b6d61',
    accent: '#2ea85a',
    accentSoft: '#f0b429',
    topbarInk: '#f4fff6',
    line: 'rgba(22, 53, 36, 0.1)',
  },
  midnight: {
    label: 'Nacht',
    bg: '#12161a',
    bgSide: '#0b0e11',
    surface: '#1b2128',
    surface2: '#232b34',
    ink: '#e8eef2',
    muted: '#9aa8b3',
    accent: '#3dd68c',
    accentSoft: '#f0c14b',
    topbarInk: '#e8eef2',
    line: 'rgba(232, 238, 242, 0.12)',
  },
  ivory: {
    label: 'Ivoor',
    bg: '#f7f1e6',
    bgSide: '#3d2b1f',
    surface: '#fffaf2',
    surface2: '#f3e6d2',
    ink: '#2b2118',
    muted: '#7a6856',
    accent: '#c48a2a',
    accentSoft: '#d4a84b',
    topbarInk: '#fff6e8',
    line: 'rgba(61, 43, 31, 0.12)',
  },
  classic: {
    label: 'Club',
    bg: '#f6efe8',
    bgSide: '#4a1c14',
    surface: '#ffffff',
    surface2: '#f3e4d8',
    ink: '#2a1814',
    muted: '#7a5348',
    accent: '#9b2c2c',
    accentSoft: '#d4a017',
    topbarInk: '#fff4ee',
    line: 'rgba(74, 28, 20, 0.12)',
  },
  ruby: {
    label: 'Ruby',
    bg: '#f6f0f1',
    bgSide: '#1a0c10',
    surface: '#ffffff',
    surface2: '#f3e4e7',
    ink: '#241016',
    muted: '#6d4a54',
    accent: '#c81e3a',
    accentSoft: '#e8c547',
    topbarInk: '#fff4f6',
    line: 'rgba(36, 16, 22, 0.12)',
  },
};

export const THEME_PRESET_IDS = Object.keys(THEME_PRESETS);
export const DEFAULT_PRESET = 'baize';

const FONTS = {
  club: { sans: '"Inter", "Segoe UI", system-ui, sans-serif', display: '"Nunito", "Inter", sans-serif' },
  system: { sans: 'system-ui, "Segoe UI", sans-serif', display: 'system-ui, "Segoe UI", sans-serif' },
  serif: { sans: 'Georgia, "Times New Roman", serif', display: 'Georgia, "Times New Roman", serif' },
};

const RADII = { snug: 10, club: 18, round: 26 };

function hex(value, fallback) {
  const raw = String(value || '').trim();
  return /^#[0-9a-fA-F]{6}$/.test(raw) ? raw : fallback;
}

export function normalizeTheme(input = {}) {
  const src = input && typeof input === 'object' ? input : {};
  let preset = String(src.themePreset || '').toLowerCase();
  if (!preset) preset = 'custom';
  if (preset !== 'custom' && !THEME_PRESETS[preset]) preset = DEFAULT_PRESET;
  const base = THEME_PRESETS[preset === 'custom' ? DEFAULT_PRESET : preset];
  const colors = preset === 'custom'
    ? {
        bg: hex(src.bg, base.bg),
        bgSide: hex(src.bgSide, base.bgSide),
        surface: hex(src.surface, base.surface),
        surface2: hex(src.surface2, base.surface2),
        ink: hex(src.ink, base.ink),
        muted: hex(src.muted, base.muted),
        accent: hex(src.accent, base.accent),
        accentSoft: hex(src.accentSoft, base.accentSoft),
        topbarInk: hex(src.topbarInk, base.topbarInk),
        line: String(src.line || base.line),
      }
    : { ...base };
  const font = FONTS[src.font] ? src.font : 'club';
  const radiusKey = RADII[src.radius] ? src.radius : 'club';
  const density = src.density === 'compact' ? 'compact' : 'comfortable';
  return {
    themePreset: preset,
    ...colors,
    font,
    radius: radiusKey,
    density,
    inheritWp: src.inheritWp === true || src.inheritWp === '1',
    sans: FONTS[font].sans,
    display: FONTS[font].display,
    radiusPx: RADII[radiusKey],
    label: preset === 'custom' ? 'Eigen' : base.label,
  };
}

export function themeCssVars(theme) {
  const t = normalizeTheme(theme);
  return {
    '--bg': t.bg,
    '--bg-side': t.bgSide,
    '--surface': t.surface,
    '--surface-2': t.surface2,
    '--ink': t.ink,
    '--muted': t.muted,
    '--accent': t.accent,
    '--accent-2': t.accentSoft,
    '--gold': t.accentSoft,
    '--topbar-ink': t.topbarInk,
    '--line': t.line,
    '--radius': `${t.radiusPx}px`,
    '--sans': t.sans,
    '--display': t.display,
  };
}

export function themeCssText(theme) {
  const vars = themeCssVars(theme);
  const body = Object.entries(vars).map(([k, v]) => `${k}:${v}`).join(';');
  return `:root{${body}}`;
}
