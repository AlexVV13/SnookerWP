function appBase() {
  if (typeof window !== 'undefined' && window.SNOOKER_BASE) {
    return String(window.SNOOKER_BASE).replace(/\/+$/, '') || '/';
  }
  const raw = location.pathname.replace(/\/+$/, '') || '/';
  return raw.endsWith('/admin') ? raw.slice(0, -6) || '/' : raw;
}

function apiUrl(path) {
  const rest = typeof window !== 'undefined' && window.SNOOKER_REST
    ? String(window.SNOOKER_REST).replace(/\/+$/, '')
    : '';
  let p = String(path || '');
  if (!p.startsWith('/')) p = `/${p}`;
  if (rest) {
    p = p.replace(/^\/api\/public/, '').replace(/^\/api/, '');
    if (!p) p = '/overview';
    return rest + p;
  }
  return `${appBase()}${p}`;
}

const BASE = appBase();
const TITLES = {
  home: 'Dashboard',
  matches: 'Uitslagen',
  excel: 'Papier / Excel',
  players: 'Spelers',
  agenda: 'Agenda',
  brand: 'Club',
  live: 'Liveblok',
};

async function json(url, options = {}) {
  const headers = { ...(options.headers || {}) };
  if (typeof window !== 'undefined' && window.SNOOKER_NONCE) {
    headers['X-WP-Nonce'] = window.SNOOKER_NONCE;
  }
  const res = await fetch(url, { ...options, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || data.message || `Fout ${res.status}`);
  return data;
}

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[ch]));
}

function scoreline(match) {
  const wins = match.wins || { p1: 0, p2: 0 };
  return `${wins.p1}–${wins.p2}`;
}

function formatHc(value, framesPlayed) {
  if (!framesPlayed) return '—';
  return Number(value || 0).toFixed(1);
}

function formatGem(player) {
  if (player && player.framesPlayed && player.avgPoints != null) {
    return formatHc(player.avgPoints, player.framesPlayed);
  }
  if (player && player.avgFrames != null) {
    return Number(player.avgFrames).toFixed(2).replace('.', ',');
  }
  return '—';
}

function formatPct(value, digits = 2) {
  return `${Number(value || 0).toFixed(digits).replace('.', ',')}%`;
}

function csvCell(value) {
  const text = String(value ?? '');
  if (/[";\n\r]/.test(text)) return `"${text.replace(/"/g, '""')}"`;
  return text;
}

function toCsv(headers, rows) {
  const lines = [
    headers.map((header) => csvCell(header)).join(';'),
    ...rows.map((row) => headers.map((header) => csvCell(row[header])).join(';')),
  ];
  return `\uFEFF${lines.join('\r\n')}\r\n`;
}

function downloadCsv(filename, text) {
  const blob = new Blob([text], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

function careerCards(items) {
  return items.map((item) => `
    <article class="career-card ${esc(item.tone)}">
      <p>${esc(item.label)}</p>
      <strong>${esc(item.value)}</strong>
    </article>
  `).join('');
}

function hcLine(match) {
  if (!match?.framesPlayed) return '—';
  return `${formatHc(match.handicap1, match.framesPlayed)} / ${formatHc(match.handicap2, match.framesPlayed)}`;
}

function computeFormAverages(form) {
  const frames = collectFrames(form);
  const played = frames.filter((frame) => (Number(frame.p1) || 0) + (Number(frame.p2) || 0) > 0);
  if (!played.length) return { handicap1: 0, handicap2: 0, framesPlayed: 0 };
  const avg = (key) => {
    const total = played.reduce((sum, frame) => sum + (Number(frame[key]) || 0), 0);
    return Math.round((total / played.length) * 10) / 10;
  };
  return { handicap1: avg('p1'), handicap2: avg('p2'), framesPlayed: played.length };
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function toast(message) {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = message;
  el.hidden = false;
  window.clearTimeout(toast.timer);
  toast.timer = window.setTimeout(() => { el.hidden = true; }, 2400);
}

function showTab(name) {
  document.querySelectorAll('.pane').forEach((el) => {
    el.hidden = el.id !== `tab-${name}`;
  });
  document.querySelectorAll('.side nav [data-tab]').forEach((btn) => {
    btn.classList.toggle('on', btn.dataset.tab === name);
  });
  setText('page-title', TITLES[name] || 'Beheer');
}

function fillFrames(root, frames) {
  root.innerHTML = '';
  for (let i = 0; i < 5; i += 1) {
    const frame = (frames && frames[i]) || { p1: 0, p2: 0 };
    const row = document.createElement('div');
    row.className = 'grid';
    row.innerHTML = `
      <label>Frame ${i + 1} speler 1 <input name="f${i}p1" type="number" min="0" max="200" value="${frame.p1}" /></label>
      <label>Frame ${i + 1} speler 2 <input name="f${i}p2" type="number" min="0" max="200" value="${frame.p2}" /></label>
    `;
    root.appendChild(row);
  }
}

function collectFrames(form) {
  const frames = [];
  for (let i = 0; i < 5; i += 1) {
    frames.push({ p1: form[`f${i}p1`].value, p2: form[`f${i}p2`].value });
  }
  return frames;
}

function renderRecent(target, matches) {
  const root = document.getElementById(target);
  if (!root) return;
  if (!matches.length) {
    root.innerHTML = '<p class="muted">Nog geen wedstrijden.</p>';
    return;
  }
  root.innerHTML = matches.map((match) => `
    <div class="row">
      <div>
        <strong>${esc(match.player1)} — ${esc(match.player2)}</strong>
        <small>${esc(match.tournament)} · ${esc(match.date)}${match.frameMode === 'fixed' ? ` · ${match.bestOf || ''} frames` : (match.bestOf ? ` · best of ${match.bestOf}` : '')}</small>
      </div>
      <div class="score">${scoreline(match)}</div>
    </div>
  `).join('');
}

function setMeter(id, pct) {
  const el = document.getElementById(id);
  if (el) el.style.width = `${Math.max(0, Math.min(100, Number(pct) || 0))}%`;
}

function renderStats(overview) {
  const high = overview.highestBreak || {};
  const latest = (overview.recent || [])[0];
  const kpis = overview.kpis || {};
  const progress = kpis.progress || {};
  setText('kpi-matches', String(kpis.matchesMonth ?? overview.matchCount ?? 0));
  setText('kpi-matches-sub', `${progress.month?.value || 0} van ${progress.month?.goal || 0} · ${overview.matchCount || 0} totaal`);
  setMeter('kpi-month-bar', progress.month?.pct);
  setText('kpi-week', String(kpis.matchesWeek || 0));
  setText('kpi-today', `${kpis.matchesToday || 0} vandaag · doel ${progress.week?.goal || 0}`);
  setMeter('kpi-week-bar', progress.week?.pct);
  setText('kpi-active', String(kpis.activePlayers || 0));
  setText('kpi-roster', `${kpis.rosterCount || 0} op de clublijst`);
  setMeter('kpi-active-bar', progress.active?.pct);
  setText('kpi-break', high.value || '—');
  setText('kpi-break-sub', high.player
    ? `${high.player}${high.date ? ` · ${high.date}` : ''} · van 147`
    : 'Nog geen breaks');
  setMeter('kpi-break-bar', progress.break?.pct);
  setText('kpi-frames', String(kpis.framesMonth ?? kpis.framesPlayed ?? 0));
  setText('kpi-frames-sub', `${kpis.framesPlayed || 0} alle tijd · doel ${progress.frames?.goal || 0}`);
  setMeter('kpi-frames-bar', progress.frames?.pct);
  setText('kpi-hc', kpis.avgHandicap ? Number(kpis.avgHandicap).toFixed(1) : '—');
  setText('kpi-centuries', String(overview.centuries || 0));
  setText('kpi-close', `${progress.centuries?.value || 0} van ${progress.centuries?.goal || 0} · ${kpis.closeMatches || 0} nipte`);
  setMeter('kpi-centuries-bar', progress.centuries?.pct);
  setText('kpi-latest', latest ? scoreline(latest) : '—');
  setText('kpi-latest-sub', latest
    ? `${latest.player1} — ${latest.player2}`
    : (kpis.nextEventLabel || 'Nog geen wedstrijd'));
  setText('kpi-members', String(kpis.newMembersMonth || 0));
  setText('kpi-members-sub', `+${kpis.newMembersMonth || 0} van ${progress.members?.goal || 0} deze maand`);
  setMeter('kpi-members-bar', progress.members?.pct);
  setText('kpi-nights', String(kpis.clubNightsMonth || 0));
  setText('kpi-nights-sub', `${kpis.clubNightsUpcoming || 0} gepland · doel ${progress.nights?.goal || 0}`);
  setMeter('kpi-nights-bar', progress.nights?.pct);
  renderRecent('home-recent', (overview.recent || []).slice(0, 6));
  renderPlayers(overview.players || []);
  const excelRank = document.getElementById('excel-ranking');
  if (excelRank) {
    excelRank.innerHTML = (overview.players || []).length
      ? rankingTable(overview.players)
      : '<p class="muted">Nog geen wedstrijden om een ranglijst uit te halen.</p>';
  }
}

function renderAppInfo(app) {
  if (!app) return;
  const version = `v${app.version || '—'}`;
  const revision = app.revision && app.revision !== 'local' ? app.revision : 'lokale build';
  setText('app-version', version);
  setText('app-revision', revision);
  setText('sys-version', version);
  setText('sys-revision', revision);
  setText('sys-node', app.node || '—');
  setText('sys-env', app.env || '—');
  setText('sys-uptime', `${app.uptimeSec || 0}s`);
}

function trophySvg(kind) {
  if (!kind) return '';
  const fill = { gold: '#e4c25a', silver: '#c5cdd4', bronze: '#c47a3a' }[kind];
  return `<svg width="16" height="16" viewBox="0 0 24 24" style="vertical-align:-3px;margin-right:4px"><path fill="${fill}" d="M7 3h10v2h3a1 1 0 0 1 1 1v2a5 5 0 0 1-4.1 4.9A6 6 0 0 1 13 16.9V18h3v2H8v-2h3v-1.1A6 6 0 0 1 7.1 12.9 5 5 0 0 1 3 8V6a1 1 0 0 1 1-1h3z"/></svg>`;
}

function renderPlayers(players) {
  const root = document.getElementById('player-table');
  if (!root) return;
  if (!players.length) {
    root.innerHTML = '<p class="muted">Nog geen spelers op de ranglijst.</p>';
    return;
  }
  root.innerHTML = rankingTable(players);
}

function rankingTable(players) {
  return `
    <table>
      <thead><tr><th>#</th><th>Speler</th><th>W</th><th>L</th><th>F+</th><th>F-</th><th>M%</th><th>F%</th><th>HB</th><th>Gem. punten/frame</th></tr></thead>
      <tbody>
        ${players.map((player) => `
          <tr>
            <td>${player.rank}</td>
            <td>${trophySvg(player.trophy)}${esc(player.name)}</td>
            <td>${player.wins}</td>
            <td>${player.losses}</td>
            <td>${player.framesFor}</td>
            <td>${player.framesAgainst}</td>
            <td>${formatPct(player.matchPct ?? player.winRate)}</td>
            <td>${formatPct(player.framePct)}</td>
            <td>${player.highestBreak || 0}</td>
            <td>${formatGem(player)}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

let allMatches = [];
let agendaCursor = { year: 0, month: 0 };

function eventWhen(event) {
  const time = [event.start, event.end].filter(Boolean).join('–');
  return time ? `${event.date} · ${time}` : event.date;
}

function fillEventForm(event = {}) {
  const form = document.getElementById('event-form');
  if (!form) return;
  form.id.value = event.id || '';
  form.title.value = event.title || '';
  form.date.value = event.date || '';
  form.kind.value = event.kind || 'clubavond';
  if (form.tournament) form.tournament.value = event.tournament || '';
  form.start.value = event.start || '';
  form.end.value = event.end || '';
  form.place.value = event.place || '';
  form.note.value = event.note || '';
  setText('event-form-title', event.id ? 'Item aanpassen' : 'Nieuw item');
  const del = document.getElementById('event-delete');
  if (del) del.hidden = !event.id;
}

function renderAgenda(agenda) {
  if (!agenda) return;
  agendaCursor = { year: agenda.year, month: agenda.month };
  setText('agenda-label', agenda.label || 'Agenda');
  const weekdays = document.getElementById('agenda-weekdays');
  if (weekdays) {
    weekdays.innerHTML = (agenda.weekdays || []).map((day) => `<span>${esc(day)}</span>`).join('');
  }
  const grid = document.getElementById('agenda-grid');
  const selected = document.getElementById('event-form')?.date.value;
  if (grid) {
    grid.innerHTML = (agenda.days || []).map((day) => {
      if (day.empty) return '<button type="button" class="cal-day empty" disabled></button>';
      const first = (day.events || [])[0];
      const extra = `${day.today ? ' today' : ''}${selected === day.date ? ' on' : ''}`;
      return `<button type="button" class="cal-day${extra}" data-agenda-day="${esc(day.date)}">
        <strong>${day.day}</strong>
        ${first ? `<small>${esc(first.title)}</small>` : ''}
        ${day.events.length > 1 ? `<small>+${day.events.length - 1}</small>` : ''}
      </button>`;
    }).join('');
    grid.querySelectorAll('[data-agenda-day]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const hits = (agenda.events || []).filter((event) => event.date === btn.dataset.agendaDay);
        fillEventForm(hits.length === 1 ? hits[0] : { date: btn.dataset.agendaDay });
        renderAgenda(agenda);
      });
    });
  }
  const list = document.getElementById('agenda-list');
  if (!list) return;
  list.innerHTML = (agenda.events || []).map((event) => `
    <div class="row">
      <div>
        <strong><span class="kind ${esc(event.kind)}">${esc(event.kindLabel || event.kind)}</span>${esc(event.title)}</strong>
        <small>${esc(eventWhen(event))}${event.tournament ? ` · ${esc(event.tournament)}` : ''}${event.place ? ` · ${esc(event.place)}` : ''}</small>
      </div>
      <button type="button" class="btn ghost" data-edit-event="${esc(event.id)}">Open</button>
    </div>
  `).join('') || '<p class="muted">Nog geen items deze maand. Voeg rechts een clubavond of tornooi toe.</p>';
  list.querySelectorAll('[data-edit-event]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const hit = (agenda.events || []).find((event) => event.id === btn.dataset.editEvent);
      if (hit) fillEventForm(hit);
      renderAgenda(agenda);
    });
  });
}

async function loadAgenda(year, month) {
  const query = year && month ? `?year=${year}&month=${month}` : '';
  const data = await json(apiUrl('/api/public/agenda') + query);
  renderAgenda(data.agenda);
}
let allRoster = [];

function fillSelect(select, selected, emptyLabel) {
  if (!select) return;
  const current = selected || select.value;
  const html = [`<option value="">${esc(emptyLabel || 'Kies speler')}</option>`]
    .concat(allRoster.map((player) => `<option value="${esc(player.id)}">${esc(player.name)}</option>`))
    .join('');
  select.innerHTML = html;
  const hit = allRoster.find((player) => player.id === current || player.name === current);
  if (hit) select.value = hit.id;
}

function fillPlayerSelects(selected = {}) {
  fillSelect(document.querySelector('#edit-form [name="player1"]'), selected.player1);
  fillSelect(document.querySelector('#edit-form [name="player2"]'), selected.player2);
  fillSelect(document.querySelector('#paper-form [name="player1"]'), selected.paper1);
  fillSelect(document.querySelector('#paper-form [name="player2"]'), selected.paper2, 'Kies tegenstander');
  fillSelect(document.querySelector('#paper-import-form [name="player"]'), selected.importPlayer, 'Staat in de CSV-kolom Speler');
  fillSelect(document.getElementById('dossier-player'), selected.dossier);
  fillSelect(document.getElementById('h2h-a'), selected.h2hA);
  fillSelect(document.getElementById('h2h-b'), selected.h2hB, 'Kies tegenstander');
  syncPlayerChoices();
}

function syncPlayerChoices() {
  const form = document.getElementById('edit-form');
  if (!form) return;
  if (form.player1.value && form.player1.value === form.player2.value) {
    form.player2.value = '';
  }
  for (const option of form.player2.options) {
    if (option.value) option.disabled = option.value === form.player1.value;
  }
  for (const option of form.player1.options) {
    if (option.value) option.disabled = option.value === form.player2.value;
  }
}

function signFigure(src, name) {
  if (!src) return `<figure><figcaption>${esc(name)} — nog geen handtekening</figcaption></figure>`;
  return `<figure><img src="${esc(src)}" alt="Handtekening ${esc(name)}" /><figcaption>${esc(name)}</figcaption></figure>`;
}

function renderRoster(roster) {
  const root = document.getElementById('roster-table');
  if (!root) return;
  if (!roster.length) {
    root.innerHTML = '<p class="muted">Nog geen spelers. Voeg hierboven de eerste naam toe.</p>';
    return;
  }
  root.innerHTML = `
    <table>
      <thead><tr><th>Naam</th><th></th></tr></thead>
      <tbody>
        ${roster.map((player) => `
          <tr>
            <td><input data-rename="${esc(player.id)}" value="${esc(player.name)}" maxlength="80" /></td>
            <td class="actions-cell">
              <button type="button" class="btn ghost" data-save-player="${esc(player.id)}">Opslaan</button>
              <button type="button" class="btn danger" data-del-player="${esc(player.id)}">Wis</button>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
  root.querySelectorAll('[data-save-player]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        const input = root.querySelector(`[data-rename="${btn.dataset.savePlayer}"]`);
        await json(apiUrl('/api/admin/players/' + btn.dataset.savePlayer), {
          method: 'PUT',
          headers: { 'content-type': 'application/json' },
          body: JSON.stringify({ name: input.value }),
        });
        toast('Speler opgeslagen.');
        await refresh();
      } catch (err) {
        toast(err.message);
      }
    });
  });
  root.querySelectorAll('[data-del-player]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('Speler van de clublijst verwijderen?')) return;
      try {
        await json(apiUrl('/api/admin/players/' + btn.dataset.delPlayer), { method: 'DELETE' });
        toast('Speler verwijderd.');
        await refresh();
      } catch (err) {
        toast(err.message);
      }
    });
  });
}

function renderMatches(matches) {
  const root = document.getElementById('match-table');
  if (!matches.length) {
    root.innerHTML = '<p class="muted">Geen wedstrijden gevonden.</p>';
    return;
  }
  root.innerHTML = `
    <table>
      <thead><tr><th>Datum</th><th>Tornooi</th><th>Spelers</th><th>Stand</th><th>Frames</th><th>HC</th><th>Getekend</th><th></th></tr></thead>
      <tbody>
        ${matches.map((match) => `
          <tr>
            <td>${esc(match.date)}</td>
            <td>${esc(match.tournament)}</td>
            <td>${esc(match.winner ? `${match.winner} wint` : '')} · ${esc(match.player1)} — ${esc(match.player2)}</td>
            <td class="score">${scoreline(match)}</td>
            <td>${match.frameMode === 'fixed' ? `${match.bestOf || match.frames?.length || ''} vast` : `best of ${match.bestOf || 5}`}</td>
            <td>${hcLine(match)}</td>
            <td>${match.signed ? 'Beide' : 'Nee'}</td>
            <td class="actions-cell">
              <button type="button" class="btn ghost" data-edit="${esc(match.id)}">Bewerk</button>
              <button type="button" class="btn danger" data-del="${esc(match.id)}">Wis</button>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
  root.querySelectorAll('[data-edit]').forEach((btn) => {
    btn.addEventListener('click', () => editMatch(allMatches.find((row) => row.id === btn.dataset.edit)));
  });
  root.querySelectorAll('[data-del]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('Wedstrijd verwijderen?')) return;
      try {
        await json(apiUrl('/api/admin/matches/' + btn.dataset.del), { method: 'DELETE' });
        toast('Wedstrijd verwijderd.');
        await refresh();
      } catch (err) {
        toast(err.message);
      }
    });
  });
}

function filterMatches() {
  const q = String(document.getElementById('match-filter').value || '').toLowerCase();
  const filtered = allMatches.filter((match) => (
    `${match.player1} ${match.player2} ${match.tournament} ${match.date} ${match.table || ''} ${match.matchType || ''}`.toLowerCase().includes(q)
  ));
  renderMatches(filtered);
}

function editMatch(match) {
  if (!match) return;
  showTab('matches');
  const form = document.getElementById('edit-form');
  form.hidden = false;
  form.id.value = match.id;
  form.tournament.value = match.tournament;
  form.date.value = match.date;
  fillPlayerSelects({ player1: match.player1, player2: match.player2 });
  form.break1.value = match.break1;
  form.break2.value = match.break2;
  if (form.frameFormat) {
    const mode = match.frameMode === 'fixed' ? 'fixed' : 'bestof';
    const count = match.bestOf || match.frames?.length || 5;
    const value = `${mode}:${count}`;
    if ([...form.frameFormat.options].some((opt) => opt.value === value)) {
      form.frameFormat.value = value;
    }
  }
  if (form.season) form.season.value = match.season || '';
  if (form.round) form.round.value = match.round || '';
  form.note.value = match.note || '';
  fillFrames(document.getElementById('edit-frames'), match.frames);
  document.getElementById('edit-sign-thumbs').innerHTML =
    signFigure(match.signature1, match.player1) + signFigure(match.signature2, match.player2);
  updateEditHc();
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function updateEditHc() {
  const form = document.getElementById('edit-form');
  const label = document.getElementById('edit-hc');
  if (!form || !label) return;
  const averages = computeFormAverages(form);
  label.textContent = averages.framesPlayed
    ? `Handicap deze wedstrijd: ${formatHc(averages.handicap1, averages.framesPlayed)} / ${formatHc(averages.handicap2, averages.framesPlayed)} (gemiddelde van ${averages.framesPlayed} gespeelde frames)`
    : 'Handicap is het gemiddelde van de gespeelde frames. Lege 0–0 frames tellen niet mee.';
}

function fillThemeFromPreset(preset) {
  const pack = window.SnookerTheme?.PRESETS?.[preset];
  const form = document.getElementById('brand-form');
  if (!form || !pack) return;
  form.accent.value = pack.accent;
  form.accentSoft.value = pack.accentSoft;
  if (form.bg) form.bg.value = pack.bg;
  if (form.bgSide) form.bgSide.value = pack.bgSide;
  if (form.ink) form.ink.value = pack.ink;
}

function applyLogo(url) {
  document.querySelectorAll('.brand-mark').forEach((mark) => {
    if (url) {
      mark.classList.add('has-logo');
      mark.innerHTML = `<img alt="" src="${esc(url)}" />`;
    } else {
      mark.classList.remove('has-logo');
      mark.innerHTML = '';
    }
  });
}

function syncPreview() {
  const form = document.getElementById('brand-form');
  const preview = document.getElementById('brand-preview');
  const clubName = form.clubName.value || 'SC De Merodesnookers';
  document.getElementById('preview-kicker').textContent = clubName;
  document.getElementById('preview-name').textContent = clubName;
  document.getElementById('preview-tag').textContent = form.tagline.value || '';
  setText('side-club', clubName);
  document.title = `Clubbeheer · ${clubName}`;
  applyLogo(form.logoUrl.value.trim());
  const brand = {
    themePreset: form.themePreset?.value || 'custom',
    accent: form.accent.value,
    accentSoft: form.accentSoft.value,
    bg: form.bg?.value,
    bgSide: form.bgSide?.value,
    ink: form.ink?.value,
    font: form.font?.value,
    radius: form.radius?.value,
    density: form.density?.value,
  };
  if (window.SnookerTheme) {
    window.SnookerTheme.apply(brand);
    if (preview) window.SnookerTheme.apply(brand, preview);
  } else {
    preview.style.setProperty('--accent', form.accent.value);
    document.documentElement.style.setProperty('--accent', form.accent.value);
    document.documentElement.style.setProperty('--accent-2', form.accentSoft.value);
  }
}

async function loadBrand() {
  const brand = await json(apiUrl('/api/admin/brand'));
  const form = document.getElementById('brand-form');
  form.clubName.value = brand.clubName;
  form.tagline.value = brand.tagline;
  if (form.frameFormat) {
    const mode = brand.frameMode === 'fixed' ? 'fixed' : 'bestof';
    const count = brand.framesCount || 5;
    const value = `${mode}:${count}`;
    if ([...form.frameFormat.options].some((opt) => opt.value === value)) {
      form.frameFormat.value = value;
    }
  }
  form.accent.value = brand.accent;
  form.accentSoft.value = brand.accentSoft;
  if (form.themePreset) {
    const preset = brand.themePreset || 'custom';
    const radio = form.querySelector(`input[name="themePreset"][value="${preset}"]`)
      || form.querySelector('input[name="themePreset"][value="custom"]');
    if (radio) radio.checked = true;
  }
  if (form.bg) form.bg.value = brand.bg || '#f3f7f0';
  if (form.bgSide) form.bgSide.value = brand.bgSide || '#163524';
  if (form.ink) form.ink.value = brand.ink || '#1d2a22';
  if (form.font) form.font.value = brand.font || 'club';
  if (form.radius) form.radius.value = brand.radius || 'club';
  if (form.density) form.density.value = brand.density || 'comfortable';
  form.logoUrl.value = brand.logoUrl || '';
  form.heroUrl.value = brand.heroUrl || '';
  form.notice.value = brand.notice || '';
  form.venue.value = brand.venue || '';
  form.openingHours.value = brand.openingHours || '';
  form.nextEvent.value = brand.nextEvent || '';
  form.goalMatchesMonth.value = brand.goalMatchesMonth || 12;
  form.goalMatchesWeek.value = brand.goalMatchesWeek || 4;
  form.goalFramesMonth.value = brand.goalFramesMonth || 40;
  form.goalCenturies.value = brand.goalCenturies || 3;
  form.goalNewMembersMonth.value = brand.goalNewMembersMonth || 5;
  form.goalClubNightsMonth.value = brand.goalClubNightsMonth || 4;
  form.tournaments.value = (brand.tournaments || []).join('\n');
  form.showSignatures.checked = brand.showSignatures !== false;
  const eventTours = document.getElementById('event-tournaments');
  if (eventTours) {
    eventTours.innerHTML = (brand.tournaments || []).map((name) => `<option value="${esc(name)}"></option>`).join('');
  }
  syncPreview();
}

function setEmbedPreview(linkId, frameId, url) {
  const link = document.getElementById(linkId);
  const frame = document.getElementById(frameId);
  if (link && url) link.href = url;
  if (!frame || !url) return;
  if (frame.tagName === 'IFRAME') frame.src = url;
}

async function loadEmbed() {
  const embed = await json(apiUrl('/api/admin/embed'));
  document.getElementById('embed-iframe').value = embed.iframe;
  document.getElementById('embed-script').value = embed.script;
  document.getElementById('embed-players-iframe').value = embed.playersIframe;
  document.getElementById('embed-players-script').value = embed.playersScript;
  const agendaIframe = document.getElementById('embed-agenda-iframe');
  const agendaScript = document.getElementById('embed-agenda-script');
  if (agendaIframe) agendaIframe.value = embed.agendaIframe || '';
  if (agendaScript) agendaScript.value = embed.agendaScript || '';
  setEmbedPreview('live-preview', 'live-frame', embed.liveUrl);
  setEmbedPreview('players-preview', 'players-frame', embed.playersUrl);
  setEmbedPreview('agenda-preview', 'agenda-frame', embed.agendaUrl);
}

let lastDossier = null;
let lastH2h = null;
let lastPlayers = [];

function renderDossier(dossier) {
  lastDossier = dossier;
  const career = document.getElementById('dossier-career');
  const table = document.getElementById('dossier-table');
  if (!career || !table) return;
  if (!dossier) {
    career.hidden = true;
    table.innerHTML = '<p class="muted">Kies een speler om het Excel-dossier te zien.</p>';
    return;
  }
  const c = dossier.career || {};
  career.hidden = false;
  career.innerHTML = careerCards([
    { tone: 'win', label: 'Winst / frames', value: c.winsFrames || 0 },
    { tone: 'win', label: 'Winst / partijen', value: c.winsMatches || 0 },
    { tone: 'break', label: 'Hoogste break', value: c.highestBreak || 0 },
    { tone: 'break', label: 'Gem. break', value: String(c.gemBreak || 0).replace('.', ',') },
    { tone: 'loss', label: 'Verlies / frames', value: c.lossesFrames || 0 },
    { tone: 'loss', label: 'Verlies / partijen', value: c.lossesMatches || 0 },
    { tone: 'pct', label: 'Framepercentage', value: formatPct(c.framePct) },
    { tone: 'pct', label: 'Partijpercentage', value: formatPct(c.matchPct) },
    { tone: 'total', label: 'Totaal frames', value: c.totalFrames || 0 },
    { tone: 'total', label: 'Totaal partijen', value: c.totalMatches || 0 },
  ]);
  table.innerHTML = (dossier.rows || []).length
    ? `<table>
      <thead><tr><th>VERSUS</th><th>TOURNAMENT</th><th>RESULT</th><th>W</th><th>L</th><th>BREAKS</th><th>ROUND</th><th>SEASON</th></tr></thead>
      <tbody>${dossier.rows.map((row) => `
        <tr>
          <td>${esc(row.versus)}</td>
          <td>${esc(row.tournament)}</td>
          <td>${esc(row.result)}</td>
          <td>${row.w}</td>
          <td>${row.l}</td>
          <td>${row.breaks || ''}</td>
          <td>${esc(row.roundLabel)}</td>
          <td>${esc(row.season)}</td>
        </tr>`).join('')}</tbody>
    </table>`
    : '<p class="muted">Nog geen partijen voor deze speler.</p>';
}

function renderH2h(data) {
  lastH2h = data;
  const summary = document.getElementById('h2h-summary');
  const table = document.getElementById('h2h-table');
  if (!summary || !table) return;
  if (!data) {
    summary.hidden = true;
    table.innerHTML = '<p class="muted">Kies twee spelers voor de onderlinge stand.</p>';
    return;
  }
  summary.hidden = false;
  summary.innerHTML = careerCards([
    { tone: 'win', label: `${data.a} wint`, value: data.wins || 0 },
    { tone: 'loss', label: `${data.b} wint`, value: data.losses || 0 },
    { tone: 'pct', label: 'Frames', value: `${data.framesFor || 0}–${data.framesAgainst || 0}` },
    { tone: 'total', label: 'Laatste', value: data.lastResult || '—' },
  ]);
  table.innerHTML = (data.rows || []).length
    ? `<table>
      <thead><tr><th>Datum</th><th>Toernooi</th><th>RESULT</th><th>W</th><th>L</th><th>ROUND</th><th>SEASON</th></tr></thead>
      <tbody>${data.rows.map((row) => `
        <tr>
          <td>${esc(row.date)}</td>
          <td>${esc(row.tournament)}</td>
          <td>${esc(row.result)}</td>
          <td>${row.w}</td>
          <td>${row.l}</td>
          <td>${esc(row.roundLabel)}</td>
          <td>${esc(row.season)}</td>
        </tr>`).join('')}</tbody>
    </table>`
    : '<p class="muted">Deze twee hebben nog niet tegen elkaar gespeeld.</p>';
}

async function loadDossier(name) {
  if (!name) {
    renderDossier(null);
    return;
  }
  renderDossier(await json(apiUrl('/api/admin/dossier?player=' + encodeURIComponent(name))));
}

async function loadH2h(a, b) {
  if (!a || !b) {
    renderH2h(null);
    return;
  }
  renderH2h(await json(apiUrl(`/api/admin/h2h?a=${encodeURIComponent(a)}&b=${encodeURIComponent(b)}`)));
}

function selectedPlayerName(select) {
  if (!select?.value) return '';
  return allRoster.find((player) => player.id === select.value || player.name === select.value)?.name || select.value;
}

function rankingCsvText(players) {
  return toCsv(
    ['#', 'Speler', 'W', 'L', 'F+', 'F-', 'M%', 'F%', 'HB', 'Gem. punten/frame'],
    (players || []).map((player) => ({
      '#': player.rank,
      Speler: player.name,
      W: player.wins,
      L: player.losses,
      'F+': player.framesFor,
      'F-': player.framesAgainst,
      'M%': formatPct(player.matchPct ?? player.winRate),
      'F%': formatPct(player.framePct),
      HB: player.highestBreak || 0,
      'Gem. punten/frame': (player.avgPoints ?? player.avgFrames) == null
        ? ''
        : String(player.avgPoints ?? player.avgFrames).replace('.', ','),
    })),
  );
}

async function refresh() {
  const [overview, listed, rosterRes] = await Promise.all([
    json(apiUrl('/api/public/overview')),
    json(apiUrl('/api/admin/matches')),
    json(apiUrl('/api/admin/players')),
  ]);
  allMatches = listed.matches || [];
  allRoster = rosterRes.players || overview.roster || [];
  lastPlayers = overview.players || [];
  renderStats(overview);
  renderRoster(allRoster);
  fillPlayerSelects();
  filterMatches();
  await loadAgenda(agendaCursor.year || undefined, agendaCursor.month || undefined);
}

async function boot() {
  document.querySelectorAll('[data-tab]').forEach((btn) => {
    btn.addEventListener('click', () => showTab(btn.dataset.tab));
  });
  document.getElementById('edit-cancel').addEventListener('click', () => {
    document.getElementById('edit-form').hidden = true;
  });
  document.getElementById('match-filter').addEventListener('input', filterMatches);
  document.getElementById('edit-frames').addEventListener('input', updateEditHc);
  document.getElementById('player-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    try {
      await json(apiUrl('/api/admin/players'), {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ name: form.name.value }),
      });
      form.reset();
      toast('Speler toegevoegd.');
      await refresh();
    } catch (err) {
      toast(err.message);
    }
  });
  document.getElementById('paper-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    try {
      await json(apiUrl('/api/admin/matches'), {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          source: 'paper',
          player1: form.player1.value,
          player2: form.player2.value,
          tournament: form.tournament.value,
          date: form.date.value,
          framesFor: form.framesFor.value,
          framesAgainst: form.framesAgainst.value,
          break1: form.break1.value,
          break2: form.break2.value,
          round: form.round.value,
          season: form.season.value,
        }),
      });
      form.framesFor.value = '';
      form.framesAgainst.value = '';
      form.break1.value = '0';
      form.break2.value = '0';
      toast('Papieruitslag opgeslagen.');
      await refresh();
    } catch (err) {
      toast(err.message);
    }
  });
  document.getElementById('paper-import-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    try {
      const imported = await json(apiUrl('/api/admin/paper'), {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          player: selectedPlayerName(form.player),
          csv: form.csv.value,
        }),
      });
      form.csv.value = '';
      toast(`${imported.count || 0} papierregels geïmporteerd.`);
      await refresh();
    } catch (err) {
      toast(err.message);
    }
  });
  document.getElementById('dossier-player')?.addEventListener('change', async (event) => {
    try {
      await loadDossier(selectedPlayerName(event.target));
    } catch (err) {
      toast(err.message);
    }
  });
  const syncH2h = async () => {
    try {
      await loadH2h(selectedPlayerName(document.getElementById('h2h-a')), selectedPlayerName(document.getElementById('h2h-b')));
    } catch (err) {
      toast(err.message);
    }
  };
  document.getElementById('h2h-a')?.addEventListener('change', syncH2h);
  document.getElementById('h2h-b')?.addEventListener('change', syncH2h);
  document.getElementById('excel-ranking-csv')?.addEventListener('click', () => {
    downloadCsv('ranglijst.csv', rankingCsvText(lastPlayers));
  });
  document.getElementById('excel-dossier-csv')?.addEventListener('click', () => {
    if (!lastDossier) {
      toast('Kies eerst een speler.');
      return;
    }
    downloadCsv(`dossier-${lastDossier.player}.csv`, toCsv(
      ['VERSUS', 'TOURNAMENT', 'RESULT', 'W', 'L', 'BREAKS', 'ROUND', 'SEASON'],
      (lastDossier.rows || []).map((row) => ({
        VERSUS: row.versus,
        TOURNAMENT: row.tournament,
        RESULT: String(row.result || '').replace('-', '/'),
        W: row.w,
        L: row.l,
        BREAKS: row.breaks || '',
        ROUND: row.roundLabel,
        SEASON: row.season,
      })),
    ));
  });
  document.getElementById('excel-h2h-csv')?.addEventListener('click', () => {
    const rows = lastDossier?.headToHead || (lastH2h ? [lastH2h] : []);
    if (!rows.length) {
      toast('Kies eerst een speler of koppel.');
      return;
    }
    downloadCsv('head-to-head.csv', toCsv(
      ['VERSUS', 'W', 'L', 'F+', 'F-', 'Laatste'],
      rows.map((row) => ({
        VERSUS: row.versus || row.b,
        W: row.wins,
        L: row.losses,
        'F+': row.framesFor,
        'F-': row.framesAgainst,
        Laatste: row.lastResult || '',
      })),
    ));
  });
  document.getElementById('edit-form').player1.addEventListener('change', syncPlayerChoices);
  document.getElementById('edit-form').player2.addEventListener('change', syncPlayerChoices);
  document.getElementById('edit-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    if (form.player1.value && form.player1.value === form.player2.value) {
      toast('Speler 2 kan niet dezelfde zijn als speler 1.');
      return;
    }
    try {
      await json(apiUrl('/api/admin/matches/' + form.id.value), {
        method: 'PUT',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          tournament: form.tournament.value,
          date: form.date.value,
          player1: form.player1.value,
          player2: form.player2.value,
          break1: form.break1.value,
          break2: form.break2.value,
          frameFormat: form.frameFormat?.value || 'bestof:5',
          season: form.season?.value || '',
          round: form.round?.value || '',
          note: form.note.value,
          frames: collectFrames(form),
        }),
      });
      form.hidden = true;
      toast('Wedstrijd opgeslagen.');
      await refresh();
    } catch (err) {
      toast(err.message);
    }
  });
  const brandForm = document.getElementById('brand-form');
  brandForm.addEventListener('input', (event) => {
    if (event.target.name === 'themePreset' && event.target.value !== 'custom') {
      fillThemeFromPreset(event.target.value);
    } else if (['accent', 'accentSoft', 'bg', 'bgSide', 'ink'].includes(event.target.name)) {
      const custom = brandForm.querySelector('input[name="themePreset"][value="custom"]');
      if (custom) custom.checked = true;
    }
    syncPreview();
  });
  brandForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await json(apiUrl('/api/admin/brand'), {
        method: 'PUT',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          clubName: brandForm.clubName.value,
          tagline: brandForm.tagline.value,
          frameFormat: brandForm.frameFormat?.value || 'bestof:5',
          themePreset: brandForm.themePreset?.value || 'custom',
          accent: brandForm.accent.value,
          accentSoft: brandForm.accentSoft.value,
          bg: brandForm.bg?.value,
          bgSide: brandForm.bgSide?.value,
          ink: brandForm.ink?.value,
          font: brandForm.font?.value,
          radius: brandForm.radius?.value,
          density: brandForm.density?.value,
          logoUrl: brandForm.logoUrl.value,
          heroUrl: brandForm.heroUrl.value,
          notice: brandForm.notice.value,
          venue: brandForm.venue.value,
          openingHours: brandForm.openingHours.value,
          nextEvent: brandForm.nextEvent.value,
          goalMatchesMonth: brandForm.goalMatchesMonth.value,
          goalMatchesWeek: brandForm.goalMatchesWeek.value,
          goalFramesMonth: brandForm.goalFramesMonth.value,
          goalCenturies: brandForm.goalCenturies.value,
          goalNewMembersMonth: brandForm.goalNewMembersMonth.value,
          goalClubNightsMonth: brandForm.goalClubNightsMonth.value,
          tournaments: brandForm.tournaments.value,
          showSignatures: brandForm.showSignatures.checked,
        }),
      });
      toast('Clubopmaak opgeslagen.');
      await refresh();
    } catch (err) {
      toast(err.message);
    }
  });
  document.getElementById('hero-file').addEventListener('change', async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const image = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(new Error('Bestand lezen mislukt.'));
      reader.readAsDataURL(file);
    });
    const brand = await json(apiUrl('/api/admin/hero'), {
      method: 'PUT',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ image }),
    });
    brandForm.heroUrl.value = brand.heroUrl;
    toast('Achtergrond bijgewerkt.');
  });
  const eventForm = document.getElementById('event-form');
  eventForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = {
      title: eventForm.title.value,
      date: eventForm.date.value,
      kind: eventForm.kind.value,
      tournament: eventForm.tournament?.value || '',
      start: eventForm.start.value,
      end: eventForm.end.value,
      place: eventForm.place.value,
      note: eventForm.note.value,
    };
    try {
      const saved = eventForm.id.value
        ? await json(apiUrl('/api/admin/events/' + eventForm.id.value), {
          method: 'PUT',
          headers: { 'content-type': 'application/json' },
          body: JSON.stringify(payload),
        })
        : await json(apiUrl('/api/admin/events'), {
          method: 'POST',
          headers: { 'content-type': 'application/json' },
          body: JSON.stringify(payload),
        });
      toast(eventForm.id.value ? 'Agenda-item bijgewerkt.' : 'Agenda-item gezet.');
      fillEventForm(saved.event || payload);
      await loadAgenda(agendaCursor.year, agendaCursor.month);
    } catch (err) {
      toast(err.message);
    }
  });
  document.getElementById('event-reset')?.addEventListener('click', () => fillEventForm({}));
  document.getElementById('event-delete')?.addEventListener('click', async () => {
    if (!eventForm.id.value || !confirm('Dit agenda-item verwijderen?')) return;
    try {
      await json(apiUrl('/api/admin/events/' + eventForm.id.value), { method: 'DELETE' });
      fillEventForm({});
      toast('Agenda-item verwijderd.');
      await loadAgenda(agendaCursor.year, agendaCursor.month);
    } catch (err) {
      toast(err.message);
    }
  });
  document.getElementById('agenda-prev')?.addEventListener('click', () => {
    const date = new Date(agendaCursor.year, agendaCursor.month - 2, 1);
    loadAgenda(date.getFullYear(), date.getMonth() + 1).catch((err) => toast(err.message));
  });
  document.getElementById('agenda-next')?.addEventListener('click', () => {
    const date = new Date(agendaCursor.year, agendaCursor.month, 1);
    loadAgenda(date.getFullYear(), date.getMonth() + 1).catch((err) => toast(err.message));
  });
  document.getElementById('agenda-today')?.addEventListener('click', () => {
    loadAgenda().catch((err) => toast(err.message));
  });
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const field = document.getElementById(btn.dataset.copy);
      await navigator.clipboard.writeText(field.value);
      toast('Gekopieerd.');
    });
  });

  try {
    const me = await json(apiUrl('/api/admin/me'));
    setText('who', me.user.username || 'beheerder');
    renderAppInfo(me.app);
    await Promise.all([refresh(), loadBrand(), loadEmbed()]);
  } catch (err) {
    setText('who', 'Niet aangemeld');
    const authErr = document.getElementById('auth-err');
    if (authErr) {
      authErr.hidden = false;
      authErr.textContent = `${err.message} Open Snookerclub → Clubbeheer in WordPress.`;
    }
    try {
      renderAppInfo((await json(apiUrl('/api/public/meta'))).app);
    } catch {
      /* versie is optioneel als health ook weg is */
    }
  }
}

boot();
