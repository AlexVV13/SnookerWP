# Installatie — Snookerclub

Deze map (`webhost/snooker`) is de volledige clubapp. Twee manieren: **WordPress-plugin** (aanbevolen voor sc-de-merode.be) of **Node** achter `/webhost/snooker`.

Standaardclub: **SC De Merodesnookers**, Biljart Palace, Merodecenter 19, 2300 Turnhout. Clubavond donderdag vanaf 19u. De naam pas je daarna overal aan.

## 1. WordPress-plugin (schone map)

De plugin staat in één map: `wordpress/snookerclub/`.

### Zip bouwen

```bash
cd webhost/snooker
npm run package:wp
```

Dat maakt `public/plugin/snookerclub.zip` (PHP + `public/` CSS/JS).

### Installeren

1. WordPress → **Plugins → Nieuwe plugin → Uploaden** → `snookerclub.zip`.
2. Activeer **Snookerclub**.
3. Bij een eerdere versie: eerst deactiveren en verwijderen, daarna zip **1.0.4** uploaden.
4. Open **Snookerclub** in het dashboard.
5. Vul **Clubgegevens** in: clubnaam, locatie, clubavond, tornooien. Opslaan.
6. Zet blokken of shortcodes op pagina’s (Patronen → Snookerclub).

Kopieer niet alleen de PHP-bestanden. Zonder `public/` ontbreekt de vormgeving.

### Alternatief zonder zip

1. Kopieer `wordpress/snookerclub` naar `wp-content/plugins/snookerclub`.
2. Kopieer ook de map `public/` naar `wp-content/plugins/snookerclub/public/` (CSS/JS/HTML).
3. Activeer de plugin.

Zonder die `public/`-map mist de vormgeving. De zip doet beide stappen in één keer.

### Eerste clubnaam

Nieuw: **SC De Merodesnookers**. Andere club? **Snookerclub → Clubgegevens**, of Customizer → Snookerclub, of Clubbeheer → Clubnaam en kleuren.

## 2. Node-app

```bash
cd webhost/snooker
cp .env.example .env   # optioneel, zie de variabelen in dat bestand
npm install
HOST=127.0.0.1 PORT=9091 WEBHOST_BASE_PATH=/webhost/snooker npm start
```

Of:

```bash
docker compose up -d --build
```

Image `webhost-snooker:1.0.4` luistert op `127.0.0.1:9091`. Data in volume / `data/club.json`. Maak een backup van `club.json` of van de WordPress-optie `snookerclub_state`.

- `/webhost/snooker` — club + uitslag invoeren  
- `/webhost/snooker/admin` — beheer (na portal-login)  
- Clubnaam: **Clubbeheer → Clubnaam en kleuren**

## Na installatie

1. Spelers toevoegen (Clubbeheer).
2. Eventueel donderdag als clubavond in de agenda.
3. Eerste uitslag via **Uitslag invoeren** of papier/Excel.
4. Rapporten: **Snookerclub → Rapporten** (afdrukken = alleen het blad).

Regels: zie [REGELS.md](./REGELS.md).
