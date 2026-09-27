/**
 * Paper → Excel stats: ranking columns, player dossier, head-to-head.
 * Seizoen loopt augustus–juli (2016-08-01 hoort bij 2016-2017).
 */

export const ROUND_NAMES = ['8ste finale', 'kwart finale', 'halve finale', 'finale'];

function clean(value, max = 80) {
  return String(value || '').trim().replace(/\s+/g, ' ').slice(0, max);
}

function sameName(a, b) {
  return clean(a).toLowerCase() === clean(b).toLowerCase() && Boolean(clean(a));
}

export function clubSeason(date) {
  const match = String(date || '').match(/^(\d{4})-(\d{2})-\d{2}$/);
  if (!match) return '';
  const year = Number(match[1]);
  const month = Number(match[2]);
  const start = month >= 8 ? year : year - 1;
  return `${start}-${start + 1}`;
}

export function seasonStartDate(season) {
  const match = String(season || '').match(/^(\d{4})\s*[-/]\s*(\d{4})$/);
  if (!match) return '';
  return `${match[1]}-08-01`;
}

export function normalizeSeason(value, date = '') {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{4})\s*[-/]\s*(\d{2,4})$/);
  if (match) {
    const start = Number(match[1]);
    let end = Number(match[2]);
    if (end < 100) end += 2000;
    if (end === start + 1) return `${start}-${end}`;
  }
  return clubSeason(date);
}

export function normalizeRound(value) {
  let raw = clean(value, 40).toLowerCase();
  if (!raw) return '';
  raw = raw.replace(/^[hv]\s*[.\-:]?\s*/i, '');
  raw = raw.replace(/kwart\s*-?\s*finale/g, 'kwart finale');
  raw = raw.replace(/halve?\s*-?\s*finale/g, 'halve finale');
  raw = raw.replace(/\b8e\b|\b8e\s+finale\b|\bachtste\s+finale\b/g, '8ste finale');
  if (raw === 'finale' || raw === 'endstrijd') return 'finale';
  if (ROUND_NAMES.includes(raw)) return raw;
  if (raw === 'kwartfinale') return 'kwart finale';
  if (raw === 'halvefinale' || raw === 'halve') return 'halve finale';
  if (raw === '8ste' || raw === '8ste finale') return '8ste finale';
  return clean(value, 40);
}

export function parseFrameResult(input = {}) {
  const directFor = Number.parseInt(input.framesFor ?? input.fPlus ?? input['F+'], 10);
  const directAgainst = Number.parseInt(input.framesAgainst ?? input.fMinus ?? input['F-'], 10);
  if (Number.isFinite(directFor) && Number.isFinite(directAgainst) && directFor >= 0 && directAgainst >= 0) {
    return { framesFor: directFor, framesAgainst: directAgainst };
  }
  const raw = String(input.result ?? input.RESULT ?? input.uitslag ?? '').trim();
  const scored = raw.match(/^(\d+)\s*[-–:/]\s*(\d+)$/);
  if (scored) {
    return { framesFor: Number(scored[1]), framesAgainst: Number(scored[2]) };
  }
  const win = Number.parseInt(input.w ?? input.W ?? input.win, 10);
  const loss = Number.parseInt(input.l ?? input.L ?? input.loss, 10);
  if (win === 1 && loss !== 1) return { framesFor: 1, framesAgainst: 0 };
  if (loss === 1 && win !== 1) return { framesFor: 0, framesAgainst: 1 };
  return null;
}

export function synthesizeFrames(framesFor, framesAgainst, slots = 5) {
  const won = Math.max(0, Number.parseInt(framesFor, 10) || 0);
  const lost = Math.max(0, Number.parseInt(framesAgainst, 10) || 0);
  const need = Math.min(Math.max(slots, won + lost), 17);
  const frames = [];
  for (let i = 0; i < won; i += 1) frames.push({ p1: 1, p2: 0 });
  for (let i = 0; i < lost; i += 1) frames.push({ p1: 0, p2: 1 });
  while (frames.length < need) frames.push({ p1: 0, p2: 0 });
  return frames.slice(0, need);
}

export function hasRealPoints(frames) {
  return (frames || []).some((frame) => (Number(frame?.p1) || 0) > 1 || (Number(frame?.p2) || 0) > 1);
}

export function padFrames(frames, slots = 5) {
  const rows = Array.isArray(frames) ? frames.map((frame) => ({
    p1: Number(frame?.p1) || 0,
    p2: Number(frame?.p2) || 0,
  })) : [];
  while (rows.length < slots) rows.push({ p1: 0, p2: 0 });
  return rows.slice(0, Math.max(slots, rows.length));
}

export function paperInputToMatch(input = {}) {
  const player1 = clean(input.player1 || input.player || input.PLAYER);
  const player2 = clean(input.player2 || input.versus || input.VERSUS);
  const tournament = clean(input.tournament || input.TOURNAMENT);
  const season = normalizeSeason(input.season || input.SEASON, input.date);
  const date = String(input.date || input.DATE || '').trim() || seasonStartDate(season);
  const parsed = parseFrameResult(input);
  const hasFrames = Array.isArray(input.frames) && input.frames.some((frame) => (
    (Number(frame?.p1) || 0) + (Number(frame?.p2) || 0) > 0
  ));
  const frames = hasFrames
    ? padFrames(input.frames, 5)
    : synthesizeFrames(parsed?.framesFor || 0, parsed?.framesAgainst || 0);
  const break1 = Number.parseInt(input.break1 ?? input.breaks ?? input.BREAKS ?? 0, 10) || 0;
  const break2 = Number.parseInt(input.break2 ?? 0, 10) || 0;
  return {
    tournament,
    date,
    player1,
    player2,
    frames,
    break1,
    break2,
    season,
    round: normalizeRound(input.round || input.ROUND),
    source: 'paper',
    matchType: String(input.matchType || input.type || 'competitie').toLowerCase(),
    table: clean(input.table || '', 40),
    referee: clean(input.referee || '', 80),
    note: String(input.note || '').trim().slice(0, 200),
  };
}

export function realPointFrames(frames) {
  return (frames || []).filter((frame) => (Number(frame?.p1) || 0) > 1 || (Number(frame?.p2) || 0) > 1)
    .map((frame) => ({ p1: Number(frame.p1) || 0, p2: Number(frame.p2) || 0 }));
}

export function resolvePlayerName(value, matches = [], roster = []) {
  const raw = clean(String(value || '').replace(/\+/g, ' '));
  if (!raw) return '';
  for (const row of roster || []) {
    if (row?.id && (String(row.id) === String(value) || String(row.id) === raw)) return clean(row.name);
    if (sameName(row?.name, raw)) return clean(row.name);
  }
  for (const match of matches || []) {
    if (sameName(match.player1, raw)) return clean(match.player1);
    if (sameName(match.player2, raw)) return clean(match.player2);
  }
  return raw;
}

export function emptyDossier(player = '') {
  return { player: clean(player), rows: [], career: playerCareer([]), headToHead: [] };
}

function frameWinsOf(match) {
  let p1 = 0;
  let p2 = 0;
  for (const frame of match?.frames || []) {
    if ((Number(frame.p1) || 0) > (Number(frame.p2) || 0)) p1 += 1;
    else if ((Number(frame.p2) || 0) > (Number(frame.p1) || 0)) p2 += 1;
  }
  if (p1 + p2 > 0) return { p1, p2 };
  const wins = match?.wins;
  if (wins && ((Number(wins.p1) || 0) + (Number(wins.p2) || 0) > 0)) {
    return { p1: Number(wins.p1) || 0, p2: Number(wins.p2) || 0 };
  }
  const parsed = parseFrameResult(match || {});
  if (parsed && parsed.framesFor + parsed.framesAgainst > 0) {
    return { p1: parsed.framesFor, p2: parsed.framesAgainst };
  }
  return { p1, p2 };
}

export function pct(part, whole, digits = 2) {
  if (!whole) return 0;
  const factor = 10 ** digits;
  return Math.round((part / whole) * 100 * factor) / factor;
}

export function matchRowForPlayer(match, playerName) {
  const name = clean(playerName);
  const first = sameName(match.player1, name);
  const second = sameName(match.player2, name);
  if (!first && !second) return null;
  const wins = frameWinsOf(match);
  const framesFor = first ? wins.p1 : wins.p2;
  const framesAgainst = first ? wins.p2 : wins.p1;
  const won = framesFor > framesAgainst;
  const lost = framesAgainst > framesFor;
  const outcome = won ? 'H' : lost ? 'V' : '';
  const roundName = normalizeRound(match.round);
  const breakValue = first ? Number(match.break1) || 0 : Number(match.break2) || 0;
  const real = realPointFrames(match.frames);
  const pointsFor = real.reduce((sum, frame) => sum + (first ? frame.p1 : frame.p2), 0);
  const pointsAgainst = real.reduce((sum, frame) => sum + (first ? frame.p2 : frame.p1), 0);
  return {
    matchId: match.id || '',
    date: match.date || '',
    versus: first ? match.player2 : match.player1,
    tournament: match.tournament || '',
    result: `${framesFor}-${framesAgainst}`,
    framesFor,
    framesAgainst,
    pointsFor,
    pointsAgainst,
    realFrames: real.length,
    w: won ? 1 : 0,
    l: lost ? 1 : 0,
    breaks: breakValue,
    outcome,
    round: roundName,
    roundLabel: [outcome, roundName].filter(Boolean).join(' '),
    season: match.season || clubSeason(match.date),
    source: match.source || (hasRealPoints(match.frames) ? 'guest' : 'paper'),
  };
}

export function playerCareer(rows) {
  const winsMatches = rows.filter((row) => row.w === 1).length;
  const lossesMatches = rows.filter((row) => row.l === 1).length;
  const winsFrames = rows.reduce((sum, row) => sum + (row.framesFor || 0), 0);
  const lossesFrames = rows.reduce((sum, row) => sum + (row.framesAgainst || 0), 0);
  const breaks = rows.map((row) => Number(row.breaks) || 0).filter((value) => value > 0);
  const gemBreak = breaks.length
    ? Math.round((breaks.reduce((sum, value) => sum + value, 0) / breaks.length) * 100) / 100
    : 0;
  const highestBreak = breaks.reduce((max, value) => Math.max(max, value), 0);
  const totalMatches = rows.length;
  const totalFrames = winsFrames + lossesFrames;
  const decided = winsMatches + lossesMatches;
  const realFrames = rows.reduce((sum, row) => sum + (Number(row.realFrames) || 0), 0);
  const pointsFor = rows.reduce((sum, row) => sum + (Number(row.pointsFor) || 0), 0);
  return {
    winsMatches,
    lossesMatches,
    totalMatches,
    winsFrames,
    lossesFrames,
    totalFrames,
    matchPct: pct(winsMatches, decided || totalMatches),
    framePct: pct(winsFrames, totalFrames),
    highestBreak,
    gemBreak,
    avgPoints: realFrames ? Math.round((pointsFor / realFrames) * 100) / 100 : null,
    avgFrames: totalMatches ? Math.round((winsFrames / totalMatches) * 100) / 100 : null,
  };
}

export function playerDossier(matches, playerName, roster = []) {
  const name = resolvePlayerName(playerName, matches, roster);
  if (!name) return emptyDossier();
  const rows = (matches || [])
    .map((match) => matchRowForPlayer(match, name))
    .filter(Boolean)
    .sort((a, b) => String(a.date).localeCompare(String(b.date)) || String(a.versus).localeCompare(String(b.versus), 'nl'));
  const career = playerCareer(rows);
  const opponents = new Map();
  for (const row of rows) {
    const key = row.versus.toLowerCase();
    if (!opponents.has(key)) {
      opponents.set(key, {
        versus: row.versus,
        wins: 0,
        losses: 0,
        framesFor: 0,
        framesAgainst: 0,
        lastDate: '',
        lastResult: '',
      });
    }
    const item = opponents.get(key);
    item.wins += row.w;
    item.losses += row.l;
    item.framesFor += row.framesFor;
    item.framesAgainst += row.framesAgainst;
    if (String(row.date || '') >= item.lastDate) {
      item.lastDate = row.date;
      item.lastResult = row.result;
    }
  }
  return {
    player: name,
    rows,
    career,
    headToHead: [...opponents.values()].sort((a, b) => a.versus.localeCompare(b.versus, 'nl')),
  };
}

export function headToHead(matches, playerA, playerB) {
  const a = clean(playerA);
  const b = clean(playerB);
  if (!a || !b || sameName(a, b)) {
    return {
      a, b, wins: 0, losses: 0, framesFor: 0, framesAgainst: 0, lastDate: '', lastResult: '', rows: [],
    };
  }
  const dossier = playerDossier(matches, a);
  const rows = dossier.rows.filter((row) => sameName(row.versus, b));
  const pair = dossier.headToHead.find((row) => sameName(row.versus, b)) || {
    versus: b,
    wins: 0,
    losses: 0,
    framesFor: 0,
    framesAgainst: 0,
    lastDate: '',
    lastResult: '',
  };
  return {
    a,
    b,
    wins: pair.wins,
    losses: pair.losses,
    framesFor: pair.framesFor,
    framesAgainst: pair.framesAgainst,
    lastDate: pair.lastDate,
    lastResult: pair.lastResult,
    rows,
  };
}

function csvCell(value) {
  const text = String(value ?? '');
  if (/[";\n\r]/.test(text)) return `"${text.replace(/"/g, '""')}"`;
  return text;
}

export function toCsv(headers, rows) {
  const lines = [
    headers.map((header) => csvCell(header)).join(';'),
    ...rows.map((row) => headers.map((header) => csvCell(row[header])).join(';')),
  ];
  return `\uFEFF${lines.join('\r\n')}\r\n`;
}

export function formatPct(value, digits = 2) {
  return `${Number(value || 0).toFixed(digits).replace('.', ',')}%`;
}

export function matchesInSeason(matches, season = '') {
  const want = normalizeSeason(season);
  if (!want) return [...(matches || [])];
  return (matches || []).filter((match) => {
    const have = normalizeSeason(match.season || '', match.date) || clubSeason(match.date);
    return have === want;
  });
}

export function seasonsFromMatches(matches) {
  const set = new Set();
  for (const match of matches || []) {
    const season = normalizeSeason(match.season || '', match.date) || clubSeason(match.date);
    if (season) set.add(season);
  }
  return [...set].sort().reverse();
}

export function rankingCsv(players) {
  const headers = ['#', 'Speler', 'W', 'L', 'F+', 'F-', 'M%', 'F%', 'HB', 'Gem. punten/frame'];
  const rows = (players || []).map((player) => ({
    '#': player.rank,
    Speler: player.name,
    W: player.wins,
    L: player.losses,
    'F+': player.framesFor,
    'F-': player.framesAgainst,
    'M%': formatPct(player.matchPct ?? player.winRate, player.matchPct == null ? 0 : 2),
    'F%': formatPct(player.framePct, 2),
    HB: player.highestBreak || 0,
    'Gem. punten/frame': (player.avgPoints ?? player.avgFrames) == null
      ? ''
      : String(player.avgPoints ?? player.avgFrames).replace('.', ','),
  }));
  return toCsv(headers, rows);
}

export function dossierCsv(dossier) {
  const headers = ['VERSUS', 'TOURNAMENT', 'RESULT', 'W', 'L', 'BREAKS', 'ROUND', 'SEASON'];
  const rows = (dossier?.rows || []).map((row) => ({
    VERSUS: row.versus,
    TOURNAMENT: row.tournament,
    RESULT: row.result.replace('-', '/'),
    W: row.w,
    L: row.l,
    BREAKS: row.breaks || '',
    ROUND: row.roundLabel,
    SEASON: row.season,
  }));
  return toCsv(headers, rows);
}

export function headToHeadCsv(rows) {
  const headers = ['VERSUS', 'W', 'L', 'F+', 'F-', 'Laatste'];
  return toCsv(headers, (rows || []).map((row) => ({
    VERSUS: row.versus,
    W: row.wins,
    L: row.losses,
    'F+': row.framesFor,
    'F-': row.framesAgainst,
    Laatste: row.lastResult || '',
  })));
}

function splitCsvLine(line) {
  const cells = [];
  let current = '';
  let quoted = false;
  for (let i = 0; i < line.length; i += 1) {
    const ch = line[i];
    if (quoted) {
      if (ch === '"' && line[i + 1] === '"') {
        current += '"';
        i += 1;
      } else if (ch === '"') {
        quoted = false;
      } else {
        current += ch;
      }
    } else if (ch === '"') {
      quoted = true;
    } else if (ch === ';' || ch === ',') {
      cells.push(current.trim());
      current = '';
    } else {
      current += ch;
    }
  }
  cells.push(current.trim());
  return cells;
}

export function parsePaperCsv(text, playerFallback = '') {
  const raw = String(text || '').replace(/^\uFEFF/, '').trim();
  if (!raw) return [];
  const lines = raw.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  if (!lines.length) return [];
  const header = splitCsvLine(lines[0]).map((cell) => cell.toLowerCase());
  const looksHeader = header.some((cell) => (
    ['versus', 'tegenstander', 'tournament', 'toernooi', 'result', 'uitslag', 'season', 'seizoen', 'player', 'speler'].includes(cell)
  ));
  const keys = looksHeader ? header : [];
  const start = looksHeader ? 1 : 0;
  const alias = {
    player: 'player',
    speler: 'player',
    versus: 'versus',
    tegenstander: 'versus',
    tournament: 'tournament',
    toernooi: 'tournament',
    result: 'result',
    uitslag: 'result',
    w: 'w',
    l: 'l',
    breaks: 'breaks',
    break: 'breaks',
    round: 'round',
    ronde: 'round',
    season: 'season',
    seizoen: 'season',
    date: 'date',
    datum: 'date',
    'f+': 'framesFor',
    'f-': 'framesAgainst',
  };
  const rows = [];
  for (let i = start; i < lines.length; i += 1) {
    const cells = splitCsvLine(lines[i]);
    const row = {};
    if (keys.length) {
      keys.forEach((key, index) => {
        const mapped = alias[key] || key;
        row[mapped] = cells[index] || '';
      });
    } else {
      [row.versus, row.tournament, row.result, row.w, row.l, row.breaks, row.round, row.season] = cells;
    }
    if (!row.player) row.player = playerFallback;
    if (row.versus || row.tournament) rows.push(row);
  }
  return rows;
}

export function decorateRankedPlayer(player) {
  const { breakSum, breakCount, ...rest } = player;
  const frames = (Number(rest.framesFor) || 0) + (Number(rest.framesAgainst) || 0);
  const decided = (Number(rest.wins) || 0) + (Number(rest.losses) || 0);
  const matchPct = pct(rest.wins || 0, decided || rest.played || 0);
  const framePct = pct(rest.framesFor || 0, frames);
  const gemBreak = breakCount
    ? Math.round((breakSum / breakCount) * 100) / 100
    : 0;
  const avgFrames = rest.played
    ? Math.round(((Number(rest.framesFor) || 0) / rest.played) * 100) / 100
    : null;
  return {
    ...rest,
    matchPct,
    framePct,
    gemBreak,
    avgFrames,
    avgPoints: rest.framesPlayed ? rest.handicap : null,
  };
}
