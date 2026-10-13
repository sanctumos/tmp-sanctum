# Branding — website + Sanctum Share (Android)

Default skin is **SanctumOS** (slate / orange retro-tech, matching [sanctumos.org](https://sanctumos.org/)). Self-hosters (or forks) can rebrand the **web UI** and the **Android share app** without touching upload logic.

Canonical color/name map: [`branding/tokens.json`](../branding/tokens.json).

---

## Quick map

| Surface | What to replace |
|---------|-----------------|
| Website CSS + chrome | `public/branding/brand.css` |
| Website logo / favicon | `public/branding/logo.svg` (optional mirror: `public/assets/sanctum-logo.svg`) |
| Website product name / footer | `public/branding/site.json` |
| Android colors | `android/app/src/main/res/values/colors.xml` |
| Android display strings | `android/app/src/main/res/values/strings.xml` |
| Android mark (in-app) | `android/app/src/main/res/drawable*/ic_sanctum_logo.png` (real SanctumOS mark; density buckets) |
| Android launcher icon | `mipmap-*/ic_launcher*.png` + `mipmap-anydpi-v26/ic_launcher.xml` (adaptive, slate `#3C6E8F` background) |
| Android simplified vector (fallback) | `android/app/src/main/res/drawable/ic_sanctum_mark.xml` (not used by UI when logo PNG is present) |

After web changes: redeploy `public/` (multihost `sync.sh` / `deploy.sh`).  
After Android changes: rebuild the APK (`android/tools/assemble-debug-on-termux.sh` or Android Studio), then publish to the site with `android/tools/publish-apk-to-public.sh` (updates `public/downloads/` + checksums for `/app/`). See [`ANDROID.md`](ANDROID.md) → *Publish a new APK*.

---

## Website

### Files (served from docroot)

| File | Role |
|------|------|
| `/branding/brand.css` | All layout + `:root` CSS variables (`--retro-primary`, `--logo-accent`, `--bg`, …) |
| `/branding/logo.svg` | Header mark + favicon (default SanctumOS mark) |
| `/branding/site.json` | `web_name`, `web_subtitle`, footer org link, `logo_src`, `logo_text` fallback |
| `/assets/sanctum-logo.svg` | Same mark kept under assets for historical / alternate `logo_src` |

`public/includes/views.php` loads `brand.css` + `site.json` at request time. Keep **`:root` variable names** stable if you only recolor — classes (`.btn`, `.card`, `.site-bar`, `.brand`) are shared by upload/result/docs pages.

### Swap procedure (web)

1. Edit `public/branding/site.json` — set your product name, subtitle, and org URL.
2. Replace `public/branding/logo.svg` (or PNG; then set `"logo_src": "/branding/logo.png"` and drop the file next to it).  
   - You can also set `"logo_src": "/assets/your-logo.svg"` if you prefer the assets path.
3. Edit `public/branding/brand.css` — change `:root` colors / gradients / fonts.  
   - Default SanctumOS tokens: `--retro-primary` `#3C6E8F`, `--logo-accent` `#e16e0a`, light `--bg` `#F9F9F9`.
4. Mirror hex values into `branding/tokens.json` so Android stays in sync.
5. Deploy `public/` to the vhost (brand pack is under `public/branding/`, so normal sync picks it up).

If the file at `logo_src` is missing, the UI falls back to a letter mark using `logo_text` from `site.json`.

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
   - Resource names stay (`sanctum_bg`, `sanctum_primary`, …); only the hex values change.
3. **In-app mark** — replace `drawable*/ic_sanctum_logo.png` (mdpi→xxxhdpi) with your logo; layouts use `@drawable/ic_sanctum_logo` in the slate site-bar header.
4. **Launcher icon** — replace `mipmap-*/ic_launcher_foreground.png` (padded for adaptive safe zone) and legacy `ic_launcher.png` / `ic_launcher_round.png`; keep `ic_launcher_background` in `colors.xml` matching your brand slate.
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

**Web:** open `/` — slate header bar, orange accent, new name/logo; `/docs/branding/` renders this doc.  
**App:** install debug APK — home shows brand mark + name; share sheet label matches `share_label`.
