# Authentication

Mark-a-Spot supports multiple authentication methods depending on the API and use case.

## Overview

| Method | APIs | Use Case |
|--------|------|----------|
| [API Key (role-based)](#api-key) | GeoReport v2 | Third-party Open311 clients |
| [Cookie Session + CSRF](#cookie-session) | JSON:API, Dashboard, Custom REST | Authenticated users (staff, logged-in citizens) |
| [Passwordless Login](#passwordless-auth) | `/api/auth/*` | Creates cookie session for citizens |
| [Public (no auth)](#public-endpoints) | Stats, Settings | Public data |

### Nuxt Frontend Authentication Model

The Nuxt frontend does **not** authenticate as a user. It acts as a **proxy**:

1. **Anonymous requests:** Nuxt server adds `GEOREPORT_API_KEY` (env variable) to GeoReport requests
2. **Logged-in users:** Drupal session cookie is passed through the proxy to Drupal
3. **Staff dashboard:** Staff's Drupal session cookie is passed through

```
Browser → Nuxt Proxy → Drupal
           ↓
    [adds api_key if no session]
    [forwards session cookie if present]
```

---

## API Key

Used exclusively for GeoReport v2 (Open311) API. API keys are **role-based** - the key itself determines what data is accessible.

### Request Format

```
GET /georeport/v2/requests.json?api_key={key}
```

### Key Management

Keys are managed in Drupal admin:

```
/admin/config/services/api-key-auth
```

Each API key is associated with a Drupal user account. The user's role determines API access level.

### Access Levels (Role-Based)

| API Key Role | Field Access | Extended Attributes |
|--------------|--------------|---------------------|
| Anonymous | Standard GeoReport fields only | No |
| User | + status, status_notes | Basic (`markaspot`, `media`) |
| Manager/Editor | + all Drupal fields, contact info | Full (`drupal` with `full=true`) |

**Field access is configured at:** `/admin/structure/markaspot/open311/settings`

```yaml
# Example field_access configuration
field_access:
  public_fields: {}                    # Anonymous keys
  user_fields:
    status: status
    field_status: field_status
  manager_fields:
    status: status
    field_status: field_status
    field_request_media: field_request_media
```

### Example

```bash
# Anonymous key - basic data only
curl 'https://example.com/georeport/v2/requests.json?api_key=anon_key'

# User key - with status info
curl 'https://example.com/georeport/v2/requests.json?api_key=user_key&extensions=true'

# Manager key - full Drupal fields
curl 'https://example.com/georeport/v2/requests.json?api_key=manager_key&extensions=true&full=true'
```

### Security Best Practices

1. **Never expose API keys in client-side code**
2. **Use HTTPS only** for API key transmission
3. **Rotate keys regularly**
4. **Limit key permissions** to minimum required
5. **Monitor key usage** for abuse detection

---

## Public Endpoints

Some endpoints require no authentication.

### Statistics API

```bash
# No authentication required
curl 'https://example.com/api/stats/status'
curl 'https://example.com/api/stats/categories'
```

### Emergency Mode Status

```bash
curl 'https://example.com/api/emergency-mode/status'
```

### Frontend Settings

```bash
curl 'https://example.com/api/mark-a-spot-settings?jurisdiction=14'
```

---

## Cookie Session

Used for JSON:API, Dashboard, and authenticated Custom REST endpoints (status notes, voting).

### Login Flow

1. Authenticate via Drupal login or passwordless flow
2. Receive session cookie (`SSESS...`)
3. Include cookie in subsequent requests

### CSRF Token

Write operations require a CSRF token:

```bash
# 1. Get token
curl -c cookies.txt 'https://example.com/session/token'

# 2. Use token for write operations
curl -b cookies.txt \
  -H "X-CSRF-Token: {token}" \
  -H "Content-Type: application/vnd.api+json" \
  -X POST 'https://example.com/jsonapi/node/service_request' \
  -d '{"data": {...}}'
```

### Required Headers

| Header | Value | Required For |
|--------|-------|--------------|
| `Cookie` | `SSESS...={session}` | All authenticated requests |
| `X-CSRF-Token` | Token from `/session/token` | POST, PATCH, DELETE |
| `Content-Type` | `application/vnd.api+json` | JSON:API writes |

---

## Passwordless Login

**Module:** `markaspot_passwordless`

Email-based one-time code login for headless Drupal. Provides REST endpoints that any client can use (Nuxt, mobile apps, third-party integrations). After successful verification, a standard Drupal cookie session is created.

**Why passwordless instead of username/password?**

Drupal also supports standard REST login (`POST /user/login?_format=json`), but Mark-a-Spot uses passwordless because:
- No password management overhead for occasional users
- No "forgot password" flows needed
- Email verification built-in
- Simpler UX for citizen reporting

Staff can use either standard Drupal login (backend) or passwordless (frontend dashboard).

**Important:** The Nuxt frontend does NOT authenticate as a user against Drupal. It acts as a **proxy**:

| Scenario | Authentication |
|----------|----------------|
| Anonymous browsing | Nuxt proxy adds `GEOREPORT_API_KEY` server-side |
| After passwordless login | User's session cookie is passed through to Drupal |
| Staff dashboard | Staff's Drupal session cookie is passed through |

The passwordless flow creates a Drupal session that the proxy then forwards.

### Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Request   │────▶│   Verify    │────▶│  Authenticated
│    Code     │     │    Code     │     │   Session   │
└─────────────┘     └─────────────┘     └─────────────┘
```

### 1. Request Code

```bash
POST /api/auth/request-code
Content-Type: application/json

{
  "email": "citizen@example.com"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Code sent to email"
}
```

### 2. Verify Code

```bash
POST /api/auth/verify-code
Content-Type: application/json

{
  "email": "citizen@example.com",
  "code": "123456"
}
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "uid": "42",
    "name": "citizen@example.com"
  }
}
```

Session cookie is set automatically.

### 3. Check Status

```bash
GET /api/auth/status
```

**Response (authenticated):**
```json
{
  "authenticated": true,
  "user": {
    "uid": "42",
    "name": "citizen@example.com",
    "roles": ["authenticated"]
  }
}
```

**Response (anonymous):**
```json
{
  "authenticated": false
}
```

### 4. Logout

```bash
POST /api/auth/logout
```

**Response:**
```json
{
  "success": true
}
```

---

## Error Responses

### Invalid API Key

```json
{
  "error": "Invalid API key",
  "code": 401
}
```

### Missing CSRF Token

```json
{
  "message": "X-CSRF-Token request header is missing",
  "code": 403
}
```

### Session Expired

```json
{
  "error": "Session expired",
  "code": 401
}
```

### Invalid Verification Code

```json
{
  "success": false,
  "error": "Invalid or expired code"
}
```

---

## Security Notes

1. **Always use HTTPS** - Never send credentials over unencrypted connections
2. **API Key rotation** - Rotate keys periodically
3. **Code expiration** - Passwordless codes expire after 10 minutes
4. **Rate limiting** - Code requests are rate-limited per email
