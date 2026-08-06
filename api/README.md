# Prompt Doom PHP API

1. Copy `.env.example` to `.env` and update the MySQL values.
2. Import `database/schema.sql` in phpMyAdmin or run: `mysql -u root < database/schema.sql`.
3. Open `http://localhost:8080/prompt-doom/admin/setup.php` to create the first administrator.
4. Open `http://localhost:8080/prompt-doom/admin/` for the admin console.

The versioned API base URL is `http://localhost:8080/prompt-doom/api/v1`.

Set `APP_URL` to the development computer's LAN address when the API is used by a physical mobile device. Image and thumbnail URLs are generated from this value, for example `http://192.168.0.158:8080/prompt-doom/api`.

## API documentation

- [Mobile/User API](MOBILE_API.md)

## Admin web structure

- `../admin/components/auth/` contains authentication views.
- `../admin/components/layout/` contains the reusable document head, sidebar, header, and admin shell.
- `../admin/assets/js/core/` contains API, session state, and shared data stores.
- `../admin/assets/js/components/` contains reusable interface helpers.
- `../admin/assets/js/pages/` contains one module for each admin feature page.
- `../admin/assets/js/app.js` is the application bootstrap and navigation coordinator.
