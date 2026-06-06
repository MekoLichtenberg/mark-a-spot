# Custom REST API

Mark-a-Spot custom REST endpoints for features beyond GeoReport v2.

> **Version Note:** Endpoints marked with `11.8+` require Mark-a-Spot profile version 11.8.x or later.

## Authentication Quick Reference

| Endpoints | Auth Required |
|-----------|---------------|
| `/api/stats/*` | None (public) |
| `/api/mark-a-spot-settings` | None (public) |
| `/api/emergency-mode/status` | None (public) |
| `/api/auth/*` | None (passwordless flow) |
| `/api/dashboard/*` | Cookie session + `access dashboard kpis` |
| `/api/ai/*` | Cookie session + `access ai insights` |
| `/api/dashboard/status-notes` | Cookie session (authenticated) |
| `/api/feedback/*` | Token in URL (email link) |
| `/api/service-response/*` | Access code (email link) |

---

## Statistics

**Module:** `markaspot_stats`
**Authentication:** None (public)

Public statistics for dashboards and reporting. Returns aggregated counts grouped by status or category. Respects `Accept-Language` header for localized labels.

### GET /api/stats/status

Count of reports per status.

**Response:**
```json
[
  {"name": "Open", "count": 42, "hex": "#28a745"},
  {"name": "In Progress", "count": 15, "hex": "#ffc107"},
  {"name": "Closed", "count": 128, "hex": "#6c757d"}
]
```

### GET /api/stats/categories

Count of reports per category.

**Response:**
```json
[
  {"name": "Pothole", "count": 35, "hex": "#007bff"},
  {"name": "Graffiti", "count": 22, "hex": "#dc3545"},
  {"name": "Street Light", "count": 18, "hex": "#ffc107"}
]
```

### GET /api/stats/categories/hierarchical

Categories with parent/child hierarchy.

**Response:**
```json
[
  {
    "name": "Streets",
    "children": [
      {"name": "Pothole", "count": 35},
      {"name": "Road Damage", "count": 12}
    ]
  }
]
```

---

## Dashboard `11.8+`

**Module:** `markaspot_dashboard`
**Authentication:** Cookie session

Staff dashboard analytics and KPIs.

**Permission required:** `access dashboard kpis`

### GET /api/dashboard/kpis

Key performance indicators.

**Response:**
```json
{
  "total_reports": 1234,
  "open_reports": 42,
  "closed_reports": 1150,
  "in_progress": 42,
  "avg_resolution_time_hours": 48.5,
  "reports_this_week": 23,
  "reports_this_month": 89
}
```

### GET /api/dashboard/time-series/volume

Report submissions over time.

**Parameters:**

| Name | Type | Default | Description |
|------|------|---------|-------------|
| `period` | string | month | `day`, `week`, `month`, `year` |
| `count` | int | 12 | Number of periods |

**Response:**
```json
[
  {"date": "2024-01", "count": 89},
  {"date": "2024-02", "count": 102},
  {"date": "2024-03", "count": 78}
]
```

### GET /api/dashboard/time-series/processing

Processing/resolution times over time.

**Response:**
```json
[
  {"date": "2024-01", "avg_hours": 52.3},
  {"date": "2024-02", "avg_hours": 48.1},
  {"date": "2024-03", "avg_hours": 44.7}
]
```

### GET /api/dashboard/forwarding-details

Service provider forwarding statistics.

**Response:**
```json
[
  {
    "provider": "Streets Dept",
    "total": 156,
    "open": 12,
    "closed": 144,
    "categories": ["Pothole", "Road Damage"]
  }
]
```

### GET /api/dashboard/hazards

AI-detected safety hazards summary.

**Response:**
```json
{
  "total_hazards": 23,
  "by_type": [
    {"type": "broken_glass", "count": 12},
    {"type": "dangerous_structure", "count": 8},
    {"type": "trip_hazard", "count": 3}
  ]
}
```

---

## AI Features `11.8+`

**Module:** `markaspot_ai`
**Authentication:** Cookie session

AI-powered automation and analysis using Claude/OpenAI.

**Permissions required:** `access ai insights`, `review ai duplicates`, or `administer markaspot ai`

### Duplicate Detection

#### GET /api/ai/duplicates/{node_id}

Find similar reports for a given request.

**Response:**
```json
{
  "request_id": "SR-2024-001234",
  "duplicates": [
    {
      "request_id": "SR-2024-001100",
      "similarity_score": 0.89,
      "status": "pending_review"
    }
  ]
}
```

#### POST /api/ai/duplicates/{node_id}/scan

Trigger new duplicate scan.

**Response:**
```json
{
  "success": true,
  "duplicates_found": 2
}
```

#### GET /api/ai/duplicates/pending

Reports awaiting duplicate review.

**Response:**
```json
[
  {
    "request_id": "SR-2024-001234",
    "potential_duplicate_of": "SR-2024-001100",
    "similarity_score": 0.89,
    "created": "2024-01-15T10:30:00Z"
  }
]
```

#### POST /api/ai/duplicates/{match_id}/review

Mark as duplicate or dismiss.

**Body:**
```json
{
  "action": "confirm",
  "merge_into": "SR-2024-001100"
}
```

Or to dismiss:
```json
{
  "action": "dismiss"
}
```

### Sentiment Analysis

#### GET /api/ai/sentiment/{node_id}

Sentiment score for single report.

**Response:**
```json
{
  "request_id": "SR-2024-001234",
  "sentiment": "frustrated",
  "score": -0.7,
  "keywords": ["waiting", "weeks", "no response"]
}
```

#### GET /api/ai/sentiment/stats

Overall sentiment distribution.

**Response:**
```json
{
  "positive": 45,
  "neutral": 120,
  "negative": 35,
  "frustrated": 12
}
```

#### GET /api/ai/sentiment/frustrated

High-priority frustrated citizens.

**Response:**
```json
[
  {
    "request_id": "SR-2024-001234",
    "score": -0.85,
    "days_open": 14,
    "description_preview": "I've been waiting for weeks..."
  }
]
```

### Processing Control

#### GET /api/ai/usage

Token usage and API costs.

**Response:**
```json
{
  "tokens_used": 125000,
  "estimated_cost_usd": 2.50,
  "period": "2024-01"
}
```

#### GET /api/ai/processing/status

Queue depth and processing state.

**Response:**
```json
{
  "queue_depth": 15,
  "processing": true,
  "last_processed": "2024-01-15T14:20:00Z"
}
```

#### POST /api/ai/processing/queue

Add unprocessed items to queue.

#### POST /api/ai/processing/run

Manually trigger queue processing.

---

## Feedback

**Module:** `markaspot_feedback`
**Authentication:** Token in URL (sent via email)

Post-resolution citizen feedback collection.

### GET /api/feedback/{uuid}

Load report details for feedback form.

**Response:**
```json
{
  "request_id": "SR-2024-001234",
  "service_name": "Pothole",
  "description": "Large pothole on Main Street",
  "resolved_date": "2024-01-20T10:00:00Z",
  "already_submitted": false
}
```

### POST /api/feedback/{uuid}

Submit rating and comment.

**Body:**
```json
{
  "rating": 4,
  "comment": "Fixed quickly, thank you!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Thank you for your feedback"
}
```

---

## Service Provider

**Module:** `markaspot_service_provider`
**Authentication:** Access code (sent via email)

External contractor integration.

### GET /api/service-response/{uuid}

Load request details for provider.

**Response:**
```json
{
  "request_id": "SR-2024-001234",
  "service_name": "Pothole",
  "description": "Large pothole on Main Street",
  "address": "123 Main Street",
  "lat": 51.4339,
  "long": 6.7509,
  "assigned_date": "2024-01-15T10:00:00Z",
  "current_status": "assigned"
}
```

### POST /api/service-response/{uuid}

Submit status update and notes.

**Body:**
```json
{
  "status": "completed",
  "notes": "Pothole filled and compacted",
  "completion_date": "2024-01-18T14:30:00Z"
}
```

### POST /api/service-response/{uuid}/auth

Verify provider access code.

**Body:**
```json
{
  "code": "ABC123"
}
```

---

## Frontend Configuration

**Module:** `markaspot_nuxt`
**Authentication:** None (public) for settings, Cookie session for voting/notes

Configuration endpoints for Nuxt frontend.

### GET /api/mark-a-spot-settings

Theme, features, and map configuration.

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `jurisdiction` | int | Jurisdiction group ID |

**Response:**
```json
{
  "client": {
    "name": "City of Example",
    "shortName": "Example"
  },
  "theme": {
    "primary": "blue",
    "secondary": "cyan",
    "neutral": "slate"
  },
  "features": {
    "voting": true,
    "statistics": true,
    "photoReporting": true
  },
  "map": {
    "center": [51.4339, 6.7509],
    "zoom": 12,
    "style": "https://tiles.openfreemap.org/styles/liberty"
  },
  "languages": {
    "available": ["en", "de"],
    "default": "en"
  }
}
```

### GET /api/mark-a-spot-form-mode-settings/{entity}/{bundle}/{mode}

Form field definitions.

**Example:**
```bash
GET /api/mark-a-spot-form-mode-settings/node/service_request/default
```

### GET /api/field-options/{entity}/{field}

Dropdown options for fields.

**Example:**
```bash
GET /api/field-options/node/field_category
```

### GET /api/jurisdictions

Available jurisdictions.

**Response:**
```json
[
  {"id": 14, "name": "City of Cologne", "slug": "cologne"},
  {"id": 15, "name": "City of Bonn", "slug": "bonn"}
]
```

### GET /api/organisations

Available organisations.

---

## Status Notes `11.8+`

**Module:** `markaspot_dashboard`
**Authentication:** Cookie session (staff only)

Internal notes for staff (Dashboard feature).

> **Why custom endpoint?** Status notes use Paragraphs (`entity_reference_revisions`), which don't work well with JSON:API for create/delete operations. This endpoint handles paragraph creation/deletion + linking atomically.

### POST /api/dashboard/status-notes

Add internal note to a request.

**Body:**
```json
{
  "request_uuid": "a1b2c3d4-...",
  "status_term_uuid": "s1t2u3v4-...",
  "note": "Called citizen, issue confirmed",
  "boilerplate_uuid": null
}
```

**Response:**
```json
{
  "uuid": "n1o2t3e4-...",
  "success": true
}
```

### DELETE /api/dashboard/status-notes/{uuid}

Remove note.

---

## Email Confirmation

**Module:** `markaspot_confirm`
**Authentication:** Token in URL (sent via email)

Email verification for anonymous submissions.

### GET /api/confirm/{uuid}

Confirm submission via email link.

**Response (redirect):** Redirects to frontend with success message.

---

## Emergency Mode

**Module:** `markaspot_emergency`
**Authentication:** None (public)

Crisis mode activation.

### GET /api/emergency-mode/status

Check if emergency mode is active.

**Response (normal):**
```json
{
  "active": false
}
```

**Response (emergency):**
```json
{
  "active": true,
  "message": "Due to flooding, only emergency reports are accepted",
  "redirect_url": "https://emergency.example.com",
  "allowed_categories": ["flooding", "road_closure"]
}
```

---

## Vision (Image Analysis) `11.8+`

**Module:** `markaspot_vision`
**Authentication:** None (public, protected by rate limiting)

AI-powered image analysis for privacy detection, hazard identification, and automatic alt text generation.

### POST /markaspot_vision/getAIresults

Analyze uploaded media images for privacy concerns and safety hazards.

**Request:**
```json
{
  "media_ids": ["uuid-1", "uuid-2"],
  "language": "de"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `media_ids` | array | Yes | Array of media entity UUIDs |
| `language` | string | No | Language code for AI response (e.g., "de", "en") |

**Response:**
```json
{
  "privacy_flag": true,
  "privacy_issues": ["face_visible", "license_plate"],
  "hazard_flag": true,
  "hazard_issues": ["broken_glass", "trip_hazard"],
  "alt_text": ["Description of image 1", "Description of image 2"]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `privacy_flag` | boolean | True if privacy concerns detected |
| `privacy_issues` | array | List of detected privacy issues |
| `hazard_flag` | boolean | True if safety hazards detected |
| `hazard_issues` | array | List of detected hazard types |
| `alt_text` | array | AI-generated alt text for each image (accessibility) |

**Side Effects:**
- Updates media entity fields: `field_ai_metadata`, `field_ai_privacy_flag`, `field_ai_privacy_issues`, `field_ai_hazard_flag`, `field_ai_hazard_issues`
- Populates `field_media_image.alt` with AI-generated description

**Error Response (500):**
```json
{
  "error": "No valid media entities found for the provided media_ids."
}
```

---

## CAP Alerts `11.8+`

**Module:** `markaspot_cap`
**Authentication:** None (public) or API Key

Common Alerting Protocol (CAP 1.2) export for integration with emergency warning systems.

### GET /api/cap/v1/alerts

List active alerts.

**Response:**
```json
{
  "alerts": [
    {
      "identifier": "CAP-2024-001",
      "sender": "example.com",
      "sent": "2024-01-15T10:00:00+00:00",
      "status": "Actual",
      "msgType": "Alert",
      "scope": "Public",
      "info": {
        "category": "Safety",
        "event": "Gas Leak",
        "urgency": "Immediate",
        "severity": "Severe",
        "headline": "Gas leak at Main Street"
      }
    }
  ]
}
```

### GET /api/cap/v1/alerts/{id}

Single alert with full CAP XML/JSON.

**Accept Header:**
- `application/json` - JSON format
- `application/xml` - CAP 1.2 XML format
