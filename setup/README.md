# Jurisdiction Setup

This directory contains the version-controlled configuration for the jurisdiction.

## Why This Exists

Jurisdiction configuration is stored in the Drupal database (Group entity with `field_nuxt_config`).
**If the database is lost, the configuration would be lost too.**

This `setup/` directory solves that problem:
- Configuration is stored as JSON in git
- Can be re-imported after database loss
- Provides documentation of all settings
- Enables reproducible deployments

## Files

```
setup/
├── jurisdiction-config.json   # Source of truth for Nuxt config
├── logos/
│   ├── logo-light.svg        # Logo for light backgrounds
│   ├── logo-dark.svg         # Logo for dark backgrounds
│   ├── favicon.svg           # Browser favicon
│   ├── icon-192.png          # PWA icon 192x192
│   └── icon-512.png          # PWA icon 512x512
├── import-jurisdiction.sh    # Import script
└── README.md                 # This file
```

## Usage

### New Installation

```bash
# From project root:
./setup/import-jurisdiction.sh
```

This updates the existing single `jur` Group when one is present. If no `jur` Group exists yet, it creates one and outputs the Group ID.

### Update Existing Jurisdiction

```bash
# With known Group ID:
./setup/import-jurisdiction.sh GROUP_ID
```

### After Import

1. **Set the jurisdiction ID for the pre-built UI image**. `import-jurisdiction.sh` writes this to `.ddev/.env` automatically:
   ```bash
   cat >> .ddev/.env <<'EOF'
   JURISDICTION_ID=GROUP_ID
   MARKASPOT_UI_BASE_IMAGE=markaspot/markaspot-ui-base:v2.0.0
   EOF
   ```

   If you wire the Nuxt variables directly in Compose, set both values:
   ```yaml
   environment:
     - NUXT_PUBLIC_JURISDICTION_ID=GROUP_ID
     - NUXT_JURISDICTION_ID=GROUP_ID
   ```

2. **Restart DDEV:**
   ```bash
   ddev restart
   ```

3. **Test:**
   ```
   https://yoursite.ddev.site:8040
   ```

## Customizing Configuration

### Edit jurisdiction-config.json

```json
{
  "group": {
    "type": "jur",
    "label": "City Name"           // Display name in Drupal
  },
  "nuxt_config": {
    "client": {
      "name": "Issue Reporter",    // Full name shown in UI
      "shortName": "City"          // Short name for mobile
    },
    "theme": {
      "primary": "#17365b",        // HEX or Tailwind name (blue, red, etc.)
      "secondary": "#586c99",
      "neutral": "#353b42"
    },
    "features": {
      "statistics": true,
      "photoReporting": true,
      "dashboard": false
      // ... see full schema in docs
    },
    "map": {
      "center": { "lat": 50.93, "lng": 6.95 },
      "zoom": 13
    }
  }
}
```

### Theme Colors

Use either:
- **Tailwind names:** `blue`, `red`, `cyan`, `slate`, `zinc`, etc.
- **HEX values:** `#17365b` (generates full color palette automatically)

**Do NOT use custom names** like `"shark"` - they won't work.

### Replace Logos

1. Put new files in `setup/logos/`
2. Run `./setup/import-jurisdiction.sh [GROUP_ID]`

## Workflow

**Golden Rule: Never edit `field_nuxt_config` directly in Drupal admin.**

1. Make changes in `setup/jurisdiction-config.json`
2. Commit to git
3. Run `./setup/import-jurisdiction.sh [GROUP_ID]`
4. Test in frontend

This ensures git is always the source of truth.

## Copying to New Projects

1. Copy the entire `setup/` directory to your new project
2. Edit `jurisdiction-config.json` with your settings
3. Replace logos in `logos/`
4. Run `./setup/import-jurisdiction.sh`
