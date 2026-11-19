# Multi-Tenant School Management System (School ERP SaaS)

[![Next.js](https://img.shields.io/badge/Frontend-Next.js%2015-black.svg?style=flat-square&logo=next.js)](https://nextjs.org)
[![Tailwind CSS](https://img.shields.io/badge/CSS-Tailwind%20CSS-38bdf8.svg?style=flat-square&logo=tailwind-css)](https://tailwindcss.com)
[![shadcn/ui](https://img.shields.io/badge/UI-shadcn%2Fui-black.svg?style=flat-square)](https://ui.shadcn.com)
[![Laravel](https://img.shields.io/badge/Backend-Laravel%2011-ff2d20.svg?style=flat-square&logo=laravel)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/Database-Supabase%20Postgres-3ecf8e.svg?style=flat-square&logo=supabase)](https://supabase.com)
[![Redis](https://img.shields.io/badge/Cache%20%26%20Queues-Redis-dc382d.svg?style=flat-square&logo=redis)](https://redis.io)
[![Pest](https://img.shields.io/badge/Testing-Pest%20PHP-ff8170.svg?style=flat-square)](https://pestphp.com)

A commercial, multi-tenant B2B School ERP SaaS platform engineered for modern education institutions. Supports single-campus and multi-branch school networks across CBSE, ICSE, State Boards, and International curricula (IB/Cambridge) with board-agnostic configuration engines.

---

## Architecture Highlights

- **Frontend:** Next.js 15 (App Router, React 19, TypeScript), Tailwind CSS, shadcn/ui, React Hook Form, Zod, Recharts. Linear and Stripe-inspired dashboard UX.
- **Backend:** Laravel 11 REST API, PHP 8.2+, Laravel Sanctum, Spatie Laravel Permission, Pest v3 testing.
- **Database & Storage:** Supabase PostgreSQL 15+ with compound discriminator columns (`tenant_id`, `school_id`) and PostgreSQL Row-Level Security (RLS) policies. Private Supabase Storage buckets with short-lived presigned URLs.
- **Infrastructure:** Redis for high-speed caching and prioritized queue processing (Laravel Horizon).

---

## Documentation Index

Comprehensive technical documentation is maintained in the `documentation/` directory:

| Document | Description |
| :--- | :--- |
| **[PRD.md](documentation/PRD.md)** | Product Requirements Document: Vision, 30 modules, subscription tiers, personas, and NFRs. |
| **[ARCHITECTURE.md](documentation/ARCHITECTURE.md)** | System Architecture: High-level topology, multi-tenancy model, dependency and entity flow diagrams. |
| **[DATABASE.md](documentation/DATABASE.md)** | Database Specification: Full PostgreSQL schema, Row-Level Security (RLS), and indexing strategy. |
| **[API_DOCUMENTATION.md](documentation/API_DOCUMENTATION.md)** | REST API Contract: Endpoints, authentication, request/response envelopes, and webhooks. |
| **[DEVELOPMENT_ROADMAP.md](documentation/DEVELOPMENT_ROADMAP.md)** | Development Roadmap: 8 sequential phases (Phase 0 to Phase 7) with quality gates and milestones. |
| **[SECURITY.md](documentation/SECURITY.md)** | Security & Governance: Defense-in-depth, RBAC permissions matrix, FERPA/GDPR compliance, and audit logging. |
| **[LOCAL_DEVELOPMENT.md](documentation/LOCAL_DEVELOPMENT.md)** | Setup & Operations: Local development instructions, environment templates, build, and test commands. |

---

## Repository Structure

```
SMS-SAAS/
├── backend/                   # Laravel 11 REST API application
│   ├── app/
│   │   ├── Actions/           # Domain business actions
│   │   ├── Http/
│   │   │   ├── Controllers/   # REST API Controllers (Platform, School, Portals)
│   │   │   ├── Middleware/    # TenantResolution, CheckSubscriptionStatus, etc.
│   │   │   ├── Requests/      # FormRequest payload validators
│   │   │   └── Resources/     # API Resource response transformers
│   │   ├── Models/            # Eloquent Models with TenantScope
│   │   ├── Policies/          # Granular authorization policies
│   │   └── Services/          # Domain services (Payment, PDF Generator, SMS)
│   ├── database/
│   │   ├── migrations/        # PostgreSQL schema migrations
│   │   └── seeders/           # Initial seeds (Platform admin, Plans, Permissions)
│   ├── routes/
│   │   ├── api.php            # v1 API routes
│   │   └── webhooks.php       # Stripe, Razorpay & Biometric webhooks
│   └── tests/                 # Pest test suites (Feature & Unit)
│
├── frontend/                  # Next.js 15 App Router web application
│   ├── src/
│   │   ├── app/
│   │   │   ├── (auth)/        # Login, 2FA, password recovery routes
│   │   │   ├── (platform)/    # SaaS SuperAdmin control plane
│   │   │   ├── (school)/      # Multi-branch school administrative workspace
│   │   │   └── (portals)/     # Parent, Student, and Teacher portals
│   │   ├── components/        # Reusable UI primitives (shadcn/ui, Data Tables, Charts)
│   │   ├── hooks/             # Custom React hooks (useTenant, useAuth, useDebounce)
│   │   ├── lib/               # API clients, Zod schemas, utility functions
│   │   └── types/             # TypeScript interfaces and API response contracts
│
├── documentation/             # Complete architectural and product documentation
└── README.md                  # Project overview and developer onboarding
```

---

## Getting Started

### Prerequisites
- PHP 8.2 or higher with extensions: `pdo_pgsql`, `redis`, `gd`, `zip`, `bcmath`, `intl`
- Composer 2.x
- Node.js 20+ & npm / pnpm
- Supabase account (or local PostgreSQL 15+ instance with `uuid-ossp`)
- Redis 7.x

### 1. Backend Setup (Laravel 11)
```bash
cd backend
composer install

# Environment Configuration
cp .env.example .env
php artisan key:generate

# Configure Database and Redis credentials in .env:
# DB_CONNECTION=pgsql
# DB_HOST=db.<your-supabase-project>.supabase.co
# DB_PORT=5432
# DB_DATABASE=postgres
# DB_USERNAME=postgres
# DB_PASSWORD=your_secure_password
# REDIS_HOST=127.0.0.1

# Run Migrations & Database Seeders
php artisan migrate --seed

# Start Local API Server
php artisan serve --port=8000
```

### 2. Queue & Scheduler Workers
```bash
# In separate terminal windows:
php artisan queue:work --queue=critical,notifications,reports,default
php artisan schedule:work
```

### 3. Frontend Setup (Next.js 15)
```bash
cd frontend
npm install

# Environment Configuration
cp .env.example .env.local
# Set NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1

# Run Development Server
npm run dev
# Access UI at http://localhost:3000
```

---

## Testing & Quality Assurance

```bash
# Backend Automated Tests (Pest)
cd backend
php artisan test

# Run Static Analysis (PHPStan Level 8)
vendor/bin/phpstan analyse

# Frontend Lint & Typecheck
cd frontend
npm run lint
npm run typecheck
```

---

## License & Commercial Terms
Commercial proprietary Software-as-a-Service (SaaS) product. All rights reserved.

