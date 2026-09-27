# Snookerclub WordPress-plugin

Ranking, uitslagen, agenda en invoer als invoegbare templates. Geen Node-app en geen externe clubsite nodig.

## Installeren

1. Bouw de zip:

   ```bash
   node webhost/snooker/scripts/package-wp-plugin.js
   ```

2. In WordPress: **Plugins → Nieuwe plugin → Uploaden** en `snookerclub.zip` kiezen.
3. Activeer **Snookerclub**. Bij een eerdere installatie: deactiveren, verwijderen, zip 1.9.0 uploaden.
4. Beheer: **Snookerclub** in het dashboard. Vul eerst **Clubgegevens** (clubnaam). Zet templates op eigen pagina’s via Gutenberg.

Uitgebreide stappen: [../docs/INSTALL.md](../docs/INSTALL.md).

De zip bevat HTML/CSS/JS. Kopieer niet alleen de PHP-map.

## Templates op de site

In de editor: **Patronen → Snookerclub**, of het blok **Snookerclub**. Elk onderdeel mag op een andere pagina.

| Shortcode / patroon | Inhoud |
| --- | --- |
| `[snookerclub_ranking]` | Live ranking (podium + tabel) |
| `[snookerclub_results]` | Recente uitslagen |
| `[snookerclub_live]` | Live overzicht |
| `[snookerclub_agenda]` | Maandagenda |
| `[snookerclub_kpis]` | Clubcijfers |
| `[snookerclub_break]` | Hoogste break |
| `[snookerclub_next]` | Volgende clubavond |
| `[snookerclub_rapport]` | Afdrukbare ranglijst (Excel-kolommen) |
| `[snookerclub_dossier player="Naam"]` | Spelersdossier |
| `[snookerclub_h2h a="A" b="B"]` | Head-to-head |
| `[snookerclub_ingeven]` | Wedstrijd invoeren |
| `[snookerclub]` | Volledige club-app |

Vormgeving: `skin="site"` volgt het WordPress-thema, `skin="club"` het clubdashboard. Voorbeeld: `[snookerclub_ranking skin="site"]`.

Dashboard: **Snookerclub → Rapporten** voor seizoenfilter, afdruk en CSV. Papieruitslagen en het Excel-dossier staan ook onder **Clubbeheer → Papier / Excel**. Seizoen loopt augustus–juli. Extra query’s: `?season=2016-2017&player=Naam`. Frames, winst en verlies tellen uit alle uitslagen (papier én getekende partijen). Gem. punten gebruikt alleen frames met echte scores; papier-1-0 verdunt dat gemiddelde niet. Afdrukken via de knop print alleen het rapportblad, niet de hele WordPress-pagina.

## Vormgeving

Clubnaam: **Snookerclub → Clubgegevens**, of Customizer → Snookerclub. Standaard SC De Merodesnookers (Turnhout). Thema’s: Baize, Nacht, Ivoor, Club, Ruby. `skin="site"` volgt het WordPress-thema. Versie 1.9.0.

## Updates

Standaard geen externe updatefeed. Optioneel een JSON-URL invullen met `version` en `package`.
