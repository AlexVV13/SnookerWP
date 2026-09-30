# Snookerclub (`/webhost/snooker`)

Clubapp voor **SC De Merodesnookers** (Turnhout): uitslagen, ranking, rapporten. De clubnaam is overal aanpasbaar.

**Installatie:** [docs/INSTALL.md](docs/INSTALL.md) · **Regels:** [docs/REGELS.md](docs/REGELS.md) · **WordPress:** [wordpress/README.md](wordpress/README.md)

Huidige versie: **1.0.2**. Datums volgen `Europe/Amsterdam`. Seizoenen lopen augustus–juli.

## Starten

```bash
docker compose up -d --build
```

De container luistert alleen op `127.0.0.1:9091`. De hoofdsite proxyt `/webhost/snooker` daarheen, net als `/webtools`.

Zonder Docker:

```bash
npm install
HOST=127.0.0.1 PORT=9091 WEBHOST_BASE_PATH=/webhost/snooker npm start
```

## Deploy

De image is bedoeld als gewone productiecontainer:

- non-root (`node`)
- OCI-labels (`org.opencontainers.image.version`)
- `HEALTHCHECK` + Compose healthcheck
- `/health` en `/readyz`
- nette stop op `SIGTERM`
- read-only rootfs, data alleen in volume `snooker-data`
- logrotatie, geheugenlimiet, `no-new-privileges`

Build-args:

```bash
APP_VERSION=1.0.2 APP_REVISION=$(git rev-parse --short HEAD) BUILD_DATE=$(date -u +%Y-%m-%dT%H:%M:%SZ) docker compose up -d --build
```

## Paden

- `/webhost/snooker` — guest app (clubavond + tab Nieuwe wedstrijd)
- `/webhost/snooker/#wedstrijd` — springt naar de invoerwizard
- WordPress: `/snooker/ingeven` of shortcode `[snookerclub_ingeven]` — dezelfde wizard, data op de WP-host
- `/webhost/snooker/admin` — clubbeheer (na login op `/webhost`), inclusief papieruitslagen, dossier en CSV zoals het oude Excel-blad
- `/webhost/snooker/live` — liveblok uitslagen + top 3
- `/webhost/snooker/live/players` — liveblok spelerslijst
- `/webhost/snooker/live/agenda` — maandagenda voor Elementor
- `/webhost/snooker/embed.js` — `<div data-snooker-live>`, `<div data-snooker-players>`, `<div data-snooker-agenda>`, `<div data-snooker-rapport>` of `<div data-snooker-dossier>`
- `/webhost/snooker/health` — liveness + versie
- `/webhost/snooker/readyz` — readiness (kan data lezen)

Publieke GET-API’s sturen CORS zodat het liveblok op een externe site mag laden.

Data staat in volume `snooker-data` (`club.json`).

## WordPress-plugin

Zelfde app als installeerbare plugin, met automatische update-checks:

```bash
npm run package:wp
```

Dat schrijft `public/plugin/snookerclub.zip`. Na herstart van de app is die te downloaden op `/webhost/snooker/plugin/snookerclub.zip`. Docker bouwt het bestand mee in de image. Zie `wordpress/README.md`.
