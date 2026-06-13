# MyBlog

*🇬🇧 [English version below](#myblog--english-version) ↓*

**Rychlá plug & play náhrada za 15 let staré redakční systémy [BLOG:CMS](https://en.wikipedia.org/wiki/Nucleus_CMS) a [Nucleus CMS](https://en.wikipedia.org/wiki/Nucleus_CMS).**

MyBlog pracuje **přímo nad původní strukturou tabulek** těchto systémů (`item`, `comment`, `category`, `subcategory`, `blog`, `foto`, `foto_fotka`, `tags`, …) — nemigruje data, jen je zobrazí v moderním, svěžím kabátě a doplní jednoduchou administraci. Stačí nasměrovat na existující databázi a starý obsah (články, komentáře, fotogalerie, tagy) okamžitě ožije s novým designem, dark/light režimem a zachovanými URL.

Postaveno na **čistém PHP 8.5**, bez frameworku, jediná závislost je PHPMailer (přibalená v `vendor/`).

## Nasazené weby

MyBlog v ostrém provozu pohání tři weby běžící z jednoho codebase nad původními databázemi:

- **[MyEgo.cz](https://myego.cz/)** — osobní webzine
- **[MyWindows.cz](https://mywindows.cz/)** — Windows, Microsoft a svět PC
- **[MyAndroid.cz](https://myandroid.cz/)** — svět Androidu, mobilů a aplikací

## Co umí

- **Jeden codebase, více webů** — podle domény (`HTTP_HOST`) si vybere databázi i branding.
- **Zachovaná stará URL** — `/item/{slug}`, `/category/{slug}`, `/group/{slug}`, `/section/{short}`, `/tag/{id}-{slug}`, `/album/{id}`, `/fotka/{id}`, stránkování `/offset/{N}`, RSS `/feed/rss2`; staré odkazy se 301 přesměrují, slugy se dohledají přes `plugin_fancierurl`.
- **Věrné vykreslení starého obsahu** — migrované tagy `%video()%` (YouTube/Vimeo), `%album()%`, `<%image%>`/`<%popup%>`/`<%media%>`, zvýraznění kódu v `<pre>`, textové smajlíky → unicode emoji, `<3` → ❤️, a nastylované legacy CSS třídy z článků (`.box`, `.rightbox`, …).
- **Komentáře** — jen zobrazení (s AJAX donačítáním u dlouhých diskuzí), v adminu mazání.
- **Fotogalerie** — alba a fotky jen pro prohlížení, s lightboxem.
- **Fulltext hledání**, sitemap.xml, robots.txt, Open Graph metadata.
- **Design** — tmavě fialový akcent, přepínač auto/světlý/tmavý, vlastní SVG loga, responsivní.
- **Administrace** — přihlášení chráněné Cloudflare Turnstile + ochranou proti brute-force, obnova hesla e-mailem, přehled a editace článků (TinyMCE 8 + správce souborů), tříúrovňový editor kategorií, mazání komentářů.

## Požadavky

- PHP 8.5+ s rozšířeními `mysqli`, `gd` nebo `imagick`, `curl`, `mbstring`, `openssl`
- MySQL / MariaDB s existující databází Nucleus / BLOG:CMS
- Webový server s přepisem URL — **IIS s URL Rewrite** (přibalený `web.config`) nebo **Apache s mod_rewrite a mod_headers** (přibalený `.htaccess`)

## Zprovoznění

1. **Naklonuj repozitář** do kořene webu.

2. **Vytvoř konfiguraci** ze vzoru a vyplň reálné hodnoty:
   ```bash
   cp cfg.sample.php cfg.php
   ```
   V `cfg.php` nastav v poli `MYBLOG_SITES` pro každou doménu: název databáze, přihlašovací údaje, prefix tabulek (`nucleus_`, …), název webu a barevný akcent (= název SVG loga v `assets/logo/`). Dále vyplň klíče **Cloudflare Turnstile** a **SMTP** pro obnovu hesla. `cfg.php` je v `.gitignore` — necommituj ho.

3. **Loga a OG obrázky** — pro každý web přidej do `assets/`:
   - `assets/logo/{accent}.svg` a `assets/logo/{accent}.ico` (favicon)
   - `assets/og/{accent}.png` (náhledový obrázek 1200×630 pro sdílení)

4. **Obrázková data** — obsah původního webu nakopíruj fyzicky do `images/{doména}/`:
   ```
   images/{doména}/media/   původní /media (fotogalerie atd.)
   images/{doména}/img/     původní /img (obrázky v článcích)
   images/{doména}/tmp/     náhledy správce souborů (zapisovatelný)
   ```
   Adresář `images/` je v `.gitignore` (jsou to data, ne kód).

5. **Spusť instalátor** — založí tabulku administrátorů, ověří fulltextový index a zkontroluje adresáře:
   ```bash
   php install/setup.php
   ```
   Vytvoří se účet `admin@example.com` (dle `ADMIN_EMAIL`) s heslem **`CHANGEME`** — po prvním přihlášení ho ihned změň.

6. **Nasměruj web server** na kořen projektu (na IIS funguje přibalený `web.config` rovnou). Adresář `log/` musí být zapisovatelný.

7. **Hotovo** — otevři web. Administrace je na `/admin/`.

### Lokální vývoj bez IIS

Pro rychlé otestování přes vestavěný PHP server (simuluje přepisovací pravidla):
```bash
MYBLOG_HOST=dev.example.com php -S 127.0.0.1:8000 install/dev-router.php
```

## Bezpečnostní doporučení pro produkci

- Použij databázový účet **jen s právy na danou databázi** (ne `root`).
- Po nasazení **změň výchozí heslo** administrátora.
- Ověř, že `cfg.php`, `log/` a `.git/` nejsou přístupné z webu (řeší přibalený `web.config`).

## Licence

Vyvíjí [MyWebdesign.cz s.r.o.](https://mywebdesign.cz/)

---

# MyBlog — English version

**A fast, plug & play replacement for the 15-year-old [BLOG:CMS](https://en.wikipedia.org/wiki/Nucleus_CMS) and [Nucleus CMS](https://en.wikipedia.org/wiki/Nucleus_CMS) publishing systems.**

MyBlog works **directly on top of the original table structure** of those systems (`item`, `comment`, `category`, `subcategory`, `blog`, `foto`, `foto_fotka`, `tags`, …) — it doesn't migrate any data, it just renders it in a modern, fresh skin and adds a simple admin panel. Point it at your existing database and the old content (articles, comments, photo galleries, tags) instantly comes back to life with a new design, dark/light mode and all original URLs preserved.

Built on **plain PHP 8.5**, no framework; the only dependency is PHPMailer (bundled in `vendor/`).

## Live sites

MyBlog powers three production sites running from a single codebase on top of their original databases:

- **[MyEgo.cz](https://myego.cz/)** — personal webzine
- **[MyWindows.cz](https://mywindows.cz/)** — Windows, Microsoft and the PC world
- **[MyAndroid.cz](https://myandroid.cz/)** — the world of Android, phones and apps

## Features

- **One codebase, multiple sites** — picks the database and branding by domain (`HTTP_HOST`).
- **Original URLs preserved** — `/item/{slug}`, `/category/{slug}`, `/group/{slug}`, `/section/{short}`, `/tag/{id}-{slug}`, `/album/{id}`, `/fotka/{id}`, pagination `/offset/{N}`, RSS `/feed/rss2`; old links are 301-redirected and slugs are resolved via `plugin_fancierurl`.
- **Faithful rendering of legacy content** — migrated tags `%video()%` (YouTube/Vimeo), `%album()%`, `<%image%>`/`<%popup%>`/`<%media%>`, code highlighting in `<pre>`, text smileys → unicode emoji, `<3` → ❤️, and styled legacy CSS classes from articles (`.box`, `.rightbox`, …).
- **Comments** — read-only display (with AJAX lazy-loading for long threads), deletable from the admin.
- **Photo galleries** — albums and photos for viewing only, with a lightbox.
- **Full-text search**, sitemap.xml, robots.txt, Open Graph metadata.
- **Design** — dark-purple accent, auto/light/dark switch, custom SVG logos, responsive.
- **Admin** — login protected by Cloudflare Turnstile + brute-force protection, e-mail password recovery, article overview and editing (TinyMCE 8 + file manager), three-level category editor, comment deletion.

## Requirements

- PHP 8.5+ with `mysqli`, `gd` or `imagick`, `curl`, `mbstring`, `openssl`
- MySQL / MariaDB with an existing Nucleus / BLOG:CMS database
- A web server with URL rewriting — **IIS with URL Rewrite** (bundled `web.config`) or **Apache with mod_rewrite and mod_headers** (bundled `.htaccess`)

## Getting started

1. **Clone the repository** into your web root.

2. **Create the configuration** from the sample and fill in real values:
   ```bash
   cp cfg.sample.php cfg.php
   ```
   In `cfg.php`, set up `MYBLOG_SITES` for each domain: database name, credentials, table prefix (`nucleus_`, …), site name and color accent (= the SVG logo name in `assets/logo/`). Then fill in the **Cloudflare Turnstile** and **SMTP** settings for password recovery. `cfg.php` is in `.gitignore` — never commit it.

3. **Logos and OG images** — for each site add to `assets/`:
   - `assets/logo/{accent}.svg` and `assets/logo/{accent}.ico` (favicon)
   - `assets/og/{accent}.png` (1200×630 social preview image)

4. **Image data** — copy the original site content physically into `images/{domain}/`:
   ```
   images/{domain}/media/   original /media (galleries, etc.)
   images/{domain}/img/     original /img (in-article images)
   images/{domain}/tmp/     file-manager thumbnails (must be writable)
   ```
   The `images/` directory is in `.gitignore` (it's data, not code).

5. **Run the installer** — creates the admin table, verifies the full-text index and checks directories:
   ```bash
   php install/setup.php
   ```
   An account `admin@example.com` (per `ADMIN_EMAIL`) is created with the password **`CHANGEME`** — change it immediately after the first login.

6. **Point your web server** at the project root (on IIS the bundled `web.config` works out of the box; on Apache the bundled `.htaccess`). The `log/` directory must be writable.

7. **Done** — open the site. The admin panel is at `/admin/`.

### Local development without IIS

Quick test via the built-in PHP server (it simulates the rewrite rules):
```bash
MYBLOG_HOST=dev.example.com php -S 127.0.0.1:8000 install/dev-router.php
```

## Production security recommendations

- Use a database account **scoped to that database only** (not `root`).
- **Change the default admin password** after deployment.
- Make sure `cfg.php`, `log/` and `.git/` are not reachable from the web (handled by the bundled `web.config` / `.htaccess`).

## License

Developed by [MyWebdesign.cz s.r.o.](https://mywebdesign.cz/)
