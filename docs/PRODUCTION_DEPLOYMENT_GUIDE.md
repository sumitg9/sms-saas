# Production Deployment Guide
**School ERP & Multi-Tenant SaaS Platform**  
**Architecture:** Decoupled Next.js Frontend + Laravel 11 API Backend + Supabase PostgreSQL

---

## 1. System Architecture Overview

The School ERP SaaS application is engineered for **separate, independently scalable frontend and backend deployments**:

```
                       [Clients / Browsers]
                                │
          ┌─────────────────────┼─────────────────────┐
          ▼                     ▼                     ▼
 https://app.example.com  https://platform...   https://school1...
 (School Staff & Portal) (Platform Admin)      (School Subdomain)
          │                     │                     │
          └─────────────────────┴─────────────────────┘
                                │ (Next.js 16 Production Frontend)
                                │ (Hosted on Vercel / AWS Amplify / Standalone Node)
                                │
                                ▼
                   https://api.example.com
                   (Laravel 11 API Gateway)
                   (Hosted on Linux VPS / AWS ECS / Laravel Forge)
                                │
          ┌─────────────────────┼─────────────────────┐
          ▼                     ▼                     ▼
[Supabase PostgreSQL 18] [Redis 7 Cluster]   [Supabase S3 Storage]
- Port 6543 (Pooler)    - Cache, Queues,     - Tenant Documents,
- Port 5432 (Migrations)  Sessions             Report Cards, Logos
- Row-Level Security
```

---

## 2. Domain & DNS Strategy

Configure the following DNS records on your domain registrar (e.g., Cloudflare / Route 53):

| Host / Subdomain | Type | Target | Purpose |
| :--- | :---: | :--- | :--- |
| `app.example.com` | CNAME / A | Frontend deployment (e.g. `cname.vercel-dns.com` or server IP) | Main School ERP application UI |
| `platform.example.com` | CNAME / A | Frontend deployment | SaaS Platform Super Admin dashboard |
| `api.example.com` | A / CNAME | Backend server IP / Load balancer | Laravel REST API & Webhooks |
| `*.example.com` | CNAME | Frontend deployment | Wildcard routing for school subdomains (`school1.example.com`) |
| `custom.example.com` | CNAME | Reverse proxy / Load balancer | CNAME destination for school custom domains |

---

## 3. Frontend Deployment (Next.js 16)

### Option A: Vercel (Recommended)
1. Import repository into Vercel and select root directory `frontend`.
2. Configure **Framework Preset:** `Next.js`.
3. Set **Node.js Version:** `20.x` or `22.x`.
4. Add Production Environment Variables in Vercel Project Settings:
   - `NEXT_PUBLIC_API_URL=https://api.example.com/api/v1`
   - `NEXT_PUBLIC_APP_URL=https://app.example.com`
   - `NEXT_PUBLIC_PLATFORM_URL=https://platform.example.com`
   - `NEXT_PUBLIC_ROOT_DOMAIN=example.com`
   - `NEXT_PUBLIC_RAZORPAY_KEY_ID=rzp_live_...`
   - `NEXT_PUBLIC_SENTRY_DSN=...`
5. Set Custom Domains:
   - Add `app.example.com`
   - Add `platform.example.com`
   - Add Wildcard domain `*.example.com`
6. Trigger deployment (`git push main` or manual deploy).

### Option B: Standalone Node.js / Docker on Linux VPS
1. On the server:
   ```bash
   cd frontend
   npm ci
   npm run build
   ```
2. Run with PM2:
   ```bash
   pm2 start npm --name "school-erp-frontend" -- start -- -p 3000
   pm2 save
   pm2 startup
   ```
3. Configure Nginx reverse proxy pointing `app.example.com` and `*.example.com` to `http://127.0.0.1:3000`.

---

## 4. Backend Deployment (Laravel 11)

### 4.1 Server Requirements
- **OS:** Ubuntu 22.04 LTS / Debian 12
- **PHP:** PHP 8.3 or 8.4 with modules:
  `php8.3-fpm`, `php8.3-pgsql`, `php8.3-redis`, `php8.3-mbstring`, `php8.3-xml`, `php8.3-curl`, `php8.3-zip`, `php8.3-gd`, `php8.3-bcmath`
- **Composer:** Version 2.7+
- **Redis Server:** Redis 7+
- **Web Server:** Nginx 1.24+ with SSL (Let's Encrypt / Certbot)

### 4.2 Deployment Commands
```bash
# 1. Clone & install dependencies
cd /var/www/school-erp/backend
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Configure environment
cp .env.production.example .env
# Edit .env with your Supabase credentials, app key, and Redis password
nano .env

# 3. Generate key & run database migrations on Supabase
php artisan key:generate --force
php artisan migrate --force

# 4. Storage & optimize caches
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Set directory permissions
chown -R www-data:www-data /var/www/school-erp/backend/storage /var/www/school-erp/backend/bootstrap/cache
chmod -R 775 /var/www/school-erp/backend/storage /var/www/school-erp/backend/bootstrap/cache
```

### 4.3 Supervisor Worker Configuration for Redis Queues
Create `/etc/supervisor/conf.d/school-erp-worker.conf`:
```ini
[program:school-erp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/school-erp/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/school-erp-worker.log
```
Reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 4.4 Crontab for Laravel Scheduler
Add to system crontab (`sudo crontab -u www-data -e`):
```cron
* * * * * cd /var/www/school-erp/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 4.5 Nginx Server Block for API (`api.example.com`)
```nginx
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate /etc/letsencrypt/live/api.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.example.com/privkey.pem;

    root /var/www/school-erp/backend/public;
    index index.php;

    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 5. Supabase PostgreSQL Integration

### 5.1 Connection Pooling Setup
Supabase provides two connection strings:
1. **Direct Connection (Port 5432):** Use for running `php artisan migrate --force` and schema modifications.
2. **PgBouncer Connection Pooler (Port 6543, Transaction Mode):** Use for live PHP-FPM web requests to handle high concurrency.

In `backend/.env`:
```ini
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-south-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF
DB_PASSWORD=YOUR_PASSWORD
DB_SSLMODE=require
DB_EMULATE_PREPARES=true
```

---

## 6. Deployment Smoke Testing Runbook

Execute these automated smoke checks immediately after deployment:

1. **API Health Probe:**
   ```bash
   curl -I https://api.example.com/api/v1/health
   # Expected: HTTP/2 200 OK
   ```
2. **CORS Validation:**
   ```bash
   curl -I -X OPTIONS https://api.example.com/api/v1/health \
     -H "Origin: https://app.example.com" \
     -H "Access-Control-Request-Method: GET"
   # Expected: access-control-allow-origin: https://app.example.com
   # Expected: access-control-allow-credentials: true
   ```
3. **Frontend Availability:**
   ```bash
   curl -I https://app.example.com
   # Expected: HTTP/2 200 OK
   ```
4. **Queue Worker Heartbeat:**
   ```bash
   php artisan queue:monitor redis:default
   ```

