# Mark-a-Spot API Documentation

Mark-a-Spot provides three API layers for different use cases.

## Version Compatibility

| Module | 11.7.x | 11.8.x | Endpoints |
|--------|:------:|:------:|-----------|
| `markaspot_open311` | ✓ | ✓ | `/georeport/v2/*` |
| `markaspot_stats` | ✓ | ✓ | `/api/stats/*` |
| `markaspot_nuxt` | ✓ | ✓ | `/api/mark-a-spot-settings` |
| `markaspot_passwordless` | ✓ | ✓ | `/api/auth/*` |
| `markaspot_feedback` | ✓ | ✓ | `/api/feedback/*` |
| `markaspot_service_provider` | ✓ | ✓ | `/api/service-response/*` |
| `markaspot_confirm` | ✓ | ✓ | `/api/confirm/*` |
| `markaspot_emergency` | ✓ | ✓ | `/api/emergency-mode/*` |
| `markaspot_dashboard` | - | ✓ | `/api/dashboard/*` |
| `markaspot_ai` | - | ✓ | `/api/ai/*` |
| `markaspot_cap` | - | ✓ | `/api/cap/*` |
| `markaspot_vision` | - | ✓ | `/markaspot_vision/*` |

---

## Quick Links

| Document | Description |
|----------|-------------|
| [GeoReport v2](./georeport-v2.md) | Open311 standard API |
| [Custom REST](./custom-rest.md) | Stats, AI, Dashboard, Settings |
| [Authentication](./authentication.md) | API keys, sessions, passwordless auth |

---

## API Overview

| API | Purpose | Base URL | Documentation |
|-----|---------|----------|---------------|
| GeoReport v2 | Open311 citizen reporting | `/georeport/v2/` | [georeport-v2.md](./georeport-v2.md) |
| Custom REST | Stats, AI, Dashboard, Auth | `/api/` | [custom-rest.md](./custom-rest.md) |
| JSON:API | CRUD for Drupal entities | `/jsonapi/`* | [Drupal JSON:API](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module) |

*\* Path can be randomized via `JSONAPI_RANDOM_PATH` for security*

---

## Quick Start

### Public Data (no auth)

```bash
# Statistics
curl 'https://example.com/api/stats/status'

# GeoReport (API key required)
curl 'https://example.com/georeport/v2/requests.json?api_key={key}&status=open'
```

### Create Report

```bash
curl -X POST 'https://example.com/georeport/v2/requests.json?api_key={key}' \
  -d 'service_code=3.2.1' \
  -d 'lat=51.4339' \
  -d 'long=6.7509' \
  -d 'description=Pothole on Main Street'
```

### Authenticated Request (JSON:API)

```bash
# Get CSRF token
TOKEN=$(curl -s 'https://example.com/session/token')

# Create with session cookie
curl -X POST 'https://example.com/jsonapi/node/service_request' \
  -H "X-CSRF-Token: $TOKEN" \
  -H "Content-Type: application/vnd.api+json" \
  -b cookies.txt \
  -d '{"data": {...}}'
```

---

## Authentication

| Endpoint | Auth Method |
|----------|-------------|
| `/georeport/v2/*` | API Key (role-based) |
| `/api/stats/*` | None (public) |
| `/api/mark-a-spot-settings` | None (public) |
| `/api/dashboard/*` | Cookie session (`access dashboard kpis`) |
| `/api/ai/*` | Cookie session (`access ai insights`) |
| `/api/dashboard/status-notes` | Cookie session (authenticated) |
| `/api/auth/*` | None (passwordless login flow) |
| `/jsonapi/*` | Cookie + CSRF token |

### Nuxt Frontend as Proxy

The Nuxt frontend does **not** authenticate directly. It proxies requests to Drupal:
- **Anonymous:** Nuxt adds `GEOREPORT_API_KEY` server-side
- **Logged-in:** Session cookie is passed through to Drupal

See [Authentication](./authentication.md) for details.

---

## Modules

### GeoReport v2 (Open311)

| Module | Purpose |
|--------|---------|
| `markaspot_open311` | Open311 GeoReport v2 implementation for civic issue tracking |

### Custom REST

| Module | Purpose | Endpoints | Version |
|--------|---------|-----------|---------|
| `markaspot_passwordless` | Email-based one-time code authentication | `/api/auth/*` | 11.7+ |
| `markaspot_stats` | Public statistics for dashboards | `/api/stats/*` | 11.7+ |
| `markaspot_dashboard` | Staff KPIs, analytics, status notes | `/api/dashboard/*` | **11.8+** |
| `markaspot_ai` | Duplicate detection, sentiment, image analysis | `/api/ai/*` | **11.8+** |
| `markaspot_feedback` | Post-resolution citizen feedback | `/api/feedback/*` | 11.7+ |
| `markaspot_service_provider` | External contractor integration | `/api/service-response/*` | 11.7+ |
| `markaspot_nuxt` | Frontend config | `/api/mark-a-spot-settings` | 11.7+ |
| `markaspot_confirm` | Email verification for submissions | `/api/confirm/*` | 11.7+ |
| `markaspot_emergency` | Crisis mode activation | `/api/emergency-mode/*` | 11.7+ |
| `markaspot_cap` | CAP 1.2 emergency alerts export | `/api/cap/*` | **11.8+** |
| `markaspot_vision` | AI image analysis (privacy, hazards) | `/markaspot_vision/*` | **11.8+** |


---

## JSON:API Configuration

By default, Mark-a-Spot restricts JSON:API access for security:

| Security Measure | Description |
|------------------|-------------|
| Randomized path | Set `JSONAPI_RANDOM_PATH` env variable to hide `/jsonapi/` |
| Index blocked | `/jsonapi/` without resource returns 404 (no resource discovery) |
| Permission-based | Only authenticated users with appropriate roles |

### Default Exposed Resources

| Resource | Endpoint | Purpose |
|----------|----------|---------|
| Service Requests | `/jsonapi/node/service_request` | CRUD for reports |
| Categories | `/jsonapi/taxonomy_term/service_category` | Load category options |
| Status | `/jsonapi/taxonomy_term/service_status` | Load status options |
| Media | `/jsonapi/media/request_image` | Image uploads |
| Paragraphs | `/jsonapi/paragraph/status` | Status notes |

### Customization

This configuration can be changed based on requirements:

```bash
# Check current JSON:API settings
ddev drush config:get jsonapi.settings

# Enable/disable resources via JSON:API Extras module
# /admin/config/services/jsonapi/resource_types
```

**Note:** Exposing additional resources may have security implications. Always review permissions when enabling new endpoints.

---

## Error Handling

All APIs return consistent error responses:

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid/missing credentials |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 500 | Internal Server Error |

---

## OpenAPI Specs

| File | Description |
|------|-------------|
| [openapi-markaspot.yaml](./openapi-markaspot.yaml) | Custom REST endpoints (OpenAPI 3.1) |
| [openapi-markaspot.json](./openapi-markaspot.json) | Same in JSON format |

**Interactive Testing:** Upload `openapi-markaspot.yaml` to [editor.swagger.io](https://editor.swagger.io)

---

## Further Reading

- [Authentication Details](./authentication.md)
- [GeoReport v2 Reference](./georeport-v2.md)
- [Custom REST Reference](./custom-rest.md)
- [Open311 GeoReport v2 Specification](http://wiki.open311.org/GeoReport_v2)
- [Drupal JSON:API Documentation](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module)
- [Mark-a-Spot GitHub](https://github.com/markaspot/markaspot)
