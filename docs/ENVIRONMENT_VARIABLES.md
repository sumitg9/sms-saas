# Environment Variables Documentation
**School ERP & Multi-Tenant SaaS Platform**

This document details all environment variables used by the Laravel backend and Next.js frontend applications in production.

---

## 1. Backend Environment Variables (`backend/.env`)

### 1.1 Core Application
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `APP_NAME` | Yes | `"School ERP SaaS"` | System display name |
| `APP_ENV` | Yes | `production` | Set strictly to `production` in live environments |
| `APP_KEY` | Yes | *None* | 32-character AES-256 encryption key generated via `php artisan key:generate` |
| `APP_DEBUG` | Yes | `false` | Must be `false` to avoid exposing stack traces and credentials |
| `APP_URL` | Yes | *None* | Base URL of the API gateway (e.g. `https://api.example.com`) |
| `FRONTEND_URL` | Yes | *None* | Main URL of the school portal UI (e.g. `https://app.example.com`) |
| `PLATFORM_URL` | Optional | *None* | SaaS Super Admin portal URL (e.g. `https://platform.example.com`) |
| `APP_TIMEZONE` | Yes | `UTC` | Recommended `UTC` for database timestamps |

### 1.2 Database & Supabase PostgreSQL
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `DB_CONNECTION` | Yes | `pgsql` | Must be `pgsql` |
| `DB_HOST` | Yes | *None* | Supabase host (e.g. `aws-0-ap-south-1.pooler.supabase.com`) |
| `DB_PORT` | Yes | `6543` | `6543` for Transaction Pooler; `5432` for Session Pooler/Direct |
| `DB_DATABASE` | Yes | `postgres` | Default Supabase database name |
| `DB_USERNAME` | Yes | *None* | Supabase username (e.g. `postgres.YOUR_PROJECT_REF`) |
| `DB_PASSWORD` | Yes | *None* | Strong database user password |
| `DB_SSLMODE` | Yes | `require` | Enforces TLS 1.3 encrypted connections |
| `DB_EMULATE_PREPARES`| Yes | `true` | Set `true` when using Supabase transaction connection pooling |

### 1.3 Session & Cross-Subdomain Authentication
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `SESSION_DRIVER` | Yes | `redis` | Uses Redis for fast, clustered session storage |
| `SESSION_LIFETIME` | Yes | `120` | Session lifetime in minutes |
| `SESSION_DOMAIN` | Yes | `.example.com` | **Critical:** Leading dot allows session cookie sharing across `app.example.com`, `platform.example.com`, and `*.example.com` |
| `SESSION_SECURE_COOKIE` | Yes | `true` | Requires HTTPS before sending session cookies |
| `SESSION_SAME_SITE` | Yes | `lax` | Balances CSRF protection and cross-subdomain navigation |

### 1.4 Laravel Sanctum & CORS
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `SANCTUM_STATEFUL_DOMAINS` | Yes | *None* | Comma-separated domains treated as stateful SPAs: `app.example.com,platform.example.com,.example.com` |
| `SANCTUM_EXPIRATION` | Yes | `10080` | Personal access token lifetime in minutes (10080 = 7 days) |
| `CORS_ALLOWED_ORIGINS` | Yes | *None* | Comma-separated allowed origins (e.g. `https://app.example.com,https://platform.example.com`) |
| `CORS_ALLOWED_ORIGINS_PATTERNS` | Yes | *None* | Regex pattern matching school subdomains: `#^https:\/\/[a-z0-9\-]+\.example\.com$#` |

### 1.5 Redis (Cache, Queues & Locks)
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `REDIS_CLIENT` | Yes | `phpredis` | High-performance C extension for PHP |
| `REDIS_HOST` | Yes | `127.0.0.1` | Redis server hostname or managed cluster endpoint |
| `REDIS_PASSWORD` | Yes | *None* | Redis authentication password |
| `REDIS_PORT` | Yes | `6379` | Redis TCP port |
| `QUEUE_CONNECTION` | Yes | `redis` | Asynchronous job processing |
| `CACHE_STORE` | Yes | `redis` | Distributed caching driver |

### 1.6 Storage & S3 Configuration (Supabase Storage)
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `FILESYSTEM_DISK` | Yes | `s3` | S3-compatible object store driver |
| `AWS_ACCESS_KEY_ID` | Yes | *None* | Supabase Storage S3 access key |
| `AWS_SECRET_ACCESS_KEY` | Yes | *None* | Supabase Storage S3 secret key |
| `AWS_DEFAULT_REGION` | Yes | `ap-south-1` | Supabase project region |
| `AWS_BUCKET` | Yes | *None* | Name of the bucket (e.g. `school-erp-documents`) |
| `AWS_ENDPOINT` | Yes | *None* | S3 endpoint URL (e.g. `https://REF.supabase.co/storage/v1/s3`) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Yes | `true` | Required for Supabase S3 compatibility |

### 1.7 Payment & Communications
| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `RAZORPAY_KEY_ID` | Yes | *None* | Live Razorpay key ID (`rzp_live_...`) |
| `RAZORPAY_KEY_SECRET` | Yes | *None* | Live Razorpay API secret |
| `RAZORPAY_WEBHOOK_SECRET` | Yes | *None* | Secret key configured in Razorpay webhook settings |
| `MAIL_MAILER` | Yes | `smtp` | Mail transport driver |
| `MAIL_HOST` | Yes | *None* | SMTP server (e.g. Amazon SES / Resend) |
| `MAIL_USERNAME` | Yes | *None* | SMTP username |
| `MAIL_PASSWORD` | Yes | *None* | SMTP password |

---

## 2. Frontend Environment Variables (`frontend/.env.production`)

All frontend variables intended for browser consumption must carry the `NEXT_PUBLIC_` prefix:

| Variable | Mandatory | Default | Description & Production Value |
| :--- | :---: | :--- | :--- |
| `NEXT_PUBLIC_API_URL` | Yes | *None* | Full API base URL (e.g. `https://api.example.com/api/v1`) |
| `NEXT_PUBLIC_APP_URL` | Yes | *None* | Production URL of the main application (e.g. `https://app.example.com`) |
| `NEXT_PUBLIC_PLATFORM_URL` | Yes | *None* | Production URL of the SaaS Super Admin app (e.g. `https://platform.example.com`) |
| `NEXT_PUBLIC_ROOT_DOMAIN` | Yes | `example.com` | Base domain for tenant subdomain generation |
| `NEXT_PUBLIC_RAZORPAY_KEY_ID` | Yes | *None* | Live Razorpay key ID for client-side checkout modal |
| `NEXT_PUBLIC_SENTRY_DSN` | Optional | *None* | Sentry client DSN for frontend crash and error monitoring |

---

## 3. Secret Management Best Practices

1. **Never Commit Secrets:** Do not commit `.env`, `.env.production`, or private keys to Git. Both `.env` files are ignored in `.gitignore`.
2. **Secret Store Integration:** In cloud environments (AWS, GCP, DigitalOcean), inject secrets via AWS Secrets Manager, Doppler, or platform environment variables.
3. **Key Rotation Schedule:** Rotate `APP_KEY`, database passwords, and API secrets semi-annually or immediately following any developer offboarding.

