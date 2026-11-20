# Production Rollback Guide
**School ERP & Multi-Tenant SaaS Platform**

This guide provides procedures for executing rollbacks of the Next.js frontend, Laravel API backend, and Supabase database.

---

## 1. Emergency Maintenance Mode

If a release causes critical application errors, activate maintenance mode immediately to protect data integrity while investigating:

```bash
cd /var/www/school-erp/backend

# Activate maintenance mode with a secret bypass key for engineering team
php artisan down --secret="emergency-deploy-bypass-token" --render="errors.503"
```
- Engineers can access the application by visiting: `https://api.example.com/emergency-deploy-bypass-token`
- All other user traffic receives a clean HTTP 503 Maintenance response.

To bring the application back online after rollback:
```bash
php artisan up
```

---

## 2. Frontend Rollback Procedure (Next.js)

### Scenario A: Vercel Deployments (Instant Rollback)
1. Open the **Vercel Dashboard** -> Project -> **Deployments**.
2. Locate the previous stable deployment (e.g., from prior release).
3. Click the three dots `...` next to the deployment -> Select **Instant Rollback**.
4. Confirm rollback. Traffic routes to the previous build in < 10 seconds with zero downtime.

### Scenario B: Linux VPS / Standalone Deployments
```bash
cd /var/www/school-erp/frontend

# 1. Checkout previous release Git tag
git fetch --tags
git checkout tags/v1.0.X

# 2. Re-install exact locked dependencies & build
npm ci
npm run build

# 3. Reload PM2 process cluster
pm2 reload school-erp-frontend --update-env
```

---

## 3. Backend Rollback Procedure (Laravel API)

```bash
cd /var/www/school-erp/backend

# 1. Checkout previous stable release tag
git fetch --tags
git checkout tags/v1.0.X

# 2. Install previous dependency state
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Refresh framework caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Restart queue workers and PHP-FPM
sudo supervisorctl restart all
sudo systemctl reload php8.3-fpm
```

---

## 4. Database Rollback Guidelines

### Rule 1: Forward-Fix Preferred Over Destructive Rollbacks
In production database environments:
- **Never rollback migrations that drop columns or tables.**
- If a migration added a column or index that caused issues, roll back specifically that migration step:
  ```bash
  php artisan migrate:rollback --step=1 --force
  ```
- If bad data was inserted, restore the affected tenant data from the pre-migration Supabase snapshot rather than rolling back global tables.

---

## 5. Post-Rollback Verification Checklist

After executing a rollback:
- [ ] Verify `https://api.example.com/api/v1/health` returns HTTP 200.
- [ ] Confirm frontend pages load without JavaScript console errors.
- [ ] Verify queue workers are consuming jobs: `php artisan queue:monitor redis:default`.
- [ ] Ensure `php artisan down` has been cleared via `php artisan up`.
- [ ] Notify stakeholders and document incident in post-mortem log.

