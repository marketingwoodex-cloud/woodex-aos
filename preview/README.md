# Woodex local preview setup

This is an **isolated, disposable local development workflow**, not a production deployment. It needs Docker Engine with Docker Compose, Python 3.11/3.12 and `unrar` with RAR 5 support. Docker, its image registry, and system packages must be available on the host. This repository's four `.zip` files are RAR volumes, not ZIP files.

## Prepare and run

From the repository root:

```sh
python3 preview/prepare.py /absolute/path/to/u128159657_woodexbaks.sql
cat preview/runtime/credentials.txt
cd preview
docker compose up --build -d
```

Open **http://127.0.0.1:8080** on the same machine. First enter the generated HTTP Basic credentials; then use the separately generated `previewadmin` credentials at `/lbpanel/`. Both passwords are random and stored in `preview/runtime/credentials.txt`. Keep that file private. The web port binds to localhost deliberately; do not expose it publicly or use this setup with real customer data. HTTP Basic over plain HTTP is appropriate only on the local machine.

The preparation script reconstructs the application and imports the schema plus a curated set of catalog/content table inserts. Customer/order/contact/review/POS/quotation records, SMTP configuration and original admin accounts are excluded, but the retained content is **not guaranteed to be fully anonymized**. Review it before sharing. Historical sessions and error logs are removed; database connections are rewritten to point only to the isolated local MariaDB container. The unsafe legacy CKEditor upload handler is removed. The generated database, files and credentials are ignored by Git. Email sending is disabled by removing SMTP settings and disabling local mail.

## Stop and reset

```sh
cd preview
docker compose down             # preserve database
# To discard all preview database changes:
docker compose down -v
```

Database initialization runs only when the Docker volume is first created. To regenerate everything, stop the stack, remove the volume using `docker compose down -v`, then delete `preview/runtime` explicitly and run preparation again. This destroys preview data, not the original archives or SQL attachment. New credentials are generated each time.

## Important limitations

- This is a legacy application with known authorization, upload and checkout issues. Basic authentication and a localhost-only binding are containment, not a fix. Never use this configuration in production.
- Verify third-party dependencies, PDF behavior, uploads, all workflows and the catalog in a safe environment. The original archive lacks `vendor/autoload.php`; PDF code may use its fallback.
- Use a sanitized SQL export where possible. Never commit the original SQL, generated runtime directory, database volume or credentials.
- Requires the Docker Hub PHP and MariaDB images and PHP extension compilation during build. This was not tested in an environment with Docker available.
