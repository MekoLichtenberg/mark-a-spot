# GeoReport v2 (Open311)

Implementation of the [Open311 GeoReport v2](http://wiki.open311.org/GeoReport_v2) standard with Mark-a-Spot extensions.

**Module:** `markaspot_open311`
**Base URL:** `/georeport/v2/`

---

## Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/discovery.json` | GET | API metadata and capabilities |
| `/services.json` | GET | Available service categories |
| `/requests.json` | GET | List service requests |
| `/requests.json` | POST | Create service request |
| `/requests/{id}.json` | GET | Single service request |
| `/requests/{id}.json` | POST | Update service request |

---

## Discovery

Returns API metadata and available endpoints.

```bash
GET /georeport/v2/discovery.json
```

**Response:**
```json
{
  "changeset": "2024-01-15T10:30:00Z",
  "contact": "admin@example.com",
  "key_service": "https://example.com/admin/config/services/api-keys",
  "endpoints": [
    {
      "specification": "http://wiki.open311.org/GeoReport_v2",
      "url": "https://example.com/georeport/v2",
      "changeset": "2024-01-15T10:30:00Z",
      "type": "production",
      "formats": ["application/json", "text/xml"]
    }
  ]
}
```

---

## Services

Returns available service categories.

```bash
GET /georeport/v2/services.json?api_key={key}
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `api_key` | string | Yes | API authentication key |

**Response:**
```json
[
  {
    "service_code": "3.2.1",
    "service_name": "Pothole",
    "description": "Report potholes in streets",
    "metadata": true,
    "type": "realtime",
    "keywords": "street,road,pothole",
    "group": "Streets"
  },
  {
    "service_code": "4.1.1",
    "service_name": "Graffiti",
    "description": "Report graffiti on public property",
    "metadata": true,
    "type": "realtime",
    "keywords": "graffiti,vandalism",
    "group": "Public Spaces"
  }
]
```

---

## List Requests

Query service requests with filters.

```bash
GET /georeport/v2/requests.json?api_key={key}&status=open&limit=50
```

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `api_key` | string | Yes | - | API authentication key |
| `status` | string | No | all | Filter: `open`, `closed` |
| `service_code` | string | No | - | Filter by category code |
| `start_date` | string | No | - | ISO 8601 datetime |
| `end_date` | string | No | - | ISO 8601 datetime |
| `lat` | float | No | - | Latitude for geo search |
| `long` | float | No | - | Longitude for geo search |
| `radius` | int | No | 1000 | Search radius in meters |
| `limit` | int | No | 100 | Results per page (max 500) |
| `page` | int | No | 0 | Page number |
| `sort` | string | No | DESC | Sort order: `ASC`, `DESC` |
| `extensions` | bool | No | false | Include extended attributes |
| `full` | bool | No | false | Include all Drupal fields |
| `fields` | string | No | - | Comma-separated field names |
| `langcode` | string | No | en | Language code |
| `group_filter` | bool | No | false | Filter by user's organisation |

**Response:**
```json
[
  {
    "service_request_id": "SR-2024-001234",
    "status": "open",
    "status_notes": "Assigned to maintenance team",
    "service_name": "Pothole",
    "service_code": "3.2.1",
    "description": "Large pothole on Main Street near intersection",
    "agency_responsible": "Streets Department",
    "service_notice": "",
    "requested_datetime": "2024-01-15T09:30:00+00:00",
    "updated_datetime": "2024-01-15T14:20:00+00:00",
    "expected_datetime": null,
    "address": "123 Main Street",
    "address_id": null,
    "zipcode": "12345",
    "lat": 51.4339,
    "long": 6.7509,
    "media_url": "https://example.com/sites/default/files/report-123.jpg"
  }
]
```

---

## Single Request

Get a single service request by ID.

```bash
GET /georeport/v2/requests/{service_request_id}.json?api_key={key}
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `api_key` | string | Yes | API authentication key |
| `extensions` | bool | No | Include extended attributes |

**Response:** Same structure as list, but single item array.

---

## Create Request

Submit a new service request.

```bash
POST /georeport/v2/requests.json?api_key={key}
Content-Type: application/x-www-form-urlencoded
```

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `api_key` | string | Yes | API authentication key |
| `service_code` | string | Yes | Category code from services list |
| `lat` | float | Yes* | Latitude |
| `long` | float | Yes* | Longitude |
| `address_string` | string | Yes* | Street address (*either coords or address) |
| `description` | string | Yes | Issue description |
| `email` | string | No | Reporter email for updates |
| `first_name` | string | No | Reporter first name |
| `last_name` | string | No | Reporter last name |
| `phone` | string | No | Reporter phone number |
| `media_url` | string | No | URL to image |

**Example:**
```bash
curl -X POST 'https://example.com/georeport/v2/requests.json?api_key=abc123' \
  -d 'service_code=3.2.1' \
  -d 'lat=51.4339' \
  -d 'long=6.7509' \
  -d 'description=Large pothole causing traffic issues' \
  -d 'email=citizen@example.com'
```

**Response (201):**
```json
[
  {
    "service_request_id": "SR-2024-001235",
    "service_notice": "Your report has been submitted",
    "account_id": null
  }
]
```

---

## Update Request

Update an existing service request (requires editor/manager API key).

```bash
POST /georeport/v2/requests/{service_request_id}.json?api_key={editor_key}
Content-Type: application/json
```

**Body:**
```json
{
  "status": "in_progress",
  "status_notes": "Maintenance crew dispatched"
}
```

---

## Extended Attributes

Request extended attributes with `?extensions=true`.

**Response with extensions:**
```json
[
  {
    "service_request_id": "SR-2024-001234",
    "status": "open",
    ...
    "extended_attributes": {
      "markaspot": {
        "nid": "1234",
        "uuid": "a1b2c3d4-e5f6-...",
        "status_history": [
          {
            "status": "open",
            "status_hex": "#28a745",
            "changed": "2024-01-15T09:30:00+00:00"
          },
          {
            "status": "in_progress",
            "status_hex": "#ffc107",
            "changed": "2024-01-15T14:20:00+00:00"
          }
        ],
        "category": {
          "hex": "#007bff",
          "icon": "pothole"
        }
      },
      "media": [
        {
          "mid": 456,
          "uuid": "m1n2o3p4-...",
          "url": "https://example.com/sites/default/files/report-123.jpg",
          "published": true
        }
      ],
      "drupal": {
        "field_custom": "Custom field value"
      }
    }
  }
]
```

### Media Management

Publish/unpublish media (requires editor/manager key):

```bash
POST /georeport/v2/requests/{id}.json?api_key={editor_key}
Content-Type: application/json

{
  "extended_attributes": {
    "media": [
      {"mid": 456, "published": false}
    ]
  }
}
```

---

## Error Responses

| Code | Error | Description |
|------|-------|-------------|
| 400 | `GEN_BAD_REQUEST` | Invalid parameters |
| 401 | `GEN_UNAUTHORIZED` | Invalid or missing API key |
| 403 | `GEN_FORBIDDEN` | Insufficient permissions |
| 404 | `GEN_NOT_FOUND` | Request not found |
| 500 | `GEN_INTERNAL_ERROR` | Server error |

**Error Response Format:**
```json
{
  "error": [
    {
      "code": 401,
      "description": "Invalid API key"
    }
  ]
}
```

---

## XML Format

Replace `.json` with `.xml` for XML responses:

```bash
GET /georeport/v2/requests.xml?api_key={key}
```
