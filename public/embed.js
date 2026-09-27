(function () {
  var script = document.currentScript;
  var root = new URL('.', script && script.src ? script.src : location.href);
  if (/\/live\/?$/.test(root.pathname)) root = new URL('../', root);
  var wp = typeof window !== 'undefined' && window.SNOOKER_WP;

  function el(tag, attrs, html) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    if (html) node.innerHTML = html;
    return node;
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  function trophy(kind) {
    if (!kind) return '';
    var fill = { gold: '#e4c25a', silver: '#c5cdd4', bronze: '#c47a3a' }[kind];
    return '<svg class="snooker-trophy" width="16" height="16" viewBox="0 0 24 24"><path fill="' + fill + '" d="M7 3h10v2h3a1 1 0 0 1 1 1v2a5 5 0 0 1-4.1 4.9A6 6 0 0 1 13 16.9V18h3v2H8v-2h3v-1.1A6 6 0 0 1 7.1 12.9 5 5 0 0 1 3 8V6a1 1 0 0 1 1-1h3z"/></svg>';
  }

  function hc(player) {
    return player && player.framesPlayed ? Number(player.handicap || 0).toFixed(1) : '—';
  }

  function gem(player) {
    if (player && player.framesPlayed && player.avgPoints != null) {
      return Number(player.avgPoints).toFixed(1).replace('.', ',');
    }
    if (player && player.framesPlayed) return hc(player);
    if (player && player.avgFrames != null) return Number(player.avgFrames).toFixed(2).replace('.', ',');
    return '—';
  }

  function printTarget(target) {
    var nodes = [];
    var cleaned = false;
    function done() {
      if (cleaned) return;
      cleaned = true;
      document.body.classList.remove('snooker-printing');
      nodes.forEach(function (node) { node.classList.remove('snooker-print-target'); });
      window.removeEventListener('afterprint', done);
    }
    document.body.classList.add('snooker-printing');
    if (target === 'page') {
      document.querySelectorAll('.snooker-print-root, .snookerclub-board--rapport, .snookerclub-board--dossier, .snookerclub-board--h2h').forEach(function (node) {
        node.classList.add('snooker-print-target');
        nodes.push(node);
      });
    } else if (target) {
      target.classList.add('snooker-print-target');
      nodes.push(target);
    }
    window.addEventListener('afterprint', done);
    window.print();
    setTimeout(done, 2000);
  }

  window.snookerPrint = function (btn) {
    if (!btn) return printTarget('page');
    if (btn.getAttribute && btn.getAttribute('data-snooker-print') === 'page') return printTarget('page');
    printTarget(btn.closest ? btn.closest('.snookerclub-board, .snooker-print-root') : null);
  };

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest && ev.target.closest('[data-snooker-print]');
    if (!btn || btn.tagName === 'A' || btn.classList.contains('snooker-csv-btn')) return;
    ev.preventDefault();
    window.snookerPrint(btn);
  });

  function logo(brand) {
    return brand && brand.logoUrl
      ? '<img class="snooker-board-logo" alt="" src="' + esc(brand.logoUrl) + '" />'
      : '';
  }

  function head(brand, title) {
    return '<header class="snooker-board-head">' + logo(brand) + '<p class="snooker-live-kicker">' + esc((brand && brand.clubName) || 'SC De Merodesnookers') +
      '</p><h3 class="snooker-live-title">' + esc(title) + '</h3></header>';
  }

  function podium(players) {
    var top = (players || []).slice(0, 3);
    if (!top.length) return '';
    return '<div class="snooker-podium">' + top.map(function (player) {
      return '<article class="snooker-seat ' + esc(player.trophy || '') + '">' + trophy(player.trophy) +
        '<strong>' + esc(player.name) + '</strong><p>' + (player.points || 0) + ' ptn · HC ' + hc(player) + '</p></article>';
    }).join('') + '</div>';
  }

  function sheetHead(brand, title, season, printed) {
    var meta = [season ? 'Seizoen ' + season : 'Alle seizoenen', printed ? 'Afgedrukt ' + printed : ''].filter(Boolean).join(' · ');
    return '<header class="snooker-sheet-head">' + (brand && brand.logoUrl ? '<img class="snooker-sheet-logo" alt="" src="' + esc(brand.logoUrl) + '" />' : '') +
      '<p class="snooker-sheet-brand">' + esc((brand && brand.clubName) || 'SC De Merodesnookers') +
      '</p><h3 class="snooker-sheet-title">' + esc(title) + '</h3><p class="snooker-sheet-meta">' + esc(meta) + '</p></header>';
  }

  function toolbar(title, csv) {
    return '<div class="snooker-report-toolbar no-print"><button type="button" class="snooker-print-btn" data-snooker-print="sheet">' +
      esc(title || 'Afdrukken') + '</button>' +
      (csv ? '<a class="snooker-print-btn snooker-csv-btn" href="' + esc(csv) + '">CSV</a>' : '') +
      '</div>';
  }

  function career(cards) {
    var data = cards || {};
    var items = [
      ['Winst', data.winsMatches || 0],
      ['Verlies', data.lossesMatches || 0],
      ['M%', data.matchPct != null ? Number(data.matchPct).toFixed(2).replace('.', ',') + '%' : '0,00%'],
      ['Hoogste', data.highestBreak || '—'],
      ['Gem. break', data.gemBreak != null ? Number(data.gemBreak).toFixed(2).replace('.', ',') : '—'],
      ['Gem. punten', data.avgPoints != null ? Number(data.avgPoints).toFixed(2).replace('.', ',') : '—'],
      ['Gem. F+', data.avgFrames != null ? Number(data.avgFrames).toFixed(2).replace('.', ',') : '—'],
      ['F+', data.winsFrames || 0],
      ['F-', data.lossesFrames || 0],
      ['F%', data.framePct != null ? Number(data.framePct).toFixed(2).replace('.', ',') + '%' : '0,00%'],
    ];
    return '<div class="snooker-career-grid">' + items.map(function (card) {
      return '<article class="snooker-career-card"><p class="snooker-live-muted">' + esc(card[0]) +
        '</p><p class="snooker-live-big">' + esc(card[1]) + '</p></article>';
    }).join('') + '</div>';
  }

  function dossierTable(rows) {
    if (!(rows || []).length) return '<p class="snooker-live-muted">Nog geen partijen in dit dossier.</p>';
    return '<div class="snooker-table-wrap"><table class="snooker-league snooker-sheet-table"><thead><tr><th>VERSUS</th><th>TOURNAMENT</th><th>RESULT</th><th>W</th><th>L</th><th>BREAKS</th><th>ROUND</th><th>SEASON</th></tr></thead><tbody>' +
      rows.map(function (row) {
        return '<tr><td>' + esc(row.versus || '') + '</td><td>' + esc(row.tournament || '') +
          '</td><td class="num">' + esc(String(row.result || '').replace('-', '/')) +
          '</td><td class="num">' + (row.w || 0) + '</td><td class="num">' + (row.l || 0) +
          '</td><td class="num">' + esc(row.breaks || '') + '</td><td>' + esc(row.roundLabel || row.round || '') +
          '</td><td>' + esc(row.season || '') + '</td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function h2hTable(rows) {
    if (!(rows || []).length) return '<p class="snooker-live-muted">Nog geen onderlinge partijen.</p>';
    return '<div class="snooker-table-wrap"><table class="snooker-league snooker-sheet-table"><thead><tr><th>VERSUS</th><th>W</th><th>L</th><th>F+</th><th>F-</th><th>Laatste</th></tr></thead><tbody>' +
      rows.map(function (row) {
        return '<tr><td>' + esc(row.versus || '') + '</td><td class="num">' + (row.wins || 0) +
          '</td><td class="num">' + (row.losses || 0) + '</td><td class="num">' + (row.framesFor || 0) +
          '</td><td class="num">' + (row.framesAgainst || 0) + '</td><td class="num">' + esc(row.lastResult || '') + '</td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function league(players) {
    if (!(players || []).length) return '<p class="snooker-live-muted">Nog geen spelers.</p>';
    return '<div class="snooker-table-wrap"><table class="snooker-league snooker-sheet-table"><thead><tr><th>#</th><th>Speler</th><th>W</th><th>L</th><th>F+</th><th>F-</th><th>M%</th><th>F%</th><th>HB</th><th>Gem.</th></tr></thead><tbody>' +
      players.map(function (player) {
        var matchPct = player.matchPct != null ? Number(player.matchPct).toFixed(2).replace('.', ',') + '%' : (player.winRate || 0) + '%';
        var framePct = player.framePct != null ? Number(player.framePct).toFixed(2).replace('.', ',') + '%' : '0,00%';
        return '<tr><td class="num">' + (player.rank || 0) + '</td><td class="name">' + trophy(player.trophy) + esc(player.name) +
          '</td><td class="num">' + (player.wins || 0) + '</td><td class="num">' + (player.losses || 0) +
          '</td><td class="num">' + (player.framesFor || 0) + '</td><td class="num">' + (player.framesAgainst || 0) +
          '</td><td class="num">' + matchPct + '</td><td class="num">' + framePct +
          '</td><td class="num">' + (player.highestBreak || '—') + '</td><td class="num">' +
          gem(player) + '</td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function results(matches) {
    if (!(matches || []).length) return '<p class="snooker-live-muted">Nog geen uitslagen.</p>';
    return '<ul class="snooker-results">' + matches.map(function (match) {
      return '<li><span class="who">' + esc(match.player1) + ' — ' + esc(match.player2) + '</span><strong>' +
        (match.wins ? match.wins.p1 : 0) + '–' + (match.wins ? match.wins.p2 : 0) + '</strong><span class="meta">' +
        esc(match.date || '') + (match.tournament ? ' · ' + esc(match.tournament) : '') + '</span></li>';
    }).join('') + '</ul>';
  }

  function renderLive(host, data) {
    var high = data.highestBreak || {};
    var latest = data.latest || (data.recent && data.recent[0]);
    host.innerHTML = head(data.brand, 'Live overzicht') +
      '<div class="snooker-live-grid"><div class="snooker-live-card"><p class="snooker-live-muted">Hoogste break</p><p class="snooker-live-big">' +
      (high.value || '—') + '</p><p class="snooker-live-muted">' + esc(high.player || 'Nog geen breaks') +
      '</p></div><div class="snooker-live-card"><p class="snooker-live-muted">Laatste wedstrijd</p><p class="snooker-live-big">' +
      (latest && latest.wins ? latest.wins.p1 + '–' + latest.wins.p2 : '—') +
      '</p><p class="snooker-live-muted">' + (latest ? esc(latest.player1) + ' — ' + esc(latest.player2) : esc(data.nextEventLabel || 'Nog geen wedstrijd')) +
      '</p></div></div>' + podium(data.top3) + results((data.recent || []).slice(0, 4));
  }

  function renderPlayers(host, data) {
    host.innerHTML = head(data.brand, 'Ranking') + podium(data.top3 || data.players) + league(data.players);
  }

  function renderMatchList(host, data) {
    host.innerHTML = head(data.brand, 'Uitslagen') + results(data.recent);
  }

  function renderAgenda(host, data) {
    var agenda = data.agenda || {};
    host.innerHTML = head(data.brand, agenda.label || 'Clubagenda') +
      '<div class="snooker-live-weekdays">' + (agenda.weekdays || []).map(function (day) { return '<span>' + esc(day) + '</span>'; }).join('') + '</div>' +
      '<div class="snooker-live-cal">' + (agenda.days || []).map(function (day) {
        if (day.empty) return '<div class="snooker-live-day empty"></div>';
        var first = (day.events || [])[0];
        return '<div class="snooker-live-day' + (day.today ? ' today' : '') + '"><strong>' + day.day + '</strong>' +
          (first ? '<small>' + esc(first.title) + '</small>' : '') + '</div>';
      }).join('') + '</div>' +
      ((agenda.upcoming || []).length
        ? agenda.upcoming.map(function (event) {
          var time = [event.start, event.end].filter(Boolean).join('–');
          return '<div class="snooker-live-row"><span>' + esc(event.kindLabel || event.kind) + ' · ' + esc(event.title) + '</span><strong>' +
            esc(event.date) + (time ? ' · ' + esc(time) : '') + '</strong></div>';
        }).join('')
        : '<p class="snooker-live-muted">Nog geen clubavonden gepland.</p>');
  }

  function renderBreak(host, data) {
    var high = data.highestBreak || {};
    host.innerHTML = head(data.brand, 'Hoogste break') +
      '<div class="snooker-live-card snooker-break-hero"><p class="snooker-live-big">' + (high.value || '—') +
      '</p><p class="snooker-live-muted">' + esc(high.player || 'Nog geen breaks') + (high.date ? ' · ' + esc(high.date) : '') + '</p></div>';
  }

  function renderNext(host, data) {
    var event = data.nextEvent;
    host.innerHTML = head(data.brand, 'Volgende clubavond') + (event
      ? '<div class="snooker-live-card"><p class="snooker-live-muted">' + esc(event.kindLabel || event.kind || 'Clubavond') +
        '</p><p class="snooker-live-big">' + esc(event.title || 'Clubavond') + '</p><p class="snooker-live-muted">' +
        esc(event.date || '') + '</p></div>'
      : '<p class="snooker-live-muted">' + esc(data.nextEventLabel || 'Nog geen clubavond gepland') + '</p>');
  }

  function renderRapport(host, data) {
    var csv = host.getAttribute('data-src') || '';
    host.innerHTML = toolbar('Ranglijst afdrukken', csv ? csv + (csv.indexOf('?') >= 0 ? '&' : '?') + 'format=csv' : '') +
      sheetHead(data.brand, 'Ranglijst', data.season, data.printedAt) + league(data.players);
  }

  function renderDossier(host, data) {
    var csv = host.getAttribute('data-src') || '';
    host.innerHTML = toolbar('Dossier afdrukken', csv ? csv + (csv.indexOf('?') >= 0 ? '&' : '?') + 'format=csv' : '') +
      sheetHead(data.brand, data.player || 'Spelersdossier', data.season, data.printedAt) +
      career(data.career) +
      '<h4 class="snooker-subhead">Head-to-head</h4>' + h2hTable(data.headToHead) +
      '<h4 class="snooker-subhead">Partijlog</h4>' + dossierTable(data.rows);
  }

  function renderH2h(host, data) {
    var title = [data.a, data.b].filter(Boolean).join(' — ') || 'Head-to-head';
    var pair = data.rows && data.rows.length ? [{
      versus: data.b,
      wins: data.wins,
      losses: data.losses,
      framesFor: data.framesFor,
      framesAgainst: data.framesAgainst,
      lastResult: data.lastResult,
    }] : (data.headToHead || []);
    host.innerHTML = toolbar() + sheetHead(data.brand, title, data.season, data.printedAt) +
      h2hTable(pair) + dossierTable(data.rows);
  }

  function paint(host, kind, data) {
    var view = host.getAttribute('data-view') || kind;
    if (view === 'ranking' || view === 'players') renderPlayers(host, data);
    else if (view === 'agenda') renderAgenda(host, data);
    else if (view === 'results') renderMatchList(host, data);
    else if (view === 'break') renderBreak(host, data);
    else if (view === 'next') renderNext(host, data);
    else if (view === 'rapport') renderRapport(host, data);
    else if (view === 'dossier') renderDossier(host, data);
    else if (view === 'h2h') renderH2h(host, data);
    else renderLive(host, data);
  }

  function mount(host, kind) {
    var rest = typeof window !== 'undefined' && window.SNOOKER_REST
      ? String(window.SNOOKER_REST).replace(/\/+$/, '')
      : '';
    var restPath = kind === 'players' || kind === 'ranking' ? '/players'
      : kind === 'agenda' ? '/agenda'
      : kind === 'kpis' || kind === 'next' ? '/overview'
      : kind === 'rapport' ? '/rapport'
      : kind === 'dossier' ? '/dossier'
      : kind === 'h2h' ? '/h2h'
      : '/live';
    var path = kind === 'players' ? 'api/public/players'
      : kind === 'agenda' ? 'api/public/agenda'
      : kind === 'rapport' ? 'api/public/rapport'
      : kind === 'dossier' ? 'api/public/dossier'
      : kind === 'h2h' ? 'api/public/h2h'
      : 'api/public/live';
    var api = host.getAttribute('data-src') || (rest ? rest + restPath : new URL(path, root).href);
    if (kind === 'dossier' && host.getAttribute('data-player') && api.indexOf('player=') < 0) {
      api += (api.indexOf('?') >= 0 ? '&' : '?') + 'player=' + encodeURIComponent(host.getAttribute('data-player'));
    }
    if (host.getAttribute('data-season') && api.indexOf('season=') < 0) {
      api += (api.indexOf('?') >= 0 ? '&' : '?') + 'season=' + encodeURIComponent(host.getAttribute('data-season'));
    }
    if (host.getAttribute('data-static') === '1' && host.querySelector('.snooker-sheet-head, .snooker-league')) {
      return;
    }
    function tick() {
      fetch(api).then(function (res) {
        if (!res.ok) throw new Error('rapport');
        return res.json();
      }).then(function (data) {
        if (data && data.code && data.message && !data.players && !data.rows && !data.career) throw new Error(data.message);
        if (!host.classList.contains('snooker-live-embed')) host.classList.add('snooker-live-embed');
        paint(host, kind, data);
      }).catch(function () {
        if (!host.innerHTML.trim()) host.textContent = 'Liveblok niet bereikbaar.';
      });
    }
    tick();
    if (kind !== 'rapport' && kind !== 'dossier' && kind !== 'h2h') {
      setInterval(tick, 15000);
    }
  }

  function boot() {
    var map = [
      ['[data-snooker-live], #snooker-live', 'live'],
      ['[data-snooker-players], #snooker-players', 'players'],
      ['[data-snooker-agenda], #snooker-agenda', 'agenda'],
      ['[data-snooker-results]', 'results'],
      ['[data-snooker-break]', 'break'],
      ['[data-snooker-next]', 'next'],
      ['[data-snooker-kpis]', 'live'],
      ['[data-snooker-rapport]', 'rapport'],
      ['[data-snooker-dossier]', 'dossier'],
      ['[data-snooker-h2h]', 'h2h'],
    ];
    var found = false;
    map.forEach(function (row) {
      Array.prototype.forEach.call(document.querySelectorAll(row[0]), function (node) {
        found = true;
        mount(node, node.getAttribute('data-view') || row[1]);
      });
    });
    if (!found && !wp && script && script.parentNode) {
      var fallback = el('div', { 'data-snooker-live': '' });
      script.parentNode.insertBefore(fallback, script);
      mount(fallback, 'live');
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
