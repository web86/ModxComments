# Public API contract v0.1

Base URL:

`/assets/components/modxcomments/connector.php`

All responses use JSON and this envelope:

```json
{
  "success": true,
  "message": "",
  "object": {}
}
```

## GET web/init

Request:

```text
?action=web/init&context=web
```

Example payload inside `object`:

```json
{
  "csrf": "session-token",
  "user": {"id": 0, "authenticated": false, "name": ""},
  "settings": {"allowGuests": true, "maxDepth": 5, "maxLength": 5000}
}
```

Never cache this response.

## GET web/comment/getlist

Request:

```text
?action=web/comment/getlist&resource=123&context=web&limit=200
```

Example `object`:

```json
{
  "total": 2,
  "comments": [
    {
      "id": 10,
      "parent": 0,
      "thread": 10,
      "depth": 0,
      "author": {"id": 4, "name": "Alice"},
      "content": "Hello",
      "contentHtml": "Hello",
      "created": "2026-10-02 18:10:00",
      "edited": false,
      "canReply": true
    }
  ]
}
```

## POST web/comment/create

Content-Type can be form-urlencoded or JSON.

Headers:

```text
X-ModxComments-CSRF: <token from web/init>
```

Body:

```json
{
  "action": "web/comment/create",
  "resource": 123,
  "context": "web",
  "parent": 0,
  "author_name": "Guest",
  "author_email": "guest@example.test",
  "content": "Hello https://example.com 😀"
}
```

The server ignores any client-provided `user_id`, `depth`, `thread_id`, `path`, `status`, or rendered HTML.
