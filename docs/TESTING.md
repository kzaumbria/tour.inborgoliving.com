# InBorgo staging and field acceptance

Run against an HTTPS staging deployment with a separate database and demo content. Run the three PHP scripts in README first. Import `SQL/schema.sql` then `SQL/seed.sql`; create an administrator before `server-check.php`. `SQL/verify.sql` is optional read-only verification.

## Execution record

| Check | Development result |
| --- | --- |
| PHP source syntax, including server test scripts | Checked using the available PHP 7.3 CLI; production target remains PHP 8.x |
| Deterministic proximity/GPS/progress tests | 11 passing in Node 20 before the server-only test decision |
| PHP 8 + MySQL schema/import/admin integration | Pending on your server |
| Desktop Chrome UI/PWA | Pending on your server |
| Android Chrome physical GPS/audio | Pending on device |
| iOS Safari physical GPS/audio/installation | Pending on device |

Do not infer a browser pass from the JavaScript unit tests. Record browser/OS versions, date, device, tester, expected and actual behavior below or in your issue tracker.

## Staff and data tests

1. Sign in with a CLI-created account. An invalid password shows a generic failure. Eleven failed attempts for the same account within 15 minutes prevent login; wait or remove that test account's `login_attempts` row from staging to recover.
2. Sign out. Direct `/admin/?entity=locations` returns sign-in, not records. POST a form without its CSRF token: expect HTTP 403. Cookies must be Secure, HttpOnly, SameSite=Strict in HTTPS developer tools. `/admin/` must say `Cache-Control: no-store`.
3. Create a location with English and Italian content. Pick coordinates by clicking and dragging. Use Current Location, then deny permission and ensure coordinate fields/map still work. Save, reopen, and verify fields and translations.
4. Reject out-of-range latitude/longitude, invalid slugs, duplicate slugs, a radius below 5 or above 500, invalid closed weekdays, and external/script image paths. Ensure invalid forms keep entered values. Set a valid radius of 20, then 75.
5. Use literal `<script>alert(1)</script>` as staging story text. It must display as text in staff/public views, never execute. Remove test content afterward.
6. Upload a JPEG, PNG/WebP and MP3 with a language; check photo and audio. Attempt a `.php`, SVG, text renamed `.mp3`, and a file over 25 MB. Reject all invalid files. An image above 24 megapixels is rejected. Language-less audio is rejected. No uploaded file is executable.
7. Copy an image path into Featured image and a route cover. Remove a media reference and confirm existing covers still load: physical file cleanup is deliberately manual.
8. Create two routes and add the same location to both. Edit its title/coordinates once; both routes must show the edit after refresh. An inactive location is omitted from both public routes. Reactivate it.
9. Add several stops. Drag reorder and use up/down buttons with the keyboard. Marker numbers and polyline order update. Save/reopen; ordering and stop IDs must persist. Try duplicating a location in one route: reject. Add a radius override and distinct translated departure instructions on each route.
10. Deleting a referenced location fails safely. Deleting a disposable route removes only that route/stops. Deactivate a route: it disappears from the online public route list and its API is unavailable.
11. Add a third language, enter content, and view it publicly. Select it as default, then deactivate the old default. The only active default cannot be removed/deactivated without replacement. Missing content falls back visibly; additional-language interface labels fall back to English.
12. Mark a location closed for today's **Rome** weekday and guided-only; confirm both notices. Opening-hour text is display-only, not an automatic schedule.

## Public flow and location

| Scenario | Expected result |
| --- | --- |
| First visit | English selected by default, English/Italian selector, three demo route cards, correct stop counts and DEMO notice on route |
| Start button | Onboarding explains location/audio; browser permission is requested only after interaction |
| GPS granted | Position dot/accuracy circle, stop numbers, schematic line and next-stop distance |
| GPS denied / unavailable / timeout | Clear manual-fallback status; every stop still opens from Stops |
| Poor accuracy | Status or accuracy-dependent prevention; no arrival from an unreliable fix |
| Implausible jump | Ignored reading; no spurious arrival |
| Inside radius | Two good fixes separated by at least 1.5 seconds produce one arrival |
| Outside radius | No arrival; small boundary motion does not continuously retrigger |
| Overlapping zones | Expected stop first; otherwise nearest eligible; one panel at a time |
| Panel closed | Same automatically triggered stop does not reopen; manually reopen freely |
| Mark visited | Green marker/list state and next unvisited stop update |
| Manual access | All location text/images/narration available without GPS or starting the tour |
| Language switch | Route/stop/story/audio language changes; visited/current position remain intact |
| Refresh | Progress/language survive; user can explicitly resume GPS or browse manually |
| Reset | Triggered/visited states cleared, position marker cleared, onboarding shown |
| Back/forward navigation | Restored page offers GPS resume rather than falsely claiming active tracking |
| Simulation absent | No debug panel or fake coordinates for ordinary visitors |

## Simulation

Open `/route/historic-village?debug=1`. **Move Here** emits fixes every two seconds and should trigger after the second good fix. Use Previous/Next, manual coordinates, and a deliberately poor accuracy (100 m). The panel must show simulation status, position, accuracy, route, expected stop, radius, distance, nearest, triggered and visited states.

Force Arrival bypasses GPS eligibility for UI/audio testing but cannot retrigger an already triggered stop; reset first. Use Real GPS stops simulation. Open the ordinary URL in another tab: simulation progress must not appear there. For close-stop testing, configure two nearby staging points through admin, not the production dataset.

## Narration

- With a selected-language MP3, Play uses native audio; verify seeking/progress, Pause, Resume, Stop and replay.
- Without MP3, Play uses narration text and an available matching speech voice. Italian voice is used for Italian fallback content even if the interface language is different.
- Make a staging MP3 path unavailable: speech fallback or visible manual/text fallback, no overlapping playback.
- Start a story, close its panel, open another, switch language, or reset: old narration stops.
- Turn on auto narration in onboarding. Arrival attempts playback; when blocked, a visible Play button remains. Test again with autoplay off.
- A browser without speech synthesis shows readable content and an explanatory message.
- Test a long narration and background/foreground transitions on actual iOS and Android. Resume support varies by system voice; replay and reading remain the fallback.

## Offline / install

1. Load a route online and click Prepare tour offline. Verify progress reaches completion and the ready message appears. Repeat in the other language if both are needed.
2. Inspect Application → Service Workers / Cache Storage in Chrome. Only application/public content/media should be stored. `/admin/` and `tile.openstreetmap.org` must not be in application caches.
3. Go offline with DevTools and, separately, enable airplane mode on a real phone. Refresh the **prepared route URL**. Verify shell, selected-language data, stops, stories, photos and downloaded MP3. TTS can depend on installed device voices.
4. The map base may be blank; marker/line display and manual content still work. GPS should work where the device can obtain an offline fix. Do not require a GPS fix inside a building to call offline content successful.
5. Visit an unprepared language/route while offline. Show a load error and keep existing tour state, never silently reset progress or claim content readiness.
6. Interrupt preparation halfway; expect an error, not a success status. Reconnect and retry. Simulate storage quota refusal where possible; no ready status on incomplete downloads.
7. Clear site storage. Progress/offline downloads are expected to disappear. Explain this to staff.
8. Install from Chrome's browser menu. On iOS Safari use Share → Add to Home Screen. Test launch and prepared route navigation; installation prompts are browser-specific.
9. Update the service worker shell version on staging, close all old tabs, reopen online, and verify the new application version while retaining compatible content/progress.

## Responsive and accessibility

Check desktop Chrome at 1280 px and mobile portrait at 375/390 px, then actual Android Chrome and iOS Safari. No horizontal scrolling; visible focus; touch targets at least 44 px; large readable next-stop and Play controls. Keyboard-test language selection, tabs, stop list, arrival dialog close/Escape and staff reorder arrows. Confirm dialog focus returns to the initiating control. Verify headings, image alternatives, contrast outdoors and visible status messages. Read every story without audio. Screen-reader announcements should not flood every GPS update.

## Production field checklist

Replace all six demo coordinate sets, walking distances, route durations, placeholder illustration/media, and unverified text. Check real walking access, stairs, closures and safety restrictions. Set per-location/route radii from physical tests in the village, including adjacent attractions, narrow streets and slow approach. Obtain approved Italian/English narration and MP3 rights. Turn off DEMO only after verification. Retest with weak connectivity, headphones, denied GPS and refresh mid-walk. Back up database and uploads before opening to visitors.
