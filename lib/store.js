import fs from 'fs/promises';
import path from 'path';
import crypto from 'crypto';
import { DEFAULT_BRAND, normalizeBrand, resolveHeroUrl, parseFrameFormat } from './brand.js';
import {
  clubSeason,
  decorateRankedPlayer,
  hasRealPoints,
  headToHead,
  matchRowForPlayer,
  matchesInSeason,
  normalizeRound,
  normalizeSeason,
  padFrames,
  paperInputToMatch,
  parsePaperCsv,
  playerDossier,
  resolvePlayerName,
  seasonsFromMatches,
  synthesizeFrames,
  parseFrameResult,
} from './excel.js';

export {
  clubSeason,
  dossierCsv,
  formatPct,
  headToHead,
  headToHeadCsv,
  hasRealPoints,
  matchesInSeason,
  normalizeRound,
  normalizeSeason,
  paperInputToMatch,
  parseFrameResult,
  parsePaperCsv,
  playerCareer,
  playerDossier,
  rankingCsv,
  ROUND_NAMES,
  seasonsFromMatches,
  synthesizeFrames,
} from './excel.js';

export const MATCH_TYPES = new Set([
  'competitie', 'vriendschappelijk', 'beker', 'finale',
  'potblack', 'ranking', 'handicap', '6red', 'open',
]);
export const MATCH_TYPE_LABELS = {
  competitie: 'Competitie',
  vriendschappelijk: 'Vriendschappelijk',
  beker: 'Beker',
  finale: 'Finale',
  potblack: 'Potblack',
  ranking: 'Rankingtornooi',
  handicap: 'Handicaptornooi',
  '6red': '6 Red',
  open: 'Open Merode',
};
export const MAX_BREAK = 155;

function invalid(message) {
  const err = new Error(message);
  err.code = 'INVALID';
  return err;
}

export const EVENT_KINDS = ['clubavond', 'toernooi', 'les', 'overig'];
export const EVENT_KIND_LABELS = {
  clubavond: 'Clubavond',
  toernooi: 'Tornooi',
  les: 'Les',
  overig: 'Overig',
};

function emptyState() {
  return {
    matches: [],
    roster: [],
    events: [],
    brand: { ...DEFAULT_BRAND },
  };
}

function score(value, max = 200) {
  const n = Number.parseInt(value, 10);
  if (!Number.isFinite(n) || n < 0 || n > max) return null;
  return n;
}

function cleanName(value) {
  return String(value || '').trim().replace(/\s+/g, ' ').slice(0, 80);
}

export function resolveRosterPlayer(roster, value) {
  const raw = String(value || '').trim();
  if (!raw) return null;
  const list = Array.isArray(roster) ? roster : [];
  const byId = list.find((row) => row.id === raw);
  if (byId) return byId;
  const name = cleanName(raw).toLowerCase();
  return list.find((row) => String(row.name || '').toLowerCase() === name) || null;
}

export function normalizePlayer(input, { existing, roster } = {}) {
  const name = cleanName(input?.name);
  if (!name) throw invalid('Vul een spelersnaam in.');
  const clash = (roster || []).find((row) => (
    row.id !== existing?.id && String(row.name || '').toLowerCase() === name.toLowerCase()
  ));
  if (clash) throw invalid('Die speler staat al op de lijst.');
  const now = new Date().toISOString();
  return {
    id: existing?.id || crypto.randomUUID(),
    name,
    createdAt: existing?.createdAt || now,
    updatedAt: now,
  };
}

function normalizeRoster(raw, matches) {
  if (Array.isArray(raw)) {
    const seen = new Set();
    return raw
      .map((row, index) => {
        const name = cleanName(row?.name);
        if (!name) return null;
        const key = name.toLowerCase();
        if (seen.has(key)) return null;
        seen.add(key);
        return {
          id: String(row.id || `p-${index + 1}`),
          name,
          createdAt: row.createdAt || new Date().toISOString(),
          updatedAt: row.updatedAt || row.createdAt || new Date().toISOString(),
        };
      })
      .filter(Boolean)
      .sort((a, b) => a.name.localeCompare(b.name, 'nl'));
  }
  const names = new Set();
  for (const match of matches || []) {
    const one = cleanName(match.player1);
    const two = cleanName(match.player2);
    if (one) names.add(one);
    if (two) names.add(two);
  }
  const now = new Date().toISOString();
  return [...names]
    .sort((a, b) => a.localeCompare(b, 'nl'))
    .map((name, index) => ({
      id: `p-${index + 1}`,
      name,
      createdAt: now,
      updatedAt: now,
    }));
}

function publicRoster(roster) {
  return (roster || []).map((row) => ({ id: row.id, name: row.name }));
}

function normalizeSignature(raw) {
  if (!raw) return '';
  const value = String(raw);
  if (!value.startsWith('data:image/')) return '';
  if (value.length > 140_000) throw invalid('Handtekening is te groot.');
  return value;
}

function normalizeFrames(input, count = 5) {
  const rows = Array.isArray(input) ? input : [];
  const frames = [];
  for (let i = 0; i < count; i += 1) {
    const row = rows[i] || {};
    const p1 = score(row.p1 ?? row.player1 ?? 0);
    const p2 = score(row.p2 ?? row.player2 ?? 0);
    if (p1 == null || p2 == null) throw invalid(`Frame ${i + 1} heeft ongeldige punten.`);
    frames.push({ p1, p2 });
  }
  return frames;
}

export function frameWins(frames) {
  let a = 0;
  let b = 0;
  for (const frame of frames || []) {
    if (frame.p1 > frame.p2) a += 1;
    else if (frame.p2 > frame.p1) b += 1;
  }
  return { p1: a, p2: b };
}

export function frameTotals(frames) {
  return (frames || []).reduce(
    (sum, frame) => ({ p1: sum.p1 + (Number(frame.p1) || 0), p2: sum.p2 + (Number(frame.p2) || 0) }),
    { p1: 0, p2: 0 },
  );
}

export function playedFrames(frames) {
  return (frames || []).filter((frame) => (Number(frame?.p1) || 0) + (Number(frame?.p2) || 0) > 0);
}

export function averagePoints(values) {
  if (!values.length) return 0;
  return Math.round((values.reduce((sum, value) => sum + value, 0) / values.length) * 10) / 10;
}

export function matchAverages(frames) {
  const played = playedFrames(frames);
  return {
    handicap1: averagePoints(played.map((frame) => Number(frame.p1) || 0)),
    handicap2: averagePoints(played.map((frame) => Number(frame.p2) || 0)),
    framesPlayed: played.length,
  };
}

export function matchHighestBreak(match) {
  const one = Number(match.break1) || 0;
  const two = Number(match.break2) || 0;
  if (one >= two && one > 0) return { value: one, player: match.player1 };
  if (two > 0) return { value: two, player: match.player2 };
  return { value: 0, player: '' };
}

export function decorateMatch(match) {
  const wins = frameWins(match.frames);
  const totals = frameTotals(match.frames);
  const averages = matchAverages(match.frames);
  const winner = wins.p1 === wins.p2 ? '' : wins.p1 > wins.p2 ? match.player1 : match.player2;
  const centuries = [match.break1, match.break2].filter((value) => Number(value) >= 100).length;
  const pointsKnown = hasRealPoints(match.frames);
  return {
    ...match,
    season: match.season || clubSeason(match.date),
    round: normalizeRound(match.round),
    source: match.source || (pointsKnown ? 'guest' : 'paper'),
    pointsKnown,
    wins,
    totals,
    handicap1: pointsKnown ? averages.handicap1 : 0,
    handicap2: pointsKnown ? averages.handicap2 : 0,
    framesPlayed: averages.framesPlayed,
    winner,
    highestBreak: matchHighestBreak(match),
    centuries,
    close: Math.abs(wins.p1 - wins.p2) === 1 && wins.p1 + wins.p2 > 0,
    signature1: match.signature1 || match.signature || '',
    signature2: match.signature2 || '',
    signed: Boolean((match.signature1 || match.signature) && match.signature2),
  };
}

export function normalizeMatch(input, { existing, roster } = {}) {
  const tournament = cleanName(input.tournament);
  const date = String(input.date || '').trim();
  let player1 = cleanName(input.player1);
  let player2 = cleanName(input.player2);
  if (!tournament) throw invalid('Vul een tornooi in.');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw invalid('Datum moet jjjj-mm-dd zijn.');
  if (roster) {
    if (!roster.length) throw invalid('Voeg eerst spelers toe in het clubbeheer.');
    const one = resolveRosterPlayer(roster, input.player1Id || input.player1);
    const two = resolveRosterPlayer(roster, input.player2Id || input.player2);
    if (!one || !two) throw invalid('Kies twee spelers uit de clublijst.');
    if (one.id === two.id) throw invalid('Kies twee verschillende spelers.');
    player1 = one.name;
    player2 = two.name;
  } else if (!player1 || !player2) {
    throw invalid('Vul beide spelers in.');
  }
  if (player1.toLowerCase() === player2.toLowerCase()) {
    throw invalid('Kies twee verschillende spelers.');
  }
  const paper = parseFrameResult(input);
  const rawFrames = Array.isArray(input.frames) ? input.frames : [];
  const hasScoredFrames = rawFrames.some((frame) => (
    (Number(frame?.p1) || 0) + (Number(frame?.p2) || 0) > 0
  ));
  const format = parseFrameFormat(
    input.frameFormat
      || (input.frameMode || existing?.frameMode
        ? `${input.frameMode || existing?.frameMode}:${input.bestOf ?? existing?.bestOf ?? 5}`
        : ''),
    score(input.bestOf ?? existing?.bestOf ?? 5, 17) || 5,
    existing?.frameMode || 'bestof',
  );
  const slots = Math.max(format.framesCount, rawFrames.length, 1);
  const frames = hasScoredFrames
    ? padFrames(normalizeFrames(rawFrames, slots), slots)
    : paper
      ? padFrames(synthesizeFrames(paper.framesFor, paper.framesAgainst), Math.max(slots, paper.framesFor + paper.framesAgainst))
      : normalizeFrames(input.frames, slots);
  const averages = matchAverages(frames);
  const pointsKnown = hasRealPoints(frames);
  const break1 = score(input.break1 ?? 0, MAX_BREAK);
  const break2 = score(input.break2 ?? 0, MAX_BREAK);
  if (break1 == null || break2 == null) throw invalid('Break is ongeldig (0–155). 147 is het maximum zonder free ball.');
  const type = String(input.matchType || existing?.matchType || '').toLowerCase();
  const sourceRaw = String(input.source || existing?.source || (paper && !hasScoredFrames ? 'paper' : 'guest')).toLowerCase();
  const source = sourceRaw === 'paper' || sourceRaw === 'admin' ? sourceRaw : 'guest';
  const now = new Date().toISOString();
  return {
    id: existing?.id || crypto.randomUUID(),
    tournament,
    date,
    player1,
    player2,
    handicap1: pointsKnown ? averages.handicap1 : 0,
    handicap2: pointsKnown ? averages.handicap2 : 0,
    framesPlayed: averages.framesPlayed,
    frames,
    break1,
    break2,
    matchType: MATCH_TYPES.has(type) ? type : '',
    table: cleanName(input.table ?? existing?.table ?? '').slice(0, 40),
    referee: cleanName(input.referee ?? existing?.referee ?? '').slice(0, 80),
    note: String(input.note ?? existing?.note ?? '').trim().slice(0, 200),
    bestOf: format.framesCount,
    frameMode: format.frameMode,
    season: normalizeSeason(input.season ?? existing?.season, date),
    round: normalizeRound(input.round ?? existing?.round),
    source,
    signature1: normalizeSignature(input.signature1 || input.signature) || existing?.signature1 || existing?.signature || '',
    signature2: normalizeSignature(input.signature2) || existing?.signature2 || '',
    createdAt: existing?.createdAt || now,
    updatedAt: now,
  };
}

function blankPlayer(name) {
  return {
    name,
    played: 0,
    wins: 0,
    losses: 0,
    draws: 0,
    points: 0,
    framesFor: 0,
    framesAgainst: 0,
    pointsFor: 0,
    pointsAgainst: 0,
    playedPoints: 0,
    playedFrameCount: 0,
    highestBreak: 0,
    centuries: 0,
    breakSum: 0,
    breakCount: 0,
    lastPlayed: '',
  };
}

export function rankPlayers(matches, roster = []) {
  const map = new Map();
  const touch = (name) => {
    const key = cleanName(name);
    if (!key) return null;
    if (!map.has(key)) map.set(key, blankPlayer(key));
    return map.get(key);
  };

  for (const row of roster || []) touch(row.name);

  for (const match of matches || []) {
    const decorated = decorateMatch(match);
    const one = touch(decorated.player1);
    const two = touch(decorated.player2);
    if (!one || !two) continue;
    const rows = [
      [one, matchRowForPlayer(decorated, decorated.player1)],
      [two, matchRowForPlayer(decorated, decorated.player2)],
    ];
    for (const [player, row] of rows) {
      if (!row) continue;
      player.played += 1;
      player.framesFor += Number(row.framesFor) || 0;
      player.framesAgainst += Number(row.framesAgainst) || 0;
      player.pointsFor += Number(row.pointsFor) || 0;
      player.pointsAgainst += Number(row.pointsAgainst) || 0;
      player.playedPoints += Number(row.pointsFor) || 0;
      player.playedFrameCount += Number(row.realFrames) || 0;
      player.highestBreak = Math.max(player.highestBreak, Number(row.breaks) || 0);
      if ((Number(row.breaks) || 0) > 0) {
        player.breakSum += Number(row.breaks) || 0;
        player.breakCount += 1;
      }
      if ((Number(row.breaks) || 0) >= 100) player.centuries += 1;
      if (row.w === 1) {
        player.wins += 1;
        player.points += 2;
      } else if (row.l === 1) {
        player.losses += 1;
      } else {
        player.draws += 1;
        player.points += 1;
      }
      if (String(row.date || '') >= player.lastPlayed) player.lastPlayed = row.date;
    }
  }

  return [...map.values()]
    .map((player) => {
      const { playedPoints, playedFrameCount, breakSum, breakCount, ...rest } = player;
      return decorateRankedPlayer({
        ...rest,
        breakSum,
        breakCount,
        handicap: playedFrameCount ? Math.round((playedPoints / playedFrameCount) * 10) / 10 : 0,
        framesPlayed: playedFrameCount,
        frameDiff: player.framesFor - player.framesAgainst,
        winRate: player.played ? Math.round((player.wins / player.played) * 100) : 0,
      });
    })
    .sort((a, b) => (
      b.points - a.points
      || b.wins - a.wins
      || b.frameDiff - a.frameDiff
      || b.highestBreak - a.highestBreak
      || b.played - a.played
      || a.name.localeCompare(b.name)
    ))
    .map((player, index) => ({
      ...player,
      rank: index + 1,
      trophy: player.played > 0 && index < 3 ? ['gold', 'silver', 'bronze'][index] : '',
    }));
}

export function summarizeMatches(matches, roster = [], brand = {}, events = [], today = clubToday()) {
  let highest = { value: 0, player: '', date: '', tournament: '', matchId: '' };
  let centuries = 0;
  let closest = null;
  for (const match of matches) {
    const decorated = decorateMatch(match);
    centuries += decorated.centuries;
    const current = decorated.highestBreak;
    if (current.value > highest.value) {
      highest = {
        value: current.value,
        player: current.player,
        date: match.date,
        tournament: match.tournament,
        matchId: match.id,
      };
    }
    if (decorated.close && (!closest || decorated.wins.p1 + decorated.wins.p2 > (closest.wins.p1 + closest.wins.p2))) {
      closest = decorated;
    }
  }
  const players = rankPlayers(matches, roster);
  const recent = [...matches]
    .sort((a, b) => String(b.date).localeCompare(String(a.date)) || String(b.createdAt).localeCompare(String(a.createdAt)))
    .slice(0, 8)
    .map(decorateMatch);
  const tournaments = [...new Set(matches.map((match) => match.tournament).filter(Boolean))];
  return {
    matchCount: matches.length,
    tournamentCount: tournaments.length,
    playerCount: players.length,
    centuries,
    highestBreak: highest,
    closest,
    players,
    top3: players.slice(0, 3),
    recent,
    tournaments,
    roster: publicRoster(roster),
    kpis: clubKpis(matches, roster, players, brand, events, today),
  };
}

export const CLUB_TZ = process.env.CLUB_TZ || 'Europe/Amsterdam';

export function clubToday(now = Date.now()) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: CLUB_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(now));
}

export function isoWeekStart(today = clubToday()) {
  const [year, month, day] = String(today).split('-').map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  const weekday = (date.getUTCDay() + 6) % 7;
  date.setUTCDate(date.getUTCDate() - weekday);
  return date.toISOString().slice(0, 10);
}

export function formatNextEvent(event, fallback = '') {
  if (!event) return fallback;
  return [event.title, event.date, event.start].filter(Boolean).join(' · ');
}

export function progressMeter(value, goal) {
  const current = Number(value) || 0;
  const target = Number(goal) || 0;
  const pct = target > 0 ? Math.min(100, Math.round((current / target) * 100)) : 0;
  return { value: current, goal: target, pct };
}

function monthKeyOf(date) {
  return String(date || '').slice(0, 7);
}

function shiftMonth(year, month, delta) {
  const date = new Date(year, month - 1 + delta, 1);
  return { year: date.getFullYear(), month: date.getMonth() + 1 };
}

export function resolveAgendaMonth(year, month, today = clubToday()) {
  const y = Number.parseInt(year, 10);
  const m = Number.parseInt(month, 10);
  if (Number.isFinite(y) && y >= 2000 && y <= 2100 && Number.isFinite(m) && m >= 1 && m <= 12) {
    return { year: y, month: m };
  }
  return { year: Number(today.slice(0, 4)), month: Number(today.slice(5, 7)) };
}

function normalizeTime(value) {
  const raw = String(value || '').trim();
  if (!raw) return '';
  const match = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
  if (!match) throw invalid('Gebruik een tijd als 19:30.');
  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (hour > 23 || minute > 59) throw invalid('Gebruik een tijd als 19:30.');
  return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

export function normalizeEvent(input, { existing } = {}) {
  const title = String(input?.title || '').trim().replace(/\s+/g, ' ').slice(0, 80);
  if (!title) throw invalid('Vul een titel in.');
  const date = String(input?.date || '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw invalid('Kies een geldige datum.');
  const kind = EVENT_KINDS.includes(input?.kind) ? input.kind : 'clubavond';
  const start = normalizeTime(input?.start);
  const end = normalizeTime(input?.end);
  if (start && end && end < start) throw invalid('Eindtijd valt voor de start.');
  const tournament = cleanName(input?.tournament ?? existing?.tournament ?? '').slice(0, 80);
  const now = new Date().toISOString();
  return {
    id: existing?.id || crypto.randomUUID(),
    title,
    date,
    start,
    end,
    kind,
    kindLabel: EVENT_KIND_LABELS[kind],
    tournament,
    place: String(input?.place || '').trim().slice(0, 80),
    note: String(input?.note || '').trim().slice(0, 200),
    createdAt: existing?.createdAt || now,
    updatedAt: now,
  };
}

function sortEvents(events) {
  return [...events].sort((a, b) => (
    String(a.date).localeCompare(String(b.date))
    || String(a.start || '99:99').localeCompare(String(b.start || '99:99'))
    || String(a.title).localeCompare(String(b.title), 'nl')
  ));
}

function normalizeEvents(raw) {
  if (!Array.isArray(raw)) return [];
  return sortEvents(raw.map((row) => {
    try {
      return normalizeEvent(row, { existing: row });
    } catch {
      return null;
    }
  }).filter(Boolean));
}

export function buildMonthAgenda(events, year, month, today = clubToday()) {
  const { year: y, month: m } = resolveAgendaMonth(year, month, today);
  const first = new Date(y, m - 1, 1);
  const startWeekday = (first.getDay() + 6) % 7;
  const daysInMonth = new Date(y, m, 0).getDate();
  const prefix = `${y}-${String(m).padStart(2, '0')}`;
  const inMonth = sortEvents((events || []).filter((event) => monthKeyOf(event.date) === prefix));
  const byDate = new Map();
  for (const event of inMonth) {
    const list = byDate.get(event.date) || [];
    list.push(event);
    byDate.set(event.date, list);
  }
  const days = [];
  for (let i = 0; i < startWeekday; i += 1) {
    days.push({ date: '', day: 0, empty: true, today: false, events: [] });
  }
  for (let day = 1; day <= daysInMonth; day += 1) {
    const date = `${prefix}-${String(day).padStart(2, '0')}`;
    days.push({
      date,
      day,
      empty: false,
      today: date === today,
      events: byDate.get(date) || [],
    });
  }
  const prev = shiftMonth(y, m, -1);
  const next = shiftMonth(y, m, 1);
  return {
    year: y,
    month: m,
    label: first.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' }),
    weekdays: ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'],
    days,
    events: inMonth,
    upcoming: sortEvents((events || []).filter((event) => String(event.date) >= today)).slice(0, 10),
    prev,
    next,
  };
}

export function clubKpis(matches, roster = [], players = [], brand = {}, events = [], today = clubToday()) {
  const decorated = (matches || []).map(decorateMatch);
  const weekStart = isoWeekStart(today);
  const month = monthKeyOf(today);
  const lastMonth = (() => {
    const [year, mon] = month.split('-').map(Number);
    const shifted = shiftMonth(year, mon, -1);
    return `${shifted.year}-${String(shifted.month).padStart(2, '0')}`;
  })();
  const thisMonth = decorated.filter((match) => monthKeyOf(match.date) === month);
  const framesPlayed = decorated.reduce((sum, match) => sum + (match.framesPlayed || 0), 0);
  const framesMonth = thisMonth.reduce((sum, match) => sum + (match.framesPlayed || 0), 0);
  const closeMatches = decorated.filter((match) => match.close).length;
  const centuries = decorated.reduce((sum, match) => sum + (match.centuries || 0), 0);
  const highestBreak = decorated.reduce((max, match) => Math.max(max, match.highestBreak?.value || 0), 0);
  const activePlayers = (players || []).filter((player) => player.played > 0).length;
  const rosterCount = (roster || []).length;
  const handicaps = (players || []).filter((player) => player.framesPlayed > 0).map((player) => player.handicap);
  const avgHandicap = handicaps.length
    ? Math.round((handicaps.reduce((sum, value) => sum + value, 0) / handicaps.length) * 10) / 10
    : 0;
  const goals = {
    matchesMonth: brand.goalMatchesMonth || DEFAULT_BRAND.goalMatchesMonth,
    matchesWeek: brand.goalMatchesWeek || DEFAULT_BRAND.goalMatchesWeek,
    framesMonth: brand.goalFramesMonth || DEFAULT_BRAND.goalFramesMonth,
    centuries: brand.goalCenturies || DEFAULT_BRAND.goalCenturies,
    newMembersMonth: brand.goalNewMembersMonth || DEFAULT_BRAND.goalNewMembersMonth,
    clubNightsMonth: brand.goalClubNightsMonth || DEFAULT_BRAND.goalClubNightsMonth,
  };
  const matchesWeek = decorated.filter((match) => String(match.date || '') >= weekStart && String(match.date || '') <= today).length;
  const matchesMonth = thisMonth.length;
  const newMembersMonth = (roster || []).filter((row) => monthKeyOf(row.createdAt) === month).length;
  const nights = (events || []).filter((event) => event.kind === 'clubavond');
  const clubNightsMonth = nights.filter((event) => monthKeyOf(event.date) === month).length;
  const clubNightsUpcoming = nights.filter((event) => String(event.date) >= today).length;
  const nextEvent = sortEvents((events || []).filter((event) => String(event.date) >= today))[0] || null;
  return {
    matchesToday: decorated.filter((match) => match.date === today).length,
    matchesWeek,
    matchesMonth,
    matchesLastMonth: decorated.filter((match) => monthKeyOf(match.date) === lastMonth).length,
    framesPlayed,
    framesMonth,
    closeMatches,
    rosterCount,
    activePlayers,
    avgHandicap,
    newMembersMonth,
    clubNightsMonth,
    clubNightsUpcoming,
    nextEvent,
    nextEventLabel: formatNextEvent(nextEvent, brand.nextEvent || ''),
    goals,
    progress: {
      week: progressMeter(matchesWeek, goals.matchesWeek),
      month: progressMeter(matchesMonth, goals.matchesMonth),
      frames: progressMeter(framesMonth, goals.framesMonth),
      active: progressMeter(activePlayers, rosterCount || 1),
      break: progressMeter(highestBreak, 147),
      centuries: progressMeter(centuries, goals.centuries),
      members: progressMeter(newMembersMonth, goals.newMembersMonth),
      nights: progressMeter(clubNightsMonth, goals.clubNightsMonth),
    },
  };
}

export function createStore(dataDir) {
  const file = path.join(dataDir, 'club.json');
  const heroFile = path.join(dataDir, 'hero-custom');
  let cache = null;
  let queue = Promise.resolve();

  function withLock(fn) {
    const run = queue.then(fn, fn);
    queue = run.then(() => undefined, () => undefined);
    return run;
  }

  async function readState() {
    if (cache) return cache;
    try {
      const raw = await fs.readFile(file, 'utf8');
      const parsed = JSON.parse(raw);
      cache = {
        matches: Array.isArray(parsed.matches) ? parsed.matches : [],
        roster: normalizeRoster(parsed.roster, parsed.matches),
        events: normalizeEvents(parsed.events),
        brand: normalizeBrand(parsed.brand),
      };
    } catch (err) {
      if (err.code !== 'ENOENT') throw err;
      cache = emptyState();
    }
    return cache;
  }

  async function writeState(state) {
    cache = state;
    await fs.mkdir(dataDir, { recursive: true });
    const tmp = `${file}.${process.pid}.tmp`;
    await fs.writeFile(tmp, `${JSON.stringify(state, null, 2)}\n`, 'utf8');
    await fs.rename(tmp, file);
  }

  return {
    async listMatches() {
      const state = await readState();
      return [...state.matches]
        .sort((a, b) => String(b.date).localeCompare(String(a.date)) || String(b.createdAt).localeCompare(String(a.createdAt)))
        .map(decorateMatch);
    },
    async getMatch(id) {
      const state = await readState();
      const match = state.matches.find((row) => row.id === id);
      return match ? decorateMatch(match) : null;
    },
    async createMatch(input, { requireSignatures = true } = {}) {
      return withLock(async () => {
        const state = await readState();
        const match = normalizeMatch(input, { roster: state.roster });
        if (requireSignatures && (!match.signature1 || !match.signature2)) {
          throw invalid('Beide spelers moeten een handtekening zetten.');
        }
        state.matches.push(match);
        await writeState(state);
        return decorateMatch(match);
      });
    },
    async createPaperMatch(input) {
      return this.createMatch({ ...paperInputToMatch(input), source: 'paper' }, { requireSignatures: false });
    },
    async importPaper(input = {}) {
      return withLock(async () => {
        const state = await readState();
        const fallback = cleanName(input.player || input.player1);
        const rows = Array.isArray(input.rows) && input.rows.length
          ? input.rows
          : parsePaperCsv(input.csv || '', fallback);
        if (!rows.length) throw invalid('Geen papierregels gevonden.');
        const created = [];
        for (const row of rows) {
          const draft = paperInputToMatch({ ...row, player: row.player || fallback });
          for (const name of [draft.player1, draft.player2]) {
            if (!name) throw invalid('Elke regel heeft een speler en een tegenstander nodig.');
            if (!resolveRosterPlayer(state.roster, name)) {
              const player = normalizePlayer({ name }, { roster: state.roster });
              state.roster = [...state.roster, player].sort((a, b) => a.name.localeCompare(b.name, 'nl'));
            }
          }
          const match = normalizeMatch({ ...draft, source: 'paper' }, { roster: state.roster });
          state.matches.push(match);
          created.push(decorateMatch(match));
        }
        await writeState(state);
        return { matches: created, count: created.length, roster: publicRoster(state.roster) };
      });
    },
    async playerDossier(name, season = '') {
      const state = await readState();
      const resolved = resolvePlayerName(name, state.matches, state.roster);
      const matches = matchesInSeason(state.matches, season);
      return {
        ...playerDossier(matches, resolved, state.roster),
        season: normalizeSeason(season),
        seasons: seasonsFromMatches(state.matches),
      };
    },
    async report(season = '') {
      const state = await readState();
      const want = normalizeSeason(season);
      const matches = matchesInSeason(state.matches, want);
      const players = rankPlayers(matches, state.roster);
      return {
        brand: normalizeBrand(state.brand),
        season: want,
        seasons: seasonsFromMatches(state.matches),
        players,
        top3: players.slice(0, 3),
        matchCount: matches.length,
        printedAt: new Date().toISOString().slice(0, 10),
      };
    },
    async headToHead(playerA, playerB) {
      const state = await readState();
      return headToHead(state.matches, playerA, playerB);
    },
    async updateMatch(id, input) {
      return withLock(async () => {
        const state = await readState();
        const index = state.matches.findIndex((match) => match.id === id);
        if (index < 0) return null;
        const match = normalizeMatch(input, { existing: state.matches[index], roster: state.roster });
        state.matches[index] = match;
        await writeState(state);
        return decorateMatch(match);
      });
    },
    async deleteMatch(id) {
      return withLock(async () => {
        const state = await readState();
        const before = state.matches.length;
        state.matches = state.matches.filter((match) => match.id !== id);
        if (state.matches.length === before) return false;
        await writeState(state);
        return true;
      });
    },
    async listPlayers() {
      const state = await readState();
      return publicRoster(state.roster);
    },
    async createPlayer(input) {
      return withLock(async () => {
        const state = await readState();
        const player = normalizePlayer(input, { roster: state.roster });
        state.roster = [...state.roster, player].sort((a, b) => a.name.localeCompare(b.name, 'nl'));
        await writeState(state);
        return player;
      });
    },
    async updatePlayer(id, input) {
      return withLock(async () => {
        const state = await readState();
        const index = state.roster.findIndex((row) => row.id === id);
        if (index < 0) return null;
        const previous = state.roster[index];
        const player = normalizePlayer(input, { existing: previous, roster: state.roster });
        state.roster[index] = player;
        state.roster.sort((a, b) => a.name.localeCompare(b.name, 'nl'));
        if (previous.name !== player.name) {
          for (const match of state.matches) {
            if (match.player1 === previous.name) match.player1 = player.name;
            if (match.player2 === previous.name) match.player2 = player.name;
          }
        }
        await writeState(state);
        return player;
      });
    },
    async deletePlayer(id) {
      return withLock(async () => {
        const state = await readState();
        const player = state.roster.find((row) => row.id === id);
        if (!player) return false;
        const used = state.matches.some((match) => match.player1 === player.name || match.player2 === player.name);
        if (used) throw invalid('Deze speler heeft wedstrijden. Verwijder die eerst of hernoem de speler.');
        state.roster = state.roster.filter((row) => row.id !== id);
        await writeState(state);
        return true;
      });
    },
    async listEvents() {
      const state = await readState();
      return sortEvents(state.events);
    },
    async createEvent(input) {
      return withLock(async () => {
        const state = await readState();
        const event = normalizeEvent(input);
        state.events = sortEvents([...state.events, event]);
        await writeState(state);
        return event;
      });
    },
    async updateEvent(id, input) {
      return withLock(async () => {
        const state = await readState();
        const index = state.events.findIndex((event) => event.id === id);
        if (index < 0) return null;
        const event = normalizeEvent(input, { existing: state.events[index] });
        state.events[index] = event;
        state.events = sortEvents(state.events);
        await writeState(state);
        return event;
      });
    },
    async deleteEvent(id) {
      return withLock(async () => {
        const state = await readState();
        const before = state.events.length;
        state.events = state.events.filter((event) => event.id !== id);
        if (state.events.length === before) return false;
        await writeState(state);
        return true;
      });
    },
    async agenda(year, month) {
      const state = await readState();
      return buildMonthAgenda(state.events, year, month);
    },
    async getBrand() {
      const state = await readState();
      return normalizeBrand(state.brand);
    },
    async saveBrand(input) {
      return withLock(async () => {
        const state = await readState();
        state.brand = normalizeBrand({ ...state.brand, ...input });
        await writeState(state);
        return state.brand;
      });
    },
    async saveHero(dataUrl, publicPath) {
      const match = String(dataUrl || '').match(/^data:image\/(png|jpe?g);base64,([a-z0-9+/=\s]+)$/i);
      if (!match) throw invalid('Stuur een JPG of PNG.');
      const buf = Buffer.from(match[2].replace(/\s+/g, ''), 'base64');
      if (buf.length < 80 || buf.length > 750_000) throw invalid('Achtergrond moet tussen 80 bytes en 750 kB zijn.');
      await fs.mkdir(dataDir, { recursive: true });
      await fs.writeFile(heroFile, buf);
      return this.saveBrand({ heroUrl: publicPath });
    },
    async readHero() {
      try {
        return await fs.readFile(heroFile);
      } catch {
        return null;
      }
    },
    async overview(basePath = '/webhost/snooker') {
      const state = await readState();
      const brand = normalizeBrand(state.brand);
      const today = clubToday();
      const summary = summarizeMatches(state.matches, state.roster, brand, state.events, today);
      return {
        brand: { ...brand, heroUrl: resolveHeroUrl(brand, basePath) },
        agenda: buildMonthAgenda(state.events, undefined, undefined, today),
        nextEvent: summary.kpis.nextEvent,
        nextEventLabel: summary.kpis.nextEventLabel,
        ...summary,
      };
    },
  };
}
