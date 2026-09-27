(function (blocks, element, components, blockEditor, serverSideRender) {
  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var RangeControl = components.RangeControl;
  var Render = serverSideRender || (window.wp && window.wp.serverSideRender);
  var labels = {
    ranking: 'Live ranking',
    live: 'Live overzicht',
    results: 'Recente uitslagen',
    agenda: 'Clubagenda',
    kpis: 'Clubcijfers',
    break: 'Hoogste break',
    next: 'Volgende clubavond',
    ingeven: 'Uitslag invoeren',
    app: 'Club-app',
    players: 'Spelerslijst',
    rapport: 'Ranglijst-rapport',
    dossier: 'Spelersdossier',
    h2h: 'Head-to-head',
  };
  var hints = {
    ranking: 'Podium en tabel zoals op een clubsite.',
    live: 'Break, laatste partij en top 3.',
    results: 'Recente frames met datum.',
    agenda: 'Maandkalender en clubavonden.',
    kpis: 'Maanddoelen van de club.',
    break: 'Hoogste break in het groot.',
    next: 'Eerstvolgende avond.',
    ingeven: 'Wizard om een uitslag in te voeren.',
    app: 'Volledige club-app.',
    players: 'Zelfde als ranking.',
    rapport: 'Afdrukbare ranglijst met Excel-kolommen.',
    dossier: 'Partijlog, career-kaarten en head-to-head.',
    h2h: 'Onderlinge stand tussen twee spelers.',
  };
  var skins = [
    { label: 'WordPress-thema', value: 'site' },
    { label: 'Clubdashboard', value: 'club' },
  ];
  function card(view, skin) {
    return el('div', {
      className: 'snookerclub-block-preview',
      style: {
        fontFamily: 'inherit',
        border: '1px solid rgba(22,53,36,.12)',
        borderRadius: '12px',
        padding: '16px 18px',
        background: 'transparent',
        color: 'inherit',
      },
    },
      el('p', { style: { letterSpacing: '.08em', textTransform: 'uppercase', fontSize: '11px', margin: '0 0 6px', opacity: 0.7 } }, 'Snookerclub · ' + (skin === 'club' ? 'dashboard' : 'thema')),
      el('h3', { style: { margin: '0 0 8px', fontSize: '20px' } }, labels[view] || view),
      el('p', { style: { margin: 0, opacity: 0.75 } }, hints[view] || 'Inhoud volgt op de pagina.')
    );
  }
  function heavy(view) {
    return view === 'app' || view === 'ingeven';
  }
  blocks.registerBlockType('snookerclub/board', {
    edit: function (props) {
      var view = props.attributes.view || 'ranking';
      var skin = props.attributes.skin || 'site';
      var limit = props.attributes.limit || 0;
      var preview = (!heavy(view) && Render)
        ? el(Render, { block: 'snookerclub/board', attributes: props.attributes })
        : card(view, skin);
      return el('div', { className: 'snookerclub-block-preview' },
        el(InspectorControls, {},
          el(PanelBody, { title: 'Template' },
            el(SelectControl, {
              label: 'Onderdeel',
              value: view,
              options: Object.keys(labels).map(function (key) {
                return { label: labels[key], value: key };
              }),
              onChange: function (value) { props.setAttributes({ view: value }); },
            }),
            el(SelectControl, {
              label: 'Vormgeving',
              help: 'Thema neemt lettertype en kleur van de site over. Dashboard volgt het clubbeheer.',
              value: skin,
              options: skins,
              onChange: function (value) { props.setAttributes({ skin: value }); },
            }),
            el(RangeControl, {
              label: 'Maximum rijen (0 = alles)',
              value: limit,
              min: 0,
              max: 30,
              onChange: function (value) { props.setAttributes({ limit: value || 0 }); },
            })
          )
        ),
        preview
      );
    },
    save: function () { return null; },
  });
  Object.keys(labels).forEach(function (key) {
    if (!blocks.registerBlockVariation) return;
    blocks.registerBlockVariation('snookerclub/board', {
      name: key,
      title: 'Snooker · ' + labels[key],
      description: hints[key],
      attributes: { view: key, skin: key === 'ingeven' || key === 'app' ? 'club' : 'site' },
      isDefault: key === 'ranking',
      scope: ['inserter'],
    });
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.components,
  window.wp.blockEditor,
  window.wp.serverSideRender
);
