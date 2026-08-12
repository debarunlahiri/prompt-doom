# Prompt Doom Mobile/User API

This document covers the public and authenticated user endpoints used by a mobile or user-facing client. Admin endpoints are intentionally excluded.

## Base URL

Local XAMPP:

```text
http://localhost:8080/prompt-doom/api/v1
```

For a physical mobile device, `localhost` points to the device itself. Replace it with the development computer's LAN IP, for example:

```text
http://192.168.1.10:8080/prompt-doom/api/v1
```

## Request conventions

- Send JSON requests with `Content-Type: application/json`.
- Protected endpoints require `Authorization: Bearer <accessToken>`.
- Dates are returned as MySQL date-time strings unless otherwise stated.
- Pagination uses `page` and `limit`. `page` defaults to `1`; `limit` defaults to `20` and is capped at `100`.
- Never embed the JWT signing secrets in a mobile application.

## Response format

Successful response:

```json
{
  "success": true,
  "data": {}
}
```

Some successful responses include a message:

```json
{
  "success": true,
  "message": "Profile updated"
}
```

Error response:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "missing": ["email"]
    }
  }
}
```

`204 No Content` responses have no JSON body.

## Authentication

### Register

`POST /auth/register`

Authentication: not required.

```json
{
  "name": "Example User",
  "email": "user@example.com",
  "password": "strong-password"
}
```

Rules:

- Name must contain at least 2 characters.
- Email must be valid.
- Password must contain at least 8 characters.

Returns `201 Created` with the created user and token pair:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Example User",
      "email": "user@example.com"
    },
    "tokens": {
      "accessToken": "<access-token>",
      "refreshToken": "<refresh-token>",
      "tokenType": "Bearer"
    }
  }
}
```

### Log in

`POST /auth/login`

Authentication: not required.

```json
{
  "email": "user@example.com",
  "password": "strong-password"
}
```

Returns the account and token pair. Five failed attempts temporarily lock the account for 15 minutes.

### Continue with Google

`POST /auth/google`

Authentication: not required. Send the Google ID token returned by the native Google sign-in flow:

```json
{
  "idToken": "<google-id-token>"
}
```

The API verifies the token's signature and claims with Google, links or creates the corresponding user, and returns the normal Prompt Doom access/refresh token pair. Only verified Google email addresses are accepted.

### Refresh tokens

`POST /auth/refresh`

Authentication: not required. Send the refresh token in the body:

```json
{
  "refreshToken": "<refresh-token>"
}
```

The supplied refresh token is revoked and a new access/refresh token pair is returned. Replace both stored tokens atomically.

### Log out current session

`POST /auth/logout`

Authentication: not required.

```json
{
  "refreshToken": "<refresh-token>"
}
```

Returns `204 No Content` and revokes that refresh token.

### Log out all sessions

`DELETE /auth/sessions`

Authentication: required user access token.

Returns `204 No Content` and revokes every refresh token belonging to the user.

### Request password reset

`POST /auth/forgot-password`

```json
{
  "email": "user@example.com"
}
```

The response is intentionally the same whether the email exists or not. In a non-production environment, `data.resetToken` is returned when the account exists. The token expires after 30 minutes.

### Reset password

`POST /auth/reset-password`

```json
{
  "token": "<reset-token>",
  "password": "new-password"
}
```

After a successful reset, all existing refresh tokens for the user are revoked.

## Gallery and prompts

### List published images

`GET /images`

Authentication: optional. Invalid optional tokens are treated as anonymous.

Query parameters:

| Parameter  | Type    | Description                            |
| ---------- | ------- | -------------------------------------- |
| `page`     | integer | Page number, starting at 1             |
| `limit`    | integer | Items per page, maximum 100            |
| `q`        | string  | Searches image title and AI-model data |
| `model`    | string  | Exact AI-model filter                  |
| `category` | string  | Category slug                          |
| `tag`      | string  | Tag slug                               |

Example:

```text
GET /images?page=1&limit=20&category=photography&tag=portrait
```

Response shape:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "title": "Example",
        "slug": "example-123",
        "imageUrl": "http://localhost:8080/prompt-doom/uploads/images/example.jpg",
        "thumbnailUrl": "http://localhost:8080/prompt-doom/uploads/thumbnails/example.jpg",
        "aiModel": null,
        "publishedAt": "2026-08-06 10:00:00",
        "viewCount": 0,
        "category": {
          "id": 1,
          "name": "Photography"
        }
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 1,
      "totalPages": 1
    }
  }
}
```

### Get image details

`GET /images/{imageId}`

Authentication: optional.

Returns `data.image`, including category, tags, image URLs, view count, and copy count. Each successful request increments the image view count and records an `image_view` analytics event. Tags are currently returned as:

```json
{
  "tags": [
    {
      "tag": {
        "id": 1,
        "name": "Portrait"
      }
    }
  ]
}
```

### Get an image prompt

`GET /images/{imageId}/prompt`

Authentication: optional.

```json
{
  "success": true,
  "data": {
    "prompt": {
      "mainPrompt": "...",
      "negativePrompt": "..."
    }
  }
}
```

This records a `prompt_view` analytics event. When a valid user token is supplied, it also updates that user's prompt history. The image view count is recorded by the image-detail endpoint, so opening a prompt does not count the same image view twice.

### Add favourite

`POST /images/{imageId}/favorite`

Authentication: required user access token.

No body is required. Returns `201 Created`. Repeating the request is safe and does not create a duplicate favourite.

### Remove favourite

`DELETE /images/{imageId}/favorite`

Authentication: required user access token.

Returns `204 No Content`.

### Record prompt copy

`POST /images/{imageId}/copy`

Authentication: optional. Logged-in copies are linked to the user. Anonymous
copies receive a privacy-safe guest identifier and platform classification.

The optional JSON body is stored as analytics metadata. The mobile app sends
`{"platform":"mobile","source":"prompt_screen"}`. Every successful call
increments the image copy count and returns `201 Created`.

### Record prompt share

`POST /images/{imageId}/share`

Authentication: optional.

Example metadata:

```json
{
  "destination": "system-share-sheet"
}
```

Returns `201 Created`.

Image list and detail responses include the canonical text-share fields:

```json
{
  "shareUrl": "https://promptdoom.com/share/4",
  "shareMessage": "erge\nhttps://promptdoom.com/share/4"
}
```

Mobile clients should send `shareMessage` to the system share sheet. The title
and public share URL are separated by one newline, matching the native Android
text share preview. Opening `/share/{imageId}` redirects into the installed app
at `promptdoom://image/{imageId}`.

### Report content

`POST /images/{imageId}/reports`

Authentication: required user access token.

```json
{
  "reason": "copyright",
  "details": "Optional explanation"
}
```

Allowed reasons: `sexual`, `violent`, `hateful`, `copyright`, `misleading`, `other`.

One user cannot submit duplicate reports for the same image; a duplicate returns `409 CONFLICT`.

## User account

All endpoints in this section require a user access token.

### Get profile

`GET /users/me`

Returns `data.user` with `id`, `email`, `name`, `avatarUrl`, `status`, and `createdAt`.

### Update profile

`PATCH /users/me`

Send at least one supported property:

```json
{
  "name": "Updated Name",
  "avatarUrl": "https://example.com/avatar.jpg"
}
```

### Delete account

`DELETE /users/me`

```json
{
  "password": "current-password"
}
```

Returns `204 No Content`, soft-deletes the account, and revokes its refresh tokens.

### List favourites

`GET /users/favorites?page=1&limit=20`

Returns `data.items`. Each item contains `createdAt` and an `image` object. Pagination is returned as `data.pagination`.

### List prompt history

`GET /users/history?page=1&limit=20`

Returns history items containing `viewedAt`, `copyCount`, `lastCopiedAt`, and a nested image. This endpoint currently returns `page` and `limit` directly rather than a full pagination object.

## Advertisements

### Get advertisement configuration

`GET /ads/config`

Authentication: not required.

```json
{
  "success": true,
  "data": {
    "config": {
      "enabled": true,
      "showAfterClicks": 5,
      "minIntervalSeconds": 120,
      "maxAdsPerSession": 3,
      "updatedAt": "2026-08-06 10:00:00"
    }
  }
}
```

### Record advertisement event

`POST /ads/events`

Authentication: optional.

```json
{
  "sessionId": "mobile-session-id",
  "eventType": "displayed",
  "provider": "optional-provider",
  "placement": "gallery",
  "metadata": {
    "sequence": 5
  }
}
```

Allowed event types: `displayed`, `closed`, `clicked`, `failed`, `skipped`.

## Health check

`GET /health`

Authentication: not required.

Returns service name, API version, status, supported API versions, and a UTC timestamp.

## Recommended mobile token flow

1. Register or log in and securely store both tokens.
2. Send the access token in the `Authorization` header.
3. If a protected request returns `401 INVALID_TOKEN`, call `/auth/refresh` once.
4. Save both newly returned tokens before retrying the original request.
5. If refresh fails, clear the session and return to login.
6. On logout, send the refresh token to `/auth/logout`, then clear local credentials.

## cURL examples

Register:

```bash
curl -X POST 'http://localhost:8080/prompt-doom/api/v1/auth/register' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Example User","email":"user@example.com","password":"strong-password"}'
```

Authenticated profile:

```bash
curl 'http://localhost:8080/prompt-doom/api/v1/users/me' \
  -H 'Authorization: Bearer <access-token>'
```

Published images:

```bash
curl 'http://localhost:8080/prompt-doom/api/v1/images?page=1&limit=20'
```

## Current implementation notes

- CORS currently allows all origins.
- Access tokens default to 15 minutes; refresh tokens default to 30 days unless changed in `.env`.
- Image URLs are absolute and use the configured `APP_URL`.
- For physical devices, set `APP_URL` in the root `.env` file to the development computer's LAN IP so returned image and thumbnail URLs are reachable from the device.
- Only published, non-deleted images are visible through user image endpoints.
- Optional-auth endpoints continue anonymously when the token is missing or invalid.
- The API does not currently provide standalone public category or tag listing endpoints.
