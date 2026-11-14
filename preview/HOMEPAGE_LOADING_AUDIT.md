# Homepage preview loading audit

The preview HTML previously generated absolute URLs based on the backend request host and scheme. Depending on the preview proxy, those URLs could use HTTP inside an HTTPS page or point outside the browser's current origin. The homepage also depended on external CDN CSS and JavaScript.

## Preview fixes

- `wasm-router.php` converts preview-host URLs in HTML and JSON responses to same-origin relative URLs; binary and XML responses are not rewritten.
- The homepage's pinned Bootstrap, Bootstrap Icons, jQuery and Owl Carousel files are served locally from `preview/vendor/`, with their licenses. The WhatsApp icon uses the locally bundled Bootstrap Icons asset. These replacements apply to the matching external URLs; other pages may use different CDN versions.
- The router avoids caching dynamic responses, allowing refreshed pages to pick up fixes.

## Checks

On the running preview with a simulated HTTPS preview Host header, 84 distinct local homepage media/CSS/JavaScript references returned successfully and none failed. The inspected homepage markup had no remaining external image/script/stylesheet URLs. The cart add/count endpoints still worked and the sitemap remained valid XML with an absolute HTTPS URL.

These are HTTP and markup checks, not a screenshot or full interactive browser test. Remote fonts imported by CSS can still fall back if unavailable. The classic hero image `assets/images/heroes/1772573373_Footer.jpg` remains absent from the supplied archive; the active modern hero files exist. Native PHP and database processes still use temporary sandbox paths; the preview is not a durable production deployment.
