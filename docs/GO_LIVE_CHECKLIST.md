# Production Go-Live Checklist
**School ERP & Multi-Tenant SaaS Platform**

This checklist outlines the mandatory steps required prior to, during, and immediately following production deployment and DNS switchover.

---

## Phase 1: Pre-Flight Verification (T-48 Hours)

### 1.1 Infrastructure & Environment
- [ ] Production Supabase PostgreSQL database provisioned in designated geographic region.
- [ ] Database Point-In-Time Recovery (PITR) verified active.
- [ ] Redis 7 production cluster provisioned with authentication enabled.
- [ ] Production email gateway (Amazon SES / Resend) domain identity verified (DKIM, SPF, DMARC).
- [ ] Live Razorpay account activated; webhook URL (`https://api.example.com/api/v1/webhooks/razorpay`) configured.
- [ ] Sentry error monitoring projects created for frontend and backend.

### 1.2 Security & Secrets
- [ ] Confirm `.env` and `.env.production` are strictly excluded from version control (`.gitignore`).
- [ ] Generate fresh production encryption key: `php artisan key:generate`.
- [ ] Set `APP_DEBUG=false` on backend and verify no stack traces leak on errors.
- [ ] Enforce Sanctum token TTL: `SANCTUM_EXPIRATION=10080` (7 days).
- [ ] Verify `SESSION_DOMAIN=.example.com` and `SESSION_SECURE_COOKIE=true` for cross-subdomain authentication.

### 1.3 Code Quality & Tests
- [ ] Run full backend test suite (`php artisan test`): **197/197 passing**.
- [ ] Run production Next.js build (`npm run build`): **24/24 static routes prerendered**.
- [ ] Verify Row-Level Security policies active across all 137 tenant tables.

---

## Phase 2: Deployment Day Execution (T-0)

### Step 1: Backend Deployment
1. [ ] Deploy Laravel backend code to production server.
2. [ ] Inject production `.env` securely.
3. [ ] Run `composer install --no-dev --optimize-autoloader`.
4. [ ] Run schema migrations: `php artisan migrate --force`.
5. [ ] Optimize framework caches:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```
6. [ ] Start Supervisor queue workers: `sudo supervisorctl start all`.
7. [ ] Confirm crontab scheduler active: `* * * * * php /var/www/school-erp/backend/artisan schedule:run`.

### Step 2: Frontend Deployment
1. [ ] Deploy Next.js frontend to production hosting platform.
2. [ ] Inject environment variables (`NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_APP_URL`, etc.).
3. [ ] Verify static build compiles without errors.
4. [ ] Bind domains: `app.example.com`, `platform.example.com`, and `*.example.com`.

### Step 3: DNS Switchover (Requires Explicit Stakeholder Approval)
> [!CAUTION]
> Do NOT switch DNS records or migrate live customer data without explicit approval from the project lead.

1. [ ] Switch DNS A/CNAME records for:
   - `app.example.com`
   - `api.example.com`
   - `platform.example.com`
   - `*.example.com`
2. [ ] Verify SSL/TLS certificates issued and valid on all hostnames.

---

## Phase 3: Post-Launch Smoke Tests (T+1 Hour)

Execute these smoke tests in the live environment:

- [ ] **Health Probe:** Verify `https://api.example.com/api/v1/health` returns HTTP 200.
- [ ] **School Onboarding:** Register a test school via `https://app.example.com/login` (registration link); confirm tenant, branch, and admin account are created.
- [ ] **Login & 2FA:** Authenticate as school administrator; confirm session cookie and JWT Bearer token are issued.
- [ ] **Student Admission:** Submit an admission application and approve it; verify admission number generation.
- [ ] **Attendance Marking:** Mark attendance on `/attendance` for a test section; confirm instant roster update and zero duplicate rows.
- [ ] **Fee Payment Simulation:** Create a fee invoice and initiate payment; confirm receipt numbering.
- [ ] **Super Admin Access:** Log in at `https://platform.example.com` as Super Admin; verify tenant listing and SaaS metrics load.
- [ ] **Cross-Tenant Access Test:** Confirm Tenant A credentials cannot view or query Tenant B data (HTTP 403 / 404).

---

## Phase 4: Ongoing Monitoring (First 48 Hours)

- [ ] Monitor Sentry dashboard for any uncaught exceptions.
- [ ] Monitor Supabase connection pool depth (Port 6543).
- [ ] Monitor Redis queue depth: `php artisan queue:monitor redis:default`.
- [ ] Review failed jobs table: `php artisan queue:failed`.
- [ ] Confirm first scheduled automated backup completes successfully.

