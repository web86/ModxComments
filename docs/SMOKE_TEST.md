# Manual smoke test

After `php _build/build.schema.php` and creating the `ModxComments` snippet:

1. Open a resource containing `[[ModxComments]]`.
2. In browser DevTools → Network confirm two GET requests:
   - `action=web/init`
   - `action=web/comment/getlist`
3. Submit a root comment and confirm a POST to `action=web/comment/create` with `X-ModxComments-CSRF`.
4. Reply to it. Confirm the returned list contains increasing `depth` and sortable tree order.
5. Clear the MODX site cache, load the page once, then reload it from cache and add another comment. The new comment should appear without clearing the page cache.
6. Enter `<script>alert(1)</script> https://example.com 😀`. The script markup must render as text; the HTTP URL may become a safe link; emoji should remain intact.
7. Send more than the configured rate limit from the same client IP within the window and confirm `rate_limit_exceeded`.
8. Open the same resource in a guest session and in an authenticated web-user session; `web/init` should report each session independently even when the resource HTML itself is cached.
