# Branding — website + Sanctum Share (Android)

Default skin is **SanctumOS**. Self-hosters (or forks) can rebrand the **web UI** and the **Android share app** without touching upload logic.

Canonical color/name map: [`branding/tokens.json`](../branding/tokens.json).

---

## Quick map

| Surface | What to replace |
|---------|-----------------|
| Website CSS + chrome | `public/branding/brand.css` |
| Website logo / favicon | `public/branding/logo.svg` |
| Website product name / footer | `public/branding/site.json` |
| Android colors | `android/app/src/main/res/values/colors.xml` |
| Android display strings | `android/app/src/main/res/values/strings.xml` |
| Android mark (in-app) | `android/app/src/main/res/drawable/ic_sanctum_mark.xml` |
| Android launcher icon | optional `mipmap-*` / adaptive icons (see Android doc) |

After web changes: redeploy `public/` (multihost `sync.sh` / `deploy.sh`).  
After Android changes: rebuild the APK (`android/tools/assemble-debug-on-termux.sh` or Android Studio).

---

## Website

### Files (served from docroot)

| File | Role |
|------|------|
| `/branding/brand.css` | All layout + `:root` CSS variables (`--accent`, `--bg`, …) |
| `/branding/logo.svg` | Header mark + favicon |
| `/branding/site.json` | `web_name`, `web_subtitle`, footer org link, `logo_text` fallback |

`public/includes/views.php` loads these at request time. Keep **`:root` variable names** stable if you only recolor — classes (`.btn`, `.card`, `.brand`) are shared by upload/result/docs pages.

### Swap procedure (web)

1. Edit `public/branding/site.json` — set your product name and org URL.
2. Replace `public/branding/logo.svg` (or PNG; then set `"logo_src": "/branding/logo.png"` and drop the file next to it).
3. Edit `public/branding/brand.css` — change `:root` colors / gradients / fonts.
4. Optionally mirror hex values into `branding/tokens.json` so Android stays in sync.
5. Deploy `public/` to the vhost.

If `logo.svg` is missing, the UI falls back to a letter mark using `logo_text` from `site.json`.

### What not to change for branding

- `public/api/v1/upload.php` contract
- Storage / SQLite paths
- JSON field names (`direct_url`, `url`, …)

---

## Android (Sanctum Share)

App lives in **`android/`** in this repo (package `org.sanctumos.share`). Default endpoint remains `https://tmp.sanctumos.org`; users can point Settings at any Sanctum Temp base URL.

### Swap procedure (app)

1. **Name / copy** — edit `android/app/src/main/res/values/strings.xml`  
   - `app_name`, `share_label`, `home_blurb`, settings strings.
2. **Colors** — edit `android/app/src/main/res/values/colors.xml` to match `branding/tokens.json` (or your fork’s palette).
3. **In-app mark** — replace `android/app/src/main/res/drawable/ic_sanctum_mark.xml` (vector) or add a bitmap under `drawable-*/`.
4. **Launcher icon** — add adaptive icons under `mipmap-anydpi-v26` + density buckets; point `android:icon` / `roundIcon` in `AndroidManifest.xml` at `@mipmap/ic_launcher`.
5. Rebuild: see [`docs/ANDROID.md`](ANDROID.md).

Default base URL for uploads is **not** a brand asset; change `Prefs.DEFAULT_BASE` only if your fork should ship pointed at *your* host.

---

## Keeping web + app aligned

Recommended workflow when you rebrand:

1. Update `branding/tokens.json` (names + hex).
2. Copy hex into `public/branding/brand.css` `:root` and `android/.../colors.xml`.
3. Replace both logos (SVG web + Android vector/mipmap).
4. Update `site.json` + `strings.xml` product names together.

---

## Sanity checks

**Web:** open `/` — header shows new name/logo; `/docs/branding/` renders this doc.  
**App:** install debug APK — home shows brand mark + name; share sheet label matches `share_label`.
