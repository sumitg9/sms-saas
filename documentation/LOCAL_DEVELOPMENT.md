# Local Development & Environment Setup Guide
## Project: Multi-Tenant School Management System (School ERP SaaS)
**Document Version:** 1.0.0  
**Status:** Validated & Tested  

---

## 1. System Prerequisites

Ensure the following tools and runtimes are installed on your workstation:

| Component | Minimum Version | Verified Active Version |
| :--- | :--- | :--- |
| **PHP** | 8.2+ | 8.2.12 (ZTS x64) |
| **PHP Extensions** | `pdo_pgsql`, `pgsql`, `pdo_sqlite`, `curl`, `mbstring`, `openssl`, `bcmath`, `zip` | Enabled & Verified |
| **Composer** | 2.x | 2.10.2 |
| **Node.js** | 20.x or 22.x | 22.21.1 |
| **npm** | 10.x | 10.9.4 |
| **PostgreSQL** | 15+ (Supabase or Local) | Supabase Cloud / Local PostgreSQL |
| **Redis** | 7.x | 127.0.0.1:6379 |

---

## 2. Backend Setup (Laravel 12 REST API)

### 2.1 Installation & Configuration
```bash
# Navigate to the backend directory
cd backend

# Install PHP dependencies (production & dev)
composer install

# Copy environment template
cp .env.example .env

# Generate application encryption key
php artisan key:generate
```

### 2.2 Environment Variables (`backend/.env`)
Key configuration variables in `backend/.env`:
```ini
APP_NAME="School ERP SaaS"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

# PostgreSQL Database (Supabase or Local Postgres)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=school_erp_saas
DB_USERNAME=postgres
DB_PASSWORD=postgres
DB_SCHEMA=public
DB_SSLMODE=prefer

# Session, Cache & Queues (Redis)
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Sanctum Stateful Domains (CORS-enabled)
SANCTUM_STATEFUL_DOMAINS="localhost,localhost:3000,127.0.0.1,127.0.0.1:3000"

# Supabase Storage & Object Store
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key
SUPABASE_STORAGE_BUCKET_PUBLIC=tenant-assets-public
SUPABASE_STORAGE_BUCKET_PRIVATE=documents-private
```

### 2.3 Database Migrations
```bash
# Run pending migrations
php artisan migrate

# Check migration status
php artisan migrate:status
```

### 2.4 Running the Backend Server
```bash
# Start local API development server on port 8000
php artisan serve --port=8000

# Probe API health check endpoint:
# GET http://localhost:8000/api/v1/health
```

### 2.5 Running Background Queues & Scheduler
```bash
# In separate terminal windows:
# Queue Worker (Prioritized: critical, notifications, reports, default)
php artisan queue:work --queue=critical,notifications,reports,default

# Scheduled Cron Runner
php artisan schedule:work
```

---

## 3. Frontend Setup (Next.js 15 App Router)

### 3.1 Installation & Configuration
```bash
# Navigate to the frontend directory
cd frontend

# Install Node dependencies
npm install

# Configure environment variables
cp .env.example .env.local
```

### 3.2 Environment Variables (`frontend/.env.local`)
```ini
NEXT_PUBLIC_APP_NAME="School ERP SaaS"
NEXT_PUBLIC_APP_URL="http://localhost:3000"

# Backend REST API endpoint with /api/v1 prefix
NEXT_PUBLIC_API_URL="http://localhost:8000/api/v1"

# Supabase Public Configuration
NEXT_PUBLIC_SUPABASE_URL="https://your-project.supabase.co"
NEXT_PUBLIC_SUPABASE_ANON_KEY="your_anon_key"

# Payment Gateways (Client keys)
NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY="pk_test_..."
NEXT_PUBLIC_RAZORPAY_KEY_ID="rzp_test_..."
```

### 3.3 Running the Development Server
```bash
# Run development server on port 3000
npm run dev

# Open in browser:
# http://localhost:3000
```

---

## 4. Build Instructions

### 4.1 Backend Optimization
```bash
cd backend

# Optimize autoload and cache configuration, routes, and events
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4.2 Frontend Production Build
```bash
cd frontend

# Build optimized Next.js production bundle
npm run build

# Start production server
npm run start -p 3000
```

---

## 5. Testing Instructions

### 5.1 Backend Testing (Pest PHP)
All backend tests are written using Pest v3 and PHPUnit 11 with an isolated in-memory SQLite database configuration (`phpunit.xml`).
```bash
cd backend

# Run the full Pest test suite
php artisan test

# Run tests with coverage
php artisan test --coverage
```

### 5.2 Frontend Testing, Typecheck & Linting
```bash
cd frontend

# TypeScript strict type checking (zero errors required)
npm run typecheck

# ESLint code style and quality check
npm run lint

# Prettier format check
npm run format
```

---

## 6. Verification Checklist

- [x] Backend created at `/backend` with Laravel 12 on PHP 8.2+.
- [x] API routes configured with `/api/v1` version prefix.
- [x] Laravel Sanctum installed and initialized with `HasApiTokens`.
- [x] Pest PHP v3 installed and initialized.
- [x] Standardized API response format and RFC 7807 error envelopes configured in `app/Traits/ApiResponse.php` and `bootstrap/app.php`.
- [x] CORS configured for `http://localhost:3000`.
- [x] PostgreSQL database connection configured in `config/database.php` and `.env`.
- [x] Frontend created at `/frontend` with Next.js 15 App Router.
- [x] TypeScript strict mode configured in `tsconfig.json`.
- [x] Tailwind CSS and shadcn/ui design tokens configured in `globals.css` and `src/lib/utils.ts`.
- [x] TanStack Query provider, theme provider, and centralized Axios `apiClient` configured with `X-Tenant-ID` and `Authorization` interceptors.
- [x] Zero lint or typecheck errors across frontend and backend.
- [x] Full automated Pest test suite passing (5 passed, 20 assertions).
- [x] Frontend and Backend live server probe verified.

