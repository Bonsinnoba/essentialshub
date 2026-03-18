# ElectroCom Production Deployment Guide

This guide provides the necessary steps to deploy the **ElectroCom Decoupled Ecosystem** to a production environment. 

---

## 🏗️ 1. Infrastructure Requirements

- **PHP 8.1+** with `pdo_mysql`, `openssl`, and `json` extensions.
- **MySQL 8.0+** or MariaDB equivalent.
- **Web Server**: Nginx (Recommended) or Apache.
- **SSL Certificate**: Required for secure authentication and Ghana Card data transmission.
- **Node.js**: Required only for building the frontend artifacts locally.

---

## 🔑 2. Backend Configuration (`api/.env.php`)

Create a production `.env.php` file on your server. **DO NOT** use development keys.

| Key | Production Requirement |
|:---|:---|
| `APP_ENV` | Must be set to `'production'`. |
| `ALLOWED_ORIGINS` | List only your live `https` domains. |
| `JWT_SECRET` | Generate a 64+ character random string. |
| `PASSWORD_PEPPER` | Generate a unique random string. |
| `SMTP_HOST` | Your live SMTP server (e.g., `smtp.gmail.com`). |
| `SMTP_USER` | Production email account. |
| `SMTP_PASS` | Production email password. |
| `HUBTEL_CLIENT_ID` | Production API credentials for Ghana SMS. |
| `HUBTEL_CLIENT_SECRET` | Production API credentials for Ghana SMS. |
| `DB_NAME` | Set to `electrocom`. |
| `MAIL_FROM` | Set to `no-reply@electrocom.com`. |

> [!IMPORTANT]
> Ensure all development-only scripts and `check_*.php` files are removed before go-live.

---

## 🌐 3. Frontend Configuration (`.env.production`)

Both the **Storefront** and **Admin Panel** rely on environment variables during the build process.

1. Navigate to both `/storefront` and `/admin-panel`.
2. Edit `.env.production`:
   ```bash
   VITE_API_BASE_URL=https://api.electrocom.com/api
   ```
3. Run the production build:
   ```bash
   npm run build
   ```
4. Upload the generated `dist/` folders to your production web server.

---

## 🛡️ 4. Server Security & Routing

### Nginx Configuration (SPA Routing)
To prevent 404 errors on page refresh, redirect all non-file requests to `index.html`.

```nginx
server {
    listen 443 ssl;
    server_name electrocom.com;
    root /var/www/electrocom/storefront/dist;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Proxy or direct alias to PHP API
    location /api {
        alias /var/www/electrocom/api;
        # Standard PHP-FPM configuration here...
    }
}
```

### File System Permissions
- **`/api/uploads`**: Must be writable by the web user (`www-data`).
- **`/api/data`**: Must be writable for persisting global settings.
- **`.env.php`**: Must be readable only by the web user, **never** publicly accessible.

---

## 🚀 5. Final Launch Checklist

- [ ] **SSL/TLS**: Verify that all traffic is forced to HTTPS.
- [ ] **Diagnostic Cleanup**: Ensure no `check_*.php` or `test_*.php` scripts remain in the API directory.
- [ ] **Database**: Run `api/schema.sql` to initialize the production tables.
- [ ] **Brand Assets**: Verify that `logo.png` and favicons reflect the ElectroCom brand in `/public`.
- [ ] **Third-Party**: Update Redirect URIs in Google/Facebook developer consoles to match your new production domain.

---

*Last Updated: March 2026*
