function appBase() {
  if (typeof window !== 'undefined' && window.SNOOKER_BASE) {
    return String(window.SNOOKER_BASE).replace(/\/+$/, '') || '/';
  }
  const raw = location.pathname.replace(/\/+$/, '') || '/';
  return raw.replace(/\/(admin|live\/players|live|ingeven)$/, '') || '/webhost/snooker';
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
const GUEST_TABS = ['club', 'match', 'kpis', 'agenda'];
const TAB_HASH = { match: 'wedstrijd', kpis: 'kpis', agenda: 'agenda' };
const HASH_TAB = { wedstrijd: 'match', kpis: 'kpis', agenda: 'agenda', club: 'club' };
let wizardStep = 1;
let lastRoster = [];
let lastBrand = null;
let lastAgenda = null;
let frameSlotCount = 5;
let selectedAgendaDay = '';
let readSignature1 = () => '';
let readSignature2 = () => '';
let clearSignatures = () => {};

async function json(url, options = {}) {
  const headers = { ...(options.headers || {}) };
  if (typeof window !== 'undefined' && window.SNOOKER_NONCE) {
    headers['X-WP-Nonce'] = window.SNOOKER_NONCE;
  }
  const res = await fetch(url, { ...options, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || data.message || 'Verzoek mislukt.');
  return data;
}

function today() {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Amsterdam',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date());
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

function playerLabel(select) {
  const opt = select?.selectedOptions?.[0];
  return opt && opt.value ? opt.textContent : 'Speler';
}

function syncPlayerChoices() {
  const form = document.getElementById('match-form');
  if (!form) return;
  const one = form.player1.value;
  const two = form.player2.value;
  if (one && one === two) form.player2.value = '';
  for (const option of form.player2.options) {
    if (option.value) option.disabled = option.value === form.player1.value;
  }
  for (const option of form.player1.options) {
    if (option.value) option.disabled = option.value === form.player2.value;
  }
}

function fillPlayerSelects(roster, selected = {}) {
  const list = roster || [];
  lastRoster = list;
  const html = ['<option value="">Kies speler</option>']
    .concat(list.map((player) => `<option value="${esc(player.id)}">${esc(player.name)}</option>`))
    .join('');
  for (const name of ['player1', 'player2']) {
    const select = document.querySelector(`[name="${name}"]`);
    if (!select) continue;
    const current = selected[name] || select.value;
    select.innerHTML = html;
    const hit = list.find((player) => player.id === current || player.name === current);
    if (hit) select.value = hit.id;
  }
  syncPlayerChoices();
  const hint = document.getElementById('roster-hint');
  if (hint) {
    hint.textContent = list.length < 2
      ? 'Nog geen (of te weinig) spelers. De clubbaas voegt ze toe in het beheer.'
      : 'Spelers voegt de club toe in het beheer. Staat je naam er niet bij, vraag de clubbaas.';
  }
}

function trophySvg(kind) {
  const fill = { gold: '#e4c25a', silver: '#c5cdd4', bronze: '#c47a3a' }[kind] || '#e4c25a';
  return `<svg class="trophy" viewBox="0 0 24 24" aria-hidden="true"><path fill="${fill}" d="M7 3h10v2h3a1 1 0 0 1 1 1v2a5 5 0 0 1-4.1 4.9A6 6 0 0 1 13 16.9V18h3v2H8v-2h3v-1.1A6 6 0 0 1 7.1 12.9 5 5 0 0 1 3 8V6a1 1 0 0 1 1-1h3zm0 4H5v1a3 3 0 0 0 2.2 2.9A6 6 0 0 1 7 7zm10 0a6 6 0 0 1-.2 3.9A3 3 0 0 0 19 8V7z"/></svg>`;
}

function setHidden(id, hidden) {
  const el = document.getElementById(id);
  if (el) el.hidden = hidden;
}

function frameSlots(form) {
  const raw = form?.frameFormat?.value || '';
  const match = String(raw).match(/^(?:bestof|fixed)[:|-](\d+)$/i);
  if (match) return Math.min(17, Math.max(1, Number(match[1])));
  const best = Number(lastBrand?.framesCount || frameSlotCount) || 5;
  return Math.min(17, Math.max(1, best));
}

function frameFormatLabel(form) {
  const opt = form?.frameFormat?.selectedOptions?.[0];
  return opt?.textContent?.trim() || form?.frameFormat?.value || '';
}

function signaturesRequired() {
  return lastBrand?.showSignatures !== false;
}

function syncSignatureUi() {
  const block = document.getElementById('sign-block');
  const help = document.getElementById('finish-help');
  const required = signaturesRequired();
  if (block) block.hidden = !required;
  if (help) {
    help.textContent = required
      ? 'Controleer de samenvatting. Beide spelers zetten hun eigen handtekening. Hoogste break is de hoogste serie in één beurt (0–147, tot 155 bij free ball).'
      : 'Controleer de samenvatting. Handtekeningen staan uit voor deze club. Hoogste break is de hoogste serie in één beurt (0–147, tot 155 bij free ball).';
  }
}

function setupFrames(form) {
  const root = document.getElementById('frames');
  if (!root) return;
  frameSlotCount = frameSlots(form || document.getElementById('match-form'));
  root.innerHTML = '<div class="frame-head"><span></span><span data-p1-label>Speler 1</span><span data-p2-label>Speler 2</span></div>';
  for (let i = 1; i <= frameSlotCount; i += 1) {
    const row = document.createElement('div');
    row.className = 'frame';
    row.innerHTML = `
      <strong>Frame ${i}</strong>
      <input type="number" min="0" max="200" value="0" name="f${i}p1" inputmode="numeric" aria-label="Frame ${i} speler 1" />
      <input type="number" min="0" max="200" value="0" name="f${i}p2" inputmode="numeric" aria-label="Frame ${i} speler 2" />
    `;
    root.appendChild(row);
  }
}

function collectFrames(form) {
  const frames = [];
  const n = frameSlots(form);
  for (let i = 1; i <= n; i += 1) {
    frames.push({
      p1: Number(form[`f${i}p1`]?.value) || 0,
      p2: Number(form[`f${i}p2`]?.value) || 0,
    });
  }
  return frames;
}

function wizardPreview(form) {
  const frames = collectFrames(form);
  const played = frames.filter((frame) => frame.p1 + frame.p2 > 0);
  let w1 = 0;
  let w2 = 0;
  for (const frame of played) {
    if (frame.p1 > frame.p2) w1 += 1;
    else if (frame.p2 > frame.p1) w2 += 1;
  }
  const avg = (key) => (played.length
    ? Math.round((played.reduce((sum, frame) => sum + frame[key], 0) / played.length) * 10) / 10
    : 0);
  const n1 = playerLabel(form.player1);
  const n2 = playerLabel(form.player2);
  const lead = !played.length ? 'Nog geen gespeelde frames' : w1 === w2 ? 'Gelijkspel tot nu toe' : `${w1 > w2 ? n1 : n2} leidt`;
  return { played: played.length, w1, w2, hc1: avg('p1'), hc2: avg('p2'), n1, n2, lead, frames };
}

function refreshPreview() {
  const form = document.getElementById('match-form');
  const box = document.getElementById('wiz-preview');
  if (!form || !box) return;
  const preview = wizardPreview(form);
  box.innerHTML = `
    <strong>${preview.lead}</strong>
    <span>${preview.n1} ${preview.w1}–${preview.w2} ${preview.n2}</span>
    <span>${preview.played ? `HC ${preview.hc1.toFixed(1)} / ${preview.hc2.toFixed(1)} · ${preview.played} frames` : '0–0 frames tellen niet mee voor de handicap'}</span>
  `;
  document.querySelectorAll('[data-p1-label]').forEach((el) => { el.textContent = preview.n1; });
  document.querySelectorAll('[data-p2-label]').forEach((el) => { el.textContent = preview.n2; });
  const review = document.getElementById('wiz-review');
  if (review) {
    review.innerHTML = `
      <p><strong>${esc(preview.n1)}</strong> tegen <strong>${esc(preview.n2)}</strong></p>
      <p>${esc(form.tournament.value || '—')} · ${esc(form.date.value || '—')} · ${esc(frameFormatLabel(form))}</p>
      <p>Stand ${preview.w1}–${preview.w2} · HC ${preview.played ? `${preview.hc1.toFixed(1)} / ${preview.hc2.toFixed(1)}` : '—'}</p>
    `;
  }
  const sign1 = document.getElementById('sign1-name');
  const sign2 = document.getElementById('sign2-name');
  if (sign1) sign1.textContent = preview.n1;
  if (sign2) sign2.textContent = preview.n2;
  document.querySelectorAll('.frame').forEach((row, index) => {
    const frame = preview.frames[index];
    row.classList.toggle('played', Boolean(frame && frame.p1 + frame.p2 > 0));
    row.classList.toggle('p1', Boolean(frame && frame.p1 > frame.p2));
    row.classList.toggle('p2', Boolean(frame && frame.p2 > frame.p1));
  });
}

function showWizardStep() {
  document.querySelectorAll('[data-step]').forEach((el) => {
    el.hidden = Number(el.dataset.step) !== wizardStep;
  });
  document.querySelectorAll('.step-dot').forEach((el) => {
    const n = Number(el.dataset.stepDot);
    el.classList.toggle('on', n === wizardStep);
    el.classList.toggle('done', n < wizardStep);
  });
  setHidden('wiz-back', wizardStep === 1);
  setHidden('wiz-next', wizardStep === 4);
  setHidden('wiz-save', wizardStep !== 4);
  const save = document.getElementById('wiz-save');
  if (save) save.disabled = lastRoster.length < 2;
  setText('wiz-label', ['Avond', 'Spelers', 'Frames', 'Afronden'][wizardStep - 1]);
  syncSignatureUi();
  refreshPreview();
}

function validateStep(form) {
  if (wizardStep === 1) {
    if (!form.tournament.value.trim()) return 'Kies of typ een tornooi.';
    if (!form.date.value) return 'Kies de datum van de wedstrijd.';
  }
  if (wizardStep === 2) {
    if (lastRoster.length < 2) return 'Nog te weinig spelers op de clublijst. Vraag de clubbaas om namen toe te voegen.';
    if (!form.player1.value || !form.player2.value) return 'Kies beide spelers uit de lijst.';
    if (form.player1.value === form.player2.value) return 'Speler 2 kan niet dezelfde zijn als speler 1.';
  }
  if (wizardStep === 3) {
    const preview = wizardPreview(form);
    if (!preview.played) return 'Vul minstens één gespeeld frame in. Laat ongebruikte frames op 0–0.';
    const n = frameSlots(form);
    for (let i = 1; i <= n; i += 1) {
      const a = Number(form[`f${i}p1`].value);
      const b = Number(form[`f${i}p2`].value);
      if (a < 0 || a > 200 || b < 0 || b > 200) return `Frame ${i} moet tussen 0 en 200 liggen.`;
    }
  }
  if (wizardStep === 4) {
    const b1 = Number(form.break1.value);
    const b2 = Number(form.break2.value);
    if (b1 < 0 || b1 > 155 || b2 < 0 || b2 > 155) return 'Een break is 0–147, of tot 155 bij free ball.';
    if (signaturesRequired() && (!readSignature1() || !readSignature2())) {
      return 'Beide spelers moeten hun handtekening zetten.';
    }
  }
  return '';
}

function setFormError(message) {
  const err = document.getElementById('form-err');
  if (!err) return;
  err.hidden = !message;
  err.textContent = message || '';
}

function defaultTab() {
  if (typeof window !== 'undefined' && window.SNOOKER_TAB && GUEST_TABS.includes(window.SNOOKER_TAB)) {
    return window.SNOOKER_TAB;
  }
  if (/\/ingeven\/?$/.test(location.pathname)) return 'match';
  const queryTab = new URLSearchParams(location.search).get('tab');
  if (queryTab && GUEST_TABS.includes(queryTab)) return queryTab;
  if (queryTab && HASH_TAB[queryTab]) return HASH_TAB[queryTab];
  return HASH_TAB[location.hash.replace('#', '')] || 'club';
}

function tabFromHash() {
  return HASH_TAB[location.hash.replace('#', '')] || defaultTab();
}

function showGuestTab(name) {
  const tab = GUEST_TABS.includes(name) ? name : 'club';
  document.querySelectorAll('[data-guest-pane]').forEach((el) => {
    el.hidden = el.dataset.guestPane !== tab;
  });
  document.querySelectorAll('[data-guest-tab]').forEach((btn) => {
    btn.classList.toggle('on', btn.dataset.guestTab === tab);
  });
  const nextHash = TAB_HASH[tab] ? `#${TAB_HASH[tab]}` : '';
  if ((location.hash || '') !== nextHash) {
    history.replaceState(null, '', `${location.pathname}${location.search}${nextHash}`);
  }
}

function setupSignature(canvasId, clearId) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) {
    return { read: () => '', clear: () => {} };
  }
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = '#163524';
  ctx.lineWidth = 2.4;
  ctx.lineCap = 'round';
  let drawing = false;
  let dirty = false;

  function pos(event) {
    const rect = canvas.getBoundingClientRect();
    const touch = event.touches ? event.touches[0] : event;
    return {
      x: ((touch.clientX - rect.left) / rect.width) * canvas.width,
      y: ((touch.clientY - rect.top) / rect.height) * canvas.height,
    };
  }
  function start(event) {
    drawing = true;
    dirty = true;
    const p = pos(event);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    event.preventDefault();
  }
  function move(event) {
    if (!drawing) return;
    const p = pos(event);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    event.preventDefault();
  }
  function end() { drawing = false; }
  function clear() {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    dirty = false;
  }

  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  window.addEventListener('pointerup', end);
  document.getElementById(clearId)?.addEventListener('click', clear);
  return {
    read: () => (dirty ? canvas.toDataURL('image/jpeg', 0.72) : ''),
    clear,
  };
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function setMeter(id, pct) {
  const el = document.getElementById(id);
  if (el) el.style.width = `${Math.max(0, Math.min(100, Number(pct) || 0))}%`;
}

function eventWhen(event) {
  const time = [event.start, event.end].filter(Boolean).join('–');
  return time ? `${event.date} · ${time}` : event.date;
}

function formatAgendaDay(date) {
  if (!date) return 'Kies een dag';
  return new Date(`${date}T12:00:00`).toLocaleDateString('nl-NL', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });
}

function pickDefaultAgendaDay(agenda, preferred = '') {
  const days = (agenda.days || []).filter((day) => !day.empty);
  if (preferred && days.some((day) => day.date === preferred)) return preferred;
  const today = days.find((day) => day.today);
  if (today) return today.date;
  const next = (agenda.upcoming || []).find((event) => days.some((day) => day.date === event.date));
  if (next) return next.date;
  const busy = days.find((day) => day.events?.length);
  return busy?.date || days[0]?.date || '';
}

function renderAgendaDetail(agenda, date) {
  const kicker = document.getElementById('guest-agenda-day-kicker');
  const label = document.getElementById('guest-agenda-day-label');
  const box = document.getElementById('guest-agenda-detail');
  if (!box) return;
  const day = (agenda.days || []).find((row) => row.date === date);
  const events = day?.events || [];
  if (kicker) kicker.textContent = day?.today ? 'Vandaag' : (events.length ? 'Deze dag' : 'Dag');
  if (label) label.textContent = formatAgendaDay(date);
  if (!date) {
    box.innerHTML = '<p class="sub">Tik een dag in de kalender om de details te zien.</p>';
    return;
  }
  box.innerHTML = events.length
    ? events.map((event) => `
      <div class="row">
        <div>
          <strong><span class="kind ${esc(event.kind)}">${esc(event.kindLabel || event.kind)}</span>${esc(event.title)}</strong>
          <div class="sub">${esc(eventWhen(event))}${event.tournament ? ` · ${esc(event.tournament)}` : ''}${event.place ? ` · ${esc(event.place)}` : ''}${event.note ? ` · ${esc(event.note)}` : ''}</div>
        </div>
      </div>
    `).join('')
    : '<p class="sub">Geen clubavond of event op deze dag.</p>';
}

function selectAgendaDay(date) {
  selectedAgendaDay = date;
  if (!lastAgenda) return;
  document.querySelectorAll('#guest-agenda-grid [data-agenda-day]').forEach((btn) => {
    btn.classList.toggle('on', btn.dataset.agendaDay === date);
  });
  renderAgendaDetail(lastAgenda, date);
}

function renderAgenda(agenda, preferredDay = selectedAgendaDay) {
  if (!agenda) return;
  lastAgenda = agenda;
  selectedAgendaDay = pickDefaultAgendaDay(agenda, preferredDay);
  setText('guest-agenda-label', agenda.label || 'Agenda');
  const weekdays = document.getElementById('guest-weekdays');
  if (weekdays) {
    weekdays.innerHTML = (agenda.weekdays || []).map((day) => `<span>${esc(day)}</span>`).join('');
  }
  const grid = document.getElementById('guest-agenda-grid');
  if (grid) {
    grid.innerHTML = (agenda.days || []).map((day) => {
      if (day.empty) return '<button type="button" class="cal-day empty" disabled></button>';
      const first = (day.events || [])[0];
      const extra = `${day.today ? ' today' : ''}${selectedAgendaDay === day.date ? ' on' : ''}${first ? ' has-events' : ''}`;
      return `<button type="button" class="cal-day${extra}" data-agenda-day="${esc(day.date)}">
        <strong>${day.day}</strong>
        ${first ? `<small>${esc(first.title)}</small>` : ''}
        ${day.events.length > 1 ? `<small>+${day.events.length - 1}</small>` : ''}
      </button>`;
    }).join('');
    grid.querySelectorAll('[data-agenda-day]').forEach((btn) => {
      btn.addEventListener('click', () => selectAgendaDay(btn.dataset.agendaDay));
    });
  }
  renderAgendaDetail(agenda, selectedAgendaDay);
}

async function loadGuestAgenda(year, month) {
  const query = year && month ? `?year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}` : '';
  const data = await json(apiUrl('/api/public/agenda') + query);
  renderAgenda(data.agenda, '');
}

function applyHero(_url) {
  /* Guest volgt het admin-dashboard; geen full-page foto meer. */
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

function renderPlayers(players) {
  const podium = document.getElementById('podium');
  const table = document.getElementById('player-table');
  if (!podium || !table) return;
  const top = (players || []).filter((player) => player.trophy).slice(0, 3);
  podium.innerHTML = top.length
    ? top.map((player) => `
      <div class="seat ${player.trophy}">
        ${trophySvg(player.trophy)}
        <strong>${esc(player.name)}</strong>
        <p class="sub">${player.points} punten · HC ${formatHc(player.handicap, player.framesPlayed)}</p>
      </div>
    `).join('')
    : '<p class="sub">Nog geen ranglijst. Speel de eerste frames.</p>';
  table.innerHTML = (players || []).map((player) => `
    <tr>
      <td>${player.rank}</td>
      <td>${player.trophy ? trophySvg(player.trophy) : ''}<button type="button" class="player-link" data-dossier="${esc(player.name)}">${esc(player.name)}</button></td>
      <td>${player.wins}</td>
      <td>${player.losses}</td>
      <td>${player.framesFor}</td>
      <td>${player.framesAgainst}</td>
      <td>${formatPct(player.matchPct ?? player.winRate)}</td>
      <td>${formatPct(player.framePct)}</td>
      <td>${player.highestBreak || '—'}</td>
      <td>${formatGem(player)}</td>
    </tr>
  `).join('') || '<tr><td colspan="10">Nog geen spelers.</td></tr>';
  table.querySelectorAll('[data-dossier]').forEach((btn) => {
    btn.addEventListener('click', () => openDossier(btn.dataset.dossier));
  });
}

async function openDossier(name) {
  const card = document.getElementById('dossier-card');
  if (!card) return;
  try {
    const dossier = await json(apiUrl('/api/public/dossier?player=' + encodeURIComponent(name)));
    const c = dossier.career || {};
    setText('dossier-title', dossier.player);
    card.hidden = false;
    document.getElementById('dossier-career').innerHTML = [
      ['win', 'Winst / frames', c.winsFrames || 0],
      ['win', 'Winst / partijen', c.winsMatches || 0],
      ['break', 'Hoogste break', c.highestBreak || 0],
      ['break', 'Gem. break', String(c.gemBreak || 0).replace('.', ',')],
      ['break', 'Gem. punten', c.avgPoints != null ? Number(c.avgPoints).toFixed(2).replace('.', ',') : '—'],
      ['win', 'Gem. F+', c.avgFrames != null ? Number(c.avgFrames).toFixed(2).replace('.', ',') : '—'],
      ['loss', 'Verlies / frames', c.lossesFrames || 0],
      ['loss', 'Verlies / partijen', c.lossesMatches || 0],
      ['pct', 'Framepercentage', formatPct(c.framePct)],
      ['pct', 'Partijpercentage', formatPct(c.matchPct)],
      ['total', 'Totaal frames', c.totalFrames || 0],
      ['total', 'Totaal partijen', c.totalMatches || 0],
    ].map(([tone, label, value]) => `<article class="career-card ${tone}"><p>${esc(label)}</p><strong>${esc(value)}</strong></article>`).join('');
    document.getElementById('dossier-h2h').innerHTML = (dossier.headToHead || []).length
      ? `<table><thead><tr><th>VERSUS</th><th>W</th><th>L</th><th>F+</th><th>F-</th><th>Laatste</th></tr></thead><tbody>${
        dossier.headToHead.map((row) => `<tr><td>${esc(row.versus)}</td><td>${row.wins}</td><td>${row.losses}</td><td>${row.framesFor}</td><td>${row.framesAgainst}</td><td>${esc(row.lastResult)}</td></tr>`).join('')
      }</tbody></table>`
      : '<p class="sub">Nog geen onderlinge partijen.</p>';
    document.getElementById('dossier-table').innerHTML = (dossier.rows || []).length
      ? `<table><thead><tr><th>VERSUS</th><th>TOURNAMENT</th><th>RESULT</th><th>W</th><th>L</th><th>BREAKS</th><th>ROUND</th><th>SEASON</th></tr></thead><tbody>${
        dossier.rows.map((row) => `<tr><td>${esc(row.versus)}</td><td>${esc(row.tournament)}</td><td>${esc(row.result)}</td><td>${row.w}</td><td>${row.l}</td><td>${row.breaks || ''}</td><td>${esc(row.roundLabel)}</td><td>${esc(row.season)}</td></tr>`).join('')
      }</tbody></table>`
      : '<p class="sub">Nog geen partijen.</p>';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (err) {
    card.hidden = false;
    setText('dossier-title', name);
    document.getElementById('dossier-career').innerHTML = '';
    document.getElementById('dossier-table').innerHTML = `<p class="sub">${esc(err.message)}</p>`;
  }
}

function renderOverview(data) {
  lastBrand = data.brand || lastBrand;
  const form = document.getElementById('match-form');
  if (form?.frameFormat && lastBrand && !form.dataset.bestLocked) {
    const mode = lastBrand.frameMode === 'fixed' ? 'fixed' : 'bestof';
    const count = lastBrand.framesCount || 5;
    const value = `${mode}:${count}`;
    if ([...form.frameFormat.options].some((opt) => opt.value === value)) {
      form.frameFormat.value = value;
    }
    setupFrames(form);
  }
  syncSignatureUi();
  document.title = data.brand.clubName || 'SC De Merodesnookers';
  setText('club-kicker', data.brand.venue || data.brand.tagline || data.brand.clubName);
  setText('club-name', data.brand.clubName);
  setText('club-tag', data.brand.tagline);
  if (window.SnookerTheme) window.SnookerTheme.apply(data.brand);
  else {
    document.documentElement.style.setProperty('--accent', data.brand.accent);
    document.documentElement.style.setProperty('--gold', data.brand.accentSoft);
  }
  applyHero(data.brand.heroUrl);
  applyLogo(data.brand.logoUrl);
  const notice = document.getElementById('notice');
  if (notice) {
    notice.hidden = !data.brand.notice;
    notice.textContent = data.brand.notice || '';
  }
  const meta = document.getElementById('club-meta');
  const bits = [
    data.brand.venue && `Locatie: ${data.brand.venue}`,
    data.brand.openingHours && `Open: ${data.brand.openingHours}`,
    (data.nextEventLabel || data.brand.nextEvent) && `Volgende: ${data.nextEventLabel || data.brand.nextEvent}`,
  ].filter(Boolean);
  if (meta) {
    meta.hidden = !bits.length;
    meta.innerHTML = bits.map((bit) => `<span>${esc(bit)}</span>`).join('');
  }
  const high = data.highestBreak || {};
  const progress = data.kpis?.progress || {};
  setText('high-break', high.value ? String(high.value) : '—');
  setText('high-break-who', high.player
    ? `${high.player}${high.date ? ` · ${high.date}` : ''} · van 147 (155 met free ball)`
    : 'Nog geen breaks');
  setMeter('high-break-bar', progress.break?.pct);
  setText('match-count', String(data.kpis?.matchesMonth ?? data.matchCount ?? 0));
  setText('tour-count', `${progress.month?.value || 0} van ${progress.month?.goal || 0} · ${progress.frames?.value || 0} van ${progress.frames?.goal || 0} frames`);
  setMeter('match-bar', progress.month?.pct);
  setText('player-count', String(data.kpis?.activePlayers ?? data.playerCount ?? 0));
  setText('century-count', `${data.kpis?.activePlayers || 0} van ${data.kpis?.rosterCount || 0} actief · ${progress.centuries?.value || 0} van ${progress.centuries?.goal || 0} centuries`);
  setMeter('player-bar', progress.active?.pct);
  setText('member-count', String(data.kpis?.newMembersMonth || 0));
  setText('member-count-sub', `+${data.kpis?.newMembersMonth || 0} van ${progress.members?.goal || 5} deze maand`);
  setMeter('member-bar', progress.members?.pct);
  setText('night-count', String(data.kpis?.clubNightsMonth || 0));
  setText('night-count-sub', `${data.kpis?.clubNightsUpcoming || 0} gepland · doel ${progress.nights?.goal || 4}`);
  setMeter('night-bar', progress.nights?.pct);
  renderAgenda(data.agenda);
  renderPlayers(data.players || []);
  const recent = document.getElementById('recent');
  if (recent) recent.innerHTML = (data.recent || []).map((match) => `
    <div class="row">
      <div>
        <strong>${match.winner ? `${esc(match.winner)} wint` : 'Gelijkspel'} · ${esc(match.player1)} — ${esc(match.player2)}</strong>
        <div class="sub">
          ${esc(match.tournament)} · ${esc(match.date)}
          ${match.frameMode === 'fixed' ? ` · ${match.bestOf || match.frames?.length || ''} frames (poule)` : (match.bestOf ? ` · best of ${match.bestOf}` : '')}
          ${match.framesPlayed ? ` · HC ${formatHc(match.handicap1, match.framesPlayed)}/${formatHc(match.handicap2, match.framesPlayed)}` : ''}
          ${match.note ? ` · ${esc(match.note)}` : ''}
        </div>
        ${data.brand.showSignatures !== false && (match.signature1 || match.signature2) ? `
          <div class="sign-thumbs">
            ${match.signature1 ? `<img src="${esc(match.signature1)}" alt="Handtekening ${esc(match.player1)}" />` : ''}
            ${match.signature2 ? `<img src="${esc(match.signature2)}" alt="Handtekening ${esc(match.player2)}" />` : ''}
          </div>
        ` : ''}
      </div>
      <div>${scoreline(match)}${match.highestBreak?.value ? ` · break ${match.highestBreak.value}` : ''}</div>
    </div>
  `).join('') || '<p class="sub">Nog geen uitslagen.</p>';
  const names = [...new Set([...(data.brand.tournaments || []), ...(data.tournaments || [])])];
  const tours = document.getElementById('tournaments');
  if (tours) {
    tours.innerHTML = names
      .map((name) => `<option value="${esc(name)}"></option>`).join('');
  }
  fillPlayerSelects(data.roster || []);
  showWizardStep();
}

function resetWizard(form) {
  form.reset();
  form.date.value = today();
  wizardStep = 1;
  clearSignatures();
  showWizardStep();
}

async function boot() {
  setupFrames();
  const first = setupSignature('sign1', 'sign1-clear');
  const second = setupSignature('sign2', 'sign2-clear');
  readSignature1 = first.read;
  readSignature2 = second.read;
  clearSignatures = () => {
    first.clear();
    second.clear();
  };
  const dateField = document.querySelector('[name=date]');
  if (dateField) dateField.value = today();
  document.querySelectorAll('[data-guest-tab]').forEach((btn) => {
    btn.addEventListener('click', () => showGuestTab(btn.dataset.guestTab));
  });
  document.getElementById('guest-agenda-prev')?.addEventListener('click', () => {
    if (!lastAgenda?.prev) return;
    loadGuestAgenda(lastAgenda.prev.year, lastAgenda.prev.month).catch(() => {});
  });
  document.getElementById('guest-agenda-next')?.addEventListener('click', () => {
    if (!lastAgenda?.next) return;
    loadGuestAgenda(lastAgenda.next.year, lastAgenda.next.month).catch(() => {});
  });
  showGuestTab(tabFromHash());
  window.addEventListener('hashchange', () => showGuestTab(tabFromHash()));

  try {
    renderOverview(await json(apiUrl('/api/public/overview')));
  } catch {
    setText('high-break-who', 'Overzicht niet geladen');
    const table = document.getElementById('player-table');
    if (table) {
      table.innerHTML = '<tr><td colspan="7">Clubdata kon niet geladen worden. Open Snookerclub → Clubbeheer of vernieuw de pagina.</td></tr>';
    }
  }

  const form = document.getElementById('match-form');
  if (!form) return;
  form.addEventListener('input', () => {
    setFormError('');
    syncPlayerChoices();
    refreshPreview();
  });
  form.frameFormat?.addEventListener('change', () => {
    form.dataset.bestLocked = '1';
    setupFrames(form);
    refreshPreview();
  });
  form.player1.addEventListener('change', syncPlayerChoices);
  form.player2.addEventListener('change', syncPlayerChoices);
  document.getElementById('wiz-next')?.addEventListener('click', () => {
    const message = validateStep(form);
    if (message) {
      setFormError(message);
      return;
    }
    wizardStep = Math.min(4, wizardStep + 1);
    setFormError('');
    showWizardStep();
  });
  document.getElementById('wiz-back')?.addEventListener('click', () => {
    wizardStep = Math.max(1, wizardStep - 1);
    setFormError('');
    showWizardStep();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    wizardStep = 4;
    const message = validateStep(form) || validateStep(Object.assign(form, {}));
    const step3 = (() => {
      wizardStep = 3;
      const err = validateStep(form);
      wizardStep = 4;
      return err;
    })();
    const step2 = (() => {
      wizardStep = 2;
      const err = validateStep(form);
      wizardStep = 4;
      return err;
    })();
    const step1 = (() => {
      wizardStep = 1;
      const err = validateStep(form);
      wizardStep = 4;
      return err;
    })();
    const block = step1 || step2 || step3 || message;
    if (block) {
      setFormError(block);
      return;
    }
    try {
      await json(apiUrl('/api/matches'), {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          tournament: form.tournament.value,
          date: form.date.value,
          player1: form.player1.value,
          player2: form.player2.value,
          frames: collectFrames(form),
          break1: form.break1.value,
          break2: form.break2.value,
          frameFormat: form.frameFormat?.value || 'bestof:5',
          note: form.note.value,
          signature1: signaturesRequired() ? readSignature1() : '',
          signature2: signaturesRequired() ? readSignature2() : '',
        }),
      });
      resetWizard(form);
      if (form.frameFormat && lastBrand) {
        const mode = lastBrand.frameMode === 'fixed' ? 'fixed' : 'bestof';
        const count = lastBrand.framesCount || 5;
        const value = `${mode}:${count}`;
        if ([...form.frameFormat.options].some((opt) => opt.value === value)) {
          form.frameFormat.value = value;
        }
        delete form.dataset.bestLocked;
        setupFrames(form);
      }
      syncSignatureUi();
      renderOverview(await json(apiUrl('/api/public/overview')));
      showGuestTab(defaultTab() === 'match' ? 'match' : 'club');
    } catch (error) {
      setFormError(error.message);
    }
  });
}

boot();
