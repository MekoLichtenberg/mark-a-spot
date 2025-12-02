# Mark-a-Spot

<div align="center">
  <img src="https://www.markaspot.de/assets/images/logo.svg" width="100px" alt="Mark-a-Spot Logo"/>
  <h3>Open-Source Civic Issue Tracking · Drupal 11 · Open311</h3>
</div>

[![Docker Image CI](https://github.com/markaspot/mark-a-spot/actions/workflows/docker-image.yml/badge.svg)](https://github.com/markaspot/mark-a-spot/actions/workflows/docker-image.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![Drupal: 11](https://img.shields.io/badge/Drupal-11-brightgreen.svg)](https://www.drupal.org/project/markaspot)

### Features

- **Citizen Reporting** – Photos, descriptions, geolocation
- **Interactive Maps** – Pinpoint locations, clustering, filtering
- **Open311 API** – Standard GeoReport v2 integration
- **Workflow Management** – Track issues from report to resolution

Built for municipalities, public service departments, and civic tech organizations.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (PWA)                          │
│         Vue 3 · TypeScript · Tailwind · MapLibre            │
└─────────────────────────────┬───────────────────────────────┘
                              │
                    HTTPS/JSON (REST)
                              │
┌─────────────────────────────▼───────────────────────────────┐
│                    Drupal 11 Backend                        │
│                                                             │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │     Open311 API      │  │       JSON:API       │        │
│  │     (GeoReport)      │  │        (CRUD)        │        │
│  └──────────────────────┘  └──────────────────────┘        │
└─────────────────────────────────────────────────────────────┘
```

## Quick Start

Requires [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/).

```bash
git clone https://github.com/markaspot/mark-a-spot.git
cd mark-a-spot
ddev start
ddev ssh
./scripts/start.sh -y
exit
```

**Access:**
- **Backend:** https://mark-a-spot.ddev.site
- **Frontend:** https://mark-a-spot.ddev.site:8040
- **Admin:** `ddev drush uli`

### Installation Options

| Flag | Description |
|------|-------------|
| `-y` | Autopilot mode (defaults: New York, en_US) |
| `-t` | Import Drupal translation files |
| `-a` | AI content translation (requires `OPENAI_API_KEY`) |

Combine flags as needed: `./scripts/start.sh -t -a`

### Multilingual Setup

Use `-t` for Drupal translations, `-a` for AI-powered content translation.
Requires `OPENAI_API_KEY`. See `./scripts/start.sh --help` for details.

## API

Implements the [Open311 GeoReport v2](https://wiki.open311.org/GeoReport_v2) standard.

| Endpoint | Description |
|----------|-------------|
| `GET /georeport/v2/services.json` | List service categories |
| `GET /georeport/v2/requests.json` | List service requests |
| `GET /georeport/v2/requests/{id}.json` | Get single request |
| `POST /georeport/v2/requests.json` | Create request |

## Requirements

- PHP 8.3+
- Node.js 22+ (LTS)
- MySQL 8.0+ / MariaDB 10.6+
- Composer 2.x

## Contributing

- [GitHub Issues](https://github.com/markaspot/mark-a-spot/issues)
- [Mark-a-Spot Profile](https://github.com/markaspot/markaspot)

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
