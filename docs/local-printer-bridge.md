# Local Printer Bridge Specification

## Purpose

USB and Bluetooth receipt printers normally cannot be reached directly from a hosted Laravel application. A local bridge runs on the same LAN or device as the printer, authenticates to PosLAB, pulls queued print jobs, prints them locally, and reports success or failure.

This bridge is also the foundation for Star CloudPRNT-style polling. CloudPRNT-compatible routes are placeholders that return pull metadata now and can be expanded to exact Star device dialects later.

## Authentication

Every bridge or printer uses a per-printer secret token.

Send the token on every request using one of:

```http
Authorization: Bearer <printer-token>
X-Printer-Token: <printer-token>
```

The server stores only `sha256(token)` in `printers.bridge_token_hash`. Tokens must be generated with high entropy, shown once to an admin, and rotated if exposed.

## Endpoints

Base path: `/api`

### Heartbeat

```http
POST /api/printer-bridge/heartbeat
Authorization: Bearer <printer-token>
Content-Type: application/json
```

Request:

```json
{
  "status": "online",
  "bridge_version": "1.0.0",
  "device_name": "Counter bridge PC"
}
```

Response:

```json
{
  "printer_id": 1,
  "status": "online",
  "server_time": "2026-07-01T12:00:00+00:00"
}
```

### List Pending Jobs

```http
GET /api/printer-bridge/jobs
Authorization: Bearer <printer-token>
```

Response:

```json
{
  "printer_id": 1,
  "has_jobs": true,
  "jobs": [
    {
      "id": 100,
      "type": "receipt",
      "status": "queued",
      "attempts": 0,
      "created_at": "2026-07-01T12:00:00+00:00",
      "fetch_url": "http://localhost/api/printer-bridge/jobs/100"
    }
  ]
}
```

Only jobs assigned to the authenticated printer are returned.

### Fetch Job

```http
GET /api/printer-bridge/jobs/{job}
Authorization: Bearer <printer-token>
```

Response:

```json
{
  "job": {
    "id": 100,
    "type": "receipt",
    "status": "queued"
  },
  "payload": {},
  "escpos_base64": "G0AuLi4=",
  "plain_text": "Receipt\n..."
}
```

Use `escpos_base64` for ESC/POS-capable USB, Bluetooth, serial, or network printers. Use `plain_text` only for diagnostics or simple test printers.

### Mark Printed

```http
POST /api/printer-bridge/jobs/{job}/printed
Authorization: Bearer <printer-token>
```

Response:

```json
{ "status": "printed" }
```

### Mark Failed

```http
POST /api/printer-bridge/jobs/{job}/failed
Authorization: Bearer <printer-token>
Content-Type: application/json
```

Request:

```json
{
  "error": "USB printer offline"
}
```

Response:

```json
{ "status": "failed" }
```

Failed jobs remain retryable from the admin printer settings page.

### CloudPRNT Poll Placeholder

```http
GET /api/cloudprnt/poll
Authorization: Bearer <printer-token>
```

Response:

```json
{
  "jobReady": true,
  "mediaTypes": ["application/json", "application/vnd.star.starprnt"],
  "jobToken": 100,
  "jobUrl": "http://localhost/api/printer-bridge/jobs/100",
  "deleteMethod": "POST",
  "placeholder": "CloudPRNT-compatible polling contract; full Star device dialect can be expanded later."
}
```

## Bridge Behavior

1. Send heartbeat every 30-60 seconds.
2. Poll `/api/printer-bridge/jobs`.
3. Fetch each job.
4. Decode `escpos_base64`.
5. Print to the configured local USB, Bluetooth, serial, or network printer.
6. Call `/printed` on success or `/failed` with a clear error.
7. Never mark a job printed before the local print operation succeeds.

## Security Rules

- Do not embed tokens in frontend JavaScript.
- Do not share tokens across tenants or printers.
- Store tokens only in the bridge OS secret store or locked-down config file.
- Use HTTPS outside trusted local development.
- Jobs are never publicly accessible; every job endpoint validates the token and printer ownership.
