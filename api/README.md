# Prompt Doom PHP API

1. Copy `.env.example` to `.env` and update the MySQL values.
2. Import `database/schema.sql` in phpMyAdmin or run: `mysql -u root < database/schema.sql`.
3. Run `php database/seed.php` to create/reset the local administrator.
4. Open `http://localhost/prompt-doom/` for the admin console.

The versioned API base URL is `http://localhost/prompt-doom/api/api/v1`. For compatibility, `http://localhost/prompt-doom/api/v1` is also accepted.

## Admin web structure

- `components/auth/` contains authentication views.
- `components/layout/` contains the reusable document head, sidebar, header, and admin shell.
- `assets/js/core/` contains API, session state, and shared data stores.
- `assets/js/components/` contains reusable interface helpers.
- `assets/js/pages/` contains one module for each admin feature page.
- `assets/js/app.js` is the small application bootstrap and navigation coordinator.
