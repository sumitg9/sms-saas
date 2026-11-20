# Production Monitoring & Observability Guide
**School ERP & Multi-Tenant SaaS Platform**

This guide outlines the monitoring architecture, alerting thresholds, and observability runbooks for the production application.

---

## 1. Observability Architecture

```
                       [Telemetry Ingestion]
                                │
          ┌─────────────────────┼─────────────────────┐
          ▼                     ▼                     ▼
  [Sentry APM / Tracing]  [BetterUptime / Pingdom]  [Supabase Metrics]
  - Frontend Client Errors - API Health Endpoint    - Active Connections
  - Backend Exceptions     - SSL Expiry Checks      - Cache Hit Ratio
  - Unhandled Rejections   - 60s Global Probes      - Query Latency
```

---

## 2. API Health Check Endpoint

An automated synthetic uptime monitor (e.g. Datadog, BetterUptime, UptimeRobot) must probe the API health endpoint every 60 seconds:

```http
GET https://api.example.com/api/v1/health
```

### Expected Response:
```json
{
  "success": true,
  "message": "School ERP SaaS API is operational",
  "version": "v1",
  "timestamp": "2026-11-20T19:30:00+00:00"
}
```

### Alert Conditions:
- **HTTP Status != 200** for 2 consecutive checks -> **P1 Alert (PagerDuty)**.
- **Latency > 1500ms** -> **P2 Warning (Slack Channel)**.

---

## 3. Application Error Tracking (Sentry)

### Backend Sentry Integration:
Configure in `backend/.env`:
```ini
SENTRY_LARAVEL_DSN=https://YOUR_KEY@o0.ingest.sentry.io/PROJECT_ID
SENTRY_TRACES_SAMPLE_RATE=0.2
```

### Frontend Sentry Integration:
Configure in `frontend/.env.production`:
```ini
NEXT_PUBLIC_SENTRY_DSN=https://YOUR_KEY@o0.ingest.sentry.io/PROJECT_ID
```

### Error Filtering Rules:
- Sentry automatically filters benign `404 Not Found` and `401 Unauthorized` user errors.
- Unhandled `500 Server Error`, database connection failures, and unhandled Promise rejections trigger immediate Slack alerts to `#engineering-alerts`.

---

## 4. Supabase Database Metrics & Alerts

Monitor the following metrics in the Supabase Dashboard:

| Metric | Normal Range | Alert Threshold | Remediation Action |
| :--- | :---: | :---: | :--- |
| **Connection Pool Utilization** | < 60% | > 85% | Increase pool size or scale Supabase compute tier |
| **PostgreSQL Cache Hit Ratio** | > 95% | < 90% | Audit missing indexes; run `ANALYZE` on high-traffic tables |
| **Disk I/O Budget** | > 80% | < 20% | Check for unindexed sequential scans; upgrade IOPS |
| **CPU Utilization** | < 40% | > 80% (5 mins) | Identify slow queries in `pg_stat_statements` |

---

## 5. Background Queue Monitoring (Redis)

Monitor the status of asynchronous background jobs via CLI or automated cron:

```bash
# Check queue backlog
php artisan queue:monitor redis:default,redis:notifications --max=50

# Inspect failed jobs
php artisan queue:failed

# Retry failed jobs after resolving transient network issues
php artisan queue:retry all
```

### Alert Thresholds:
- **Failed Jobs Count > 10:** Send warning to `#devops-alerts`.
- **Queue Backlog > 500:** Scale supervisor workers from 4 to 8 instances.

---

## 6. Security & Audit Log Monitoring

The platform maintains an immutable audit trail in the `audit_logs` table:
- Track failed login attempts per IP: `SELECT count(*) FROM login_histories WHERE is_successful = false AND created_at > now() - interval '1 hour';`
- Review tenant switch events for unauthorized attempts.
- Monitor payment webhook events for signature mismatch warnings (`400 Bad Request` on `/api/v1/webhooks/razorpay`).

