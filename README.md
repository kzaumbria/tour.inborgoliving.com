# InBorgo — Phase 1 village audio tours

A plain PHP/PDO/MySQL application with a mobile-first public tour, staff administration, Leaflet maps, browser GPS, MP3/speech narration, local progress, simulation, and prepared offline content. No framework, Node backend, billing API, analytics, visitor GPS upload, or LLM integration.

**All supplied coordinates, stories, opening notices, route distances and durations are DEMO data, not verified directions or historical claims.** Replace and field-test them before publishing. The village illustration is an original placeholder drawing.

## Requirements

- PHP **8.x** (8.2+ recommended) with `pdo_mysql`, `fileinfo`, `session`, and JSON support. Optional `curl` for the HTTP test script.
- MySQL **8.0.16+** or MariaDB **10.6+**, InnoDB and utf8mb4.
- Apache 2.4 with `mod_rewrite`, or Nginx with PHP-FPM.
- HTTPS in production. `http://localhost` / `http://127.0.0.1` work for desktop development. A phone visiting an HTTP LAN address is **not** a secure context.
- A dedicated domain/subdomain with **`public/` as its document root**. Root-relative URLs intentionally assume installation at `/`, not a subdirectory.
- Modern Chrome, Android Chrome, or iOS Safari supporting ES modules and `<dialog>`. Text/manual browsing remain available when GPS, TTS or service workers are unavailable.

No Composer or npm install is required to run the application. Leaflet 1.9.4 and its BSD license are included under `public/assets/vendor/`.

## Install on your server

1. Upload the project to a private application directory, for example `/var/www/inborgo`. Point your domain document root to `/var/www/inborgo/public`. Keep `src/`, `SQL/`, `bin/`, `tests/`, and `config.php` outside the web root.
2. Create a database and an application user using your hosting control panel (or the example SQL below). Set its character set to `utf8mb4`.
3. Import **`SQL/schema.sql` first**, then **`SQL/seed.sql`**. The seed provides Italian and English, six physical locations, and three routes that reuse the locations. These imports are for an empty database; do not rerun them against an installed application.

   ```sh
   mysql -u YOUR_DB_USER -p YOUR_DB_NAME < SQL/schema.sql
   mysql -u YOUR_DB_USER -p YOUR_DB_NAME < SQL/seed.sql
   ```

   In phpMyAdmin: select the database, choose **Import**, import `schema.sql`, then repeat with `seed.sql`. Do not concatenate them with `verify.sql`.

   Example database provisioning, run as a database administrator and replace the password:

   ```sql
   CREATE DATABASE inborgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'inborgo'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_STRONG_PASSWORD';
   GRANT SELECT, INSERT, UPDATE, DELETE ON inborgo.* TO 'inborgo'@'localhost';
   ```

   Import the schema using your migration/hosting account with CREATE/ALTER permissions; the runtime application user only needs the four data privileges above. Hosting providers may assign database/user prefixes.

4. Copy the configuration and edit it on the server:

   ```sh
   cp config.example.php config.php
   ```

   Set `dsn`, `username`, and `password` to the hosting credentials. A typical DSN is `mysql:host=localhost;dbname=inborgo;charset=utf8mb4`. Some hosts require a different hostname or `port=3306`. Keep `secure_cookies => true` on HTTPS. `config.php` is ignored by Git. Never place it inside `public/`.
5. Allow the PHP web-server user to write to **`public/uploads/` only**. For example, where PHP runs as `www-data`:

   ```sh
   sudo chown www-data:www-data public/uploads
   sudo chmod 750 public/uploads
   ```

   Adapt ownership/group permissions to your hosting setup. Do not use `chmod 777`. PHP also needs a writable session directory, normally provided by the host. Recommended PHP settings: `upload_max_filesize=25M`, `post_max_size=32M`, `memory_limit=128M`, `max_input_vars=3000`. The application enforces a 25 MB per-file limit and 24-megapixel image limit. Keep `display_errors=Off` and log errors privately.
6. Create the first administrator using your host's SSH/terminal:

   ```sh
   php bin/create-admin.php staff@example.com
   ```

   Enter a unique password of at least 12 characters when prompted. The prompt reads standard input and **does not hide typing**, so use a private terminal. The password is never a command-line argument. There is no default account and no publicly accessible setup page. If your hosting panel has no terminal, ask the host to run this CLI command; do not publish `bin/` as an installer.
7. Open `https://YOUR_DOMAIN/admin/` and sign in. Open `/` for the public application.
8. Run the server checks below before inviting visitors.

## Test on your server

Run from the **project root**, with the same PHP version used by the website:

```sh
php tests/server-check.php
php tests/database-integration.php
php tests/http-smoke.php https://YOUR_DOMAIN
```

- `server-check.php`: read-only configuration, schema, language/default, content, and file checks. Its upload permission check reflects the CLI user; also test an upload through the website.
- `database-integration.php`: checks shared-location updates across two routes, translated content, radius priority, deletion protection, stable reorder IDs, and inactive stop filtering. It creates temporary fixture rows inside a transaction and rolls them back on success/failure. **Run on staging**; auto-increment counters may advance despite rollback.
- `http-smoke.php`: read-only public routes, JSON responses, admin sign-in, private path protection, and missing-resource checks. PHP curl must be enabled. TLS certificates are verified.
- `SQL/verify.sql`: optional read-only SQL checks for phpMyAdmin. It can be run after schema/seed installation.
- `tests/browser.html`: no Node needed. On **staging only**, temporarily copy this file to `public/browser-tests.html`, open `https://YOUR_DOMAIN/browser-tests.html`, and press Run. It imports your deployed GPS/proximity/tour modules, runs deterministic browser tests, and cleans up its own local test storage. Remove the copied file afterward.
- `docs/TESTING.md`: detailed staff/browser acceptance checklist including uploads, permissions, MP3/TTS, offline, phone testing and language switching.
- Optional developer tests if Node 20+ is available on the test server: `node --test tests/*.test.js`. Node is test tooling only.

The application was syntax-checked with the available PHP 7.3 CLI and the deterministic JavaScript tests were run during development. **PHP 8/MySQL integration, real desktop browser interaction, and physical Android/iOS GPS/audio acceptance still require the server checks and checklist.** No successful database or mobile test is claimed from the development machine.

## English default update for existing installations

English is the default public interface and seed-data language. Admin and debug screens are also English. Italian remains selectable. The updated application starts existing browsers in English once, using a new language-preference key; subsequent language choices are remembered. Tour progress is preserved.

For an existing database, import **`SQL/002_english_default.sql`** through phpMyAdmin (or `mysql -u YOUR_DB_USER -p YOUR_DB_NAME < SQL/002_english_default.sql`). Do not reimport the schema or seed. This update sets English active/default without changing content or language IDs. Fresh installations already use English through the updated seed.

Upload the updated public files, including `sw.js`. Close all open tour tabs and reopen online so the new service worker and English shell can activate. Re-prepare offline tours after updating.

## Staff workflow

1. **Languages:** manage Italian/English or add language codes such as `de`, `fr`, `pt-BR`. There must be one active default language. Set a new default before deactivating the old one. Content languages are database-driven; built-in interface labels are Italian/English, with English UI fallback for additional languages. Extend `i18n.js` for fully translated interface labels.
2. **Locations:** create a physical place once. Set coordinates by entering numbers, clicking/dragging on the map, or using current location. Configure a 5–500 m radius (default 50), notices, ISO closed weekdays (`1,2,3` = Mon–Wed), guided-only status, and language content. The map and seed positions are unverified demo examples.
3. **Photos/audio:** save the location first, then upload JPEG, PNG, WebP, or MP3 under Photos & narration. Choose a language for audio. Copy an uploaded image path into Featured image; route cover images can reuse that path. Captions are image text alternatives. Language-neutral photos appear in all languages. When multiple audio files exist for a language, the first by sort order/ID wins; remove obsolete references when replacing audio.
4. **Routes:** create route details and translations. Save, then add **existing** locations. Drag to reorder or use the keyboard-accessible up/down buttons. Edit each stop's override radius, optional flag, and translated arrival/departure/notes. Save the stop list separately. One location may belong to many routes, but only once within an individual route in Phase 1.
5. Empty translation titles remove that translation. Missing language content falls back to another active translation, preferring the default language. The arrival panel labels a fallback content language; TTS uses that content's language. Audio is used only when assigned to the visitor's selected language.
6. **Deactivate** to unpublish. Deleting a location referenced by a route is blocked by the database. Deleting a route removes its stop associations/translations, not the physical locations. Removing media removes its database reference; files remain on disk so covers and previously prepared content do not break unexpectedly. Clean unreferenced files manually after checking location/route covers and maintaining backups.
7. Opening hours are display text, not a booking/calendar engine. “Closed today” uses the configured weekdays and **Europe/Rome** day. Guided-only and translated opening/accessibility notices are displayed at a stop. Closures inform visitors; they do not hide content or block manual access.

## Visitor and simulation use

- English is selected initially; choose another language and walk at `/` if desired.
- Start requests GPS only after pressing the start button. Auto narration is opt-in; Play is always available. Refresh preserves progress but asks for another start/resume gesture before restarting GPS/audio.
- All stops remain accessible from the Stops tab, including after denied/unavailable location permission.
- Triggered and visited states are distinct: automatic arrival prevents retriggering; Mark visited advances progress. Closing the panel leaves the stop accessible and prevents the same automatic arrival from repeating.
- Add **`?debug=1`**, for example `/route/historic-village?debug=1`. The hidden-by-default debug panel supports stop selection, custom coordinates/accuracy, previous/next movement, forced arrival, and return to real GPS. Move Here emits a simulated fix every two seconds. Bad accuracy intentionally does not trigger. Forced arrival bypasses radius/debounce for UI testing; reset to force the same stop again.
- Simulation uses a **separate local saved session**, never a normal visitor's progress. The query parameter is a developer convenience, not an authentication boundary; it exposes only public tour data and no write API.
- Debug information includes coordinates, accuracy, expected stop, distance, radius, nearest stop, and triggered/visited/nearby IDs.

## GPS and narration behavior

`Proximity` checks the stop override radius first, then the location radius. It requires two eligible fixes at least 1.5 seconds apart and no more than 15 seconds apart. Readings worse than the radius (with a 15 m minimum tolerance and 50 m ceiling) cannot trigger. The GPS layer rejects fixes over 75 m accuracy, stale fixes, non-increasing timestamps, and implausible walking jumps. Nearby state clears after exceeding radius plus the larger of 15 m or 35%; actual arrivals still require a fix inside the real radius.

If zones overlap, priority is: **first untriggered stop in route order**, then nearest eligible untriggered stop, then route order for equal distances. Only one stop activates per update, and an open arrival panel blocks other automatic arrivals. A pause/loss of valid fixes clears or expires debounce state. Small radii and urban GPS remain imperfect: field testing in Italy and manual access are essential.

Narration prefers the selected language's MP3, then uses browser speech with a matching available voice and language code. A failed MP3 load falls back to speech; autoplay permission denial offers a manual Play retry. Play stops previous narration. Pause, Resume, Stop and replay are available; prerecorded tracks also have native progress/seek controls. Text remains readable when voices are missing. Voice selection is isolated in `audio.js` for future preferences. Some mobile browsers may require tapping Play at each stop even after the initial start gesture. Background or locked-screen tracking is not guaranteed.

## Offline / PWA

Use **Prepare tour offline** on WiFi for each desired route/language. The service worker caches the shell and the preparation process downloads route data, stop translations, photos and language audio with file-count progress. It reports readiness only after all files have been cached successfully. Offline readiness verifies that recorded files still exist. Admin pages and all non-GET requests are excluded.

- No OpenStreetMap tiles are prefetched or stored by the service worker. Online Leaflet uses `tile.openstreetmap.org` with visible attribution. Offline, stop markers/lines can be visible on a blank base map; Stops, stories, cached images/audio, GPS and progress remain usable.
- System TTS voices may require a network download. For dependable offline narration, upload MP3s for every supported language/stop.
- Browser quotas, eviction, private browsing and clearing site data can remove content/progress. Preparation requests persistent storage where supported, but cannot guarantee it. Reopen and test in airplane mode before setting off. Failed preparation can leave partial cache files, but does not show a ready status.
- Offline packs remain on the device until site data is cleared. Re-prepare after staff change content/media. Network-first JSON refreshes while online; media prepared for offline remains cached under its unique filename. Removed/unpublished content can remain in an already downloaded tour until the browser connects again or its data is cleared.
- Bump the `SHELL` version in `public/sw.js` after changing application assets; the updated worker takes over after old tabs close. Preserve/content-migrate downloaded data deliberately when changing its cache version.
- An alternative offline tile provider can be added in `map.js`; it must explicitly permit offline downloads.

Policy/reference: https://operations.osmfoundation.org/policies/tiles/ and https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/watchPosition

## HTTPS deployment

See `docs/apache.conf` and `docs/nginx.conf`. They assume root deployment. Apache uses `public/.htaccess` for clean route and API URLs. Nginx needs equivalent rewrites and must deny PHP execution in uploads. HTTPS redirection/certificates belong in your hosting configuration. Enable HTTPS before testing cookies, GPS and service workers; do not treat LAN HTTP as a phone test environment.

On shared hosting, set the subdomain root to this project's `public/`. If your host forces a fixed `public_html`, place the **contents of `public/`** there, and place `src/`, `config.php`, etc. in its parent so the existing relative includes resolve. Keep only this application's files in that layout and verify `/config.php`, `/src/bootstrap.php` and `/SQL/schema.sql` return 403/404. Never expose the whole repository as the document root.

Back up MySQL **and** `public/uploads/`; preserve config privately. Deploy using a staging copy first. The application sets output escaping, CSP, no-sniff, frame restrictions, secure/HttpOnly/SameSite cookies, CSRF checks and prepared queries. Login is throttled per account to 10 attempts per 15-minute window; admins expire after two hours of inactivity. Use hosting-level rate limits for broader abuse protection. Do not put an HTTP cache in front of `/admin/`.

## Local development (optional, when PHP/MySQL are installed)

After importing SQL and configuring your local DB, set `secure_cookies => false` **only for HTTP localhost**, then run:

```sh
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080/` and `/admin/`. The PHP development server is not a production server. You can skip local database setup entirely and do all installation/testing on your HTTPS staging server.

## Important directories

```text
SQL/                         schema.sql, seed.sql, verify.sql
src/                         configuration/PDO/security, content queries, admin helpers
public/index.php             single public application shell
public/admin/index.php       authenticated staff CRUD and uploads
public/api/index.php         read-only JSON routing
public/assets/js/            app, GPS, proximity, map, audio, tour, storage, offline, debug
public/assets/css/            responsive cream/walnut/sienna design
public/assets/vendor/         pinned local Leaflet and license
public/uploads/               generated safe filenames for validated media
public/sw.js, manifest.json   PWA and offline runtime
bin/create-admin.php          CLI-only account creation
config.example.php           private configuration template
tests/                       server checks, browser checks, optional Node tests
docs/                        hosting, architecture, acceptance tests
```

Public API: `GET /api/languages`, `/api/routes?lang=en`, `/api/routes/{slug}?lang=it`, `/api/routes/{slug}/stops?lang=en`, `/api/locations/{id}?lang=en`. Only published routes and active locations/languages are returned. Internal staff notes, user records and credentials are not returned.

## Phase 2 boundary

Location stories and approved content remain attached to physical locations, independently of routes. A future assistant can accept location ID, route ID, language and a question, retrieve approved content server-side, and return text to the existing narration interface. Add verified-source/context tables and a protected server-side provider adapter when that phase is specified. **No LLM keys, provider calls, chat endpoints, or pseudo-assistant exist in Phase 1.**
