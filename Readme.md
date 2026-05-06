# Mad

Et system til lagerstyring i husstande med opskrifter, indkøbssedler og tilbudsintegration.

## Vision

Gøre det nemt at holde styr på madvarer i hjemmet, minimere madspild og gøre indkøb hurtigere ved at kombinere:

- lagerstatus
- opskrifter
- automatiske indkøbslister
- aktuelle tilbud fra udvalgte butikker

## Produktretning (opdateret)

HMI og backend prioriteres nu i denne raekkefoelge:

1. Madplaner (ugeflow, hvad skal vi spise, hvad mangler vi)
2. Opskrifter (med ingredienser, lokation og lagerkobling)
3. Foedevarer med lokationer (fersk, frost, koeleskab, fryserum, kaelder)
4. Scanning som hurtig inputkanal - ikke hovedoplevelsen

## Teknologistak (forelobig)

- Backend: PHP
- Database: MySQL
- Hardware-integration: ESP32 scanning ind/ud (sekundaer input)
- API-integrationer: Open Food Facts, Frida (naeringsdata), InMobile SMS

## Kernefunktioner

### Lagerstyring

- Scanning ind/ud via ESP32 som hurtig registrering.
- Varedata: navn, maerke, vaegt, billede, stregkode, ernaeringsoplysninger.
- Minimumsbeholdning pr. vare med automatisk oprettelse pa indkoebsseddel.
- Lokationstyper: fersk, frost, koekken, kaelder, fryserum.
- QR label print og one-click flow: `Scan -> Ind -> QR` (senere fase).

### Husstande og adgang

- Multi-tenant model med flere husstande.
- Flere lokationer pr. husstand (fx koekken, kaelder, fryserum).
- Husstande kan ikke se hinandens lagerantal.
- Deling af varekatalog mellem husstande er muligt.

### Opskrifter

- Opskriftssamling med kobling til indkoebsfunktion.
- Upload af PDF-opskrifter.
- Husstande kan vaelge at dele opskrifter med andre husstande.
- Maaltidsplanlaegning koblet direkte til lager og lokationer.

### Indkoebsseddel

- Generering baseret pa lagerbeholdning og mangler.
- Opdeling efter butik og varetype for bedre butiksflow.
- Integration af ugens tilbud i indkoebssedlen.

### Butikstilbud og scraping

- Webscraping af tilbud fra: Kvickly, 365, Netto, Rema 1000 og Lidl.
- Tilbud data bruges aktivt i indkoebsplanlaegning.

### Central administration

- Administration af husstande og brugere.
- Tilfoejelse af brugere til husstande.
- Opsaetning af API-noegler og integrationer.
- Opsaetning/styring af scraping jobs.

### Login og sikkerhed

- Login med tildelte initialer.
- 2FA via SMS.

### UI/UX

- Mobilvenligt og desktop-optimeret design.
- Fokus pa hurtigt flow i hverdagen.

## Foreslaaet MVP (fase 1)

1. Opret husstand og brugere.
2. Login med initialer + SMS 2FA.
3. Opskrifter + madplan (ugevisning) som primaer funktion.
4. Basis lagerstyring med lokationer (fersk/frost) og minimumsbeholdning.
5. Scanning som hurtig opdatering af lager, ikke krav for at bruge systemet.
6. Automatisk indkoebsseddel uden tilbudsintegration.

## Fase 2

- Webscraping af tilbud fra prioriterede butikker.
- Tilbudsintegration i indkoebsseddel.
- QR label automatisering og printflow.
- Deling af opskrifter og varekatalog mellem husstande.

## Aabne afklaringer

- Skal hver husstand have eget abonnement/plan?
- Hvor ofte skal tilbud scraperes (dagligt, flere gange dagligt)?
- Hvilke printformater skal understoettes til QR labels?
- Skal der vaere roller (admin, medlem, gaest) i husstanden?

## Naeste skridt

1. Laas MVP scope endeligt.
2. Beskriv datamodel (husstand, lokation, vare, lagerbevaegelse, opskrift, indkoebspost).
3. Definer API-kontrakter til scanning, lager og indkoebsseddel.
4. Vaelg scraping-strategi og compliance-ramme pr. butik.

## Hurtig opstart (webd.pl)

### 1) Database

Udgangspunkt (allerede oplyst):

- DB navn: `cardinal_mad`
- DB bruger: `cardinal_mad`

Manglende oplysning foer vi kan forbinde:

- DB host (fx localhost eller ekstern host fra webd.pl panel)
- DB port (typisk 3306)

Import af schema:

1. Opret databasen i kontrolpanel hvis den ikke findes.
2. Importer [sql/schema.sql](sql/schema.sql) via phpMyAdmin eller MySQL CLI.

### 2) Miljoekonfiguration

1. Kopier `.env.example` til `.env`.
2. Udfyld `DB_HOST`, `DB_PORT` og `DB_PASS` i `.env`.
3. Behold `.env` uden for versionsstyring (den er ignoreret i [/.gitignore](.gitignore)).

### 3) FTP deploy

Manglende oplysning foer deploy kan automatiseres:

- FTP host
- FTP bruger
- FTP mappe (remote path)

Anbefaling:

- Brug FTPS/SFTP.
- Brug en dedikeret FTP-bruger begraenset til projektmappen.

### 4) Forbindelsestest

Upload projektet til webhotellets webroot (fx `public_html/mad`) og kald:

- [public/index.php](public/index.php)

Ved succes returneres JSON med `status: ok` og database servertid.

## Release-rytme

Projektet bruger nu en fast versionsrytme:

1. Opdater version + changelog via script.
2. Commit + tag oprettes automatisk.
3. Push til GitHub (`main` + tag).

Kommando:

```bash
./scripts/release.sh 0.1.1 "Kort beskrivelse af aendringer"
```

Filer der altid opdateres ved release:

- [VERSION](VERSION)
- [CHANGELOG.md](CHANGELOG.md)