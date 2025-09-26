
# Backend Development Challenge - Laravel (Multi-Database Tenancy + Modular Architecture)

## 1. Architecture Overview

We will follow a **modular + layered architecture** for clear separation of concerns and maintainability.

- **Modules**: Each domain feature (Auth, Users, Analytics, Orgs, Webhooks) is isolated in its own module.
- **Controllers**: Handle HTTP requests, return responses, and delegate work to services.
- **Services**: Business logic orchestration between repositories and external integrations.
- **Repositories**: Database access logic (Eloquent, Query Builder).
- **Requests**: Form Request classes for validation.

**Folder structure (Modular):**
```
app/Modules/
 ├── Auth/
 │    ├── Controllers/
 │    ├── Services/
 │    ├── Repositories/
 │    ├── Requests/
 ├── Users/
 ├── Tenant/
 ├── Analytics/
 ├── Webhooks/
```

---

## 2. Multi-Tenancy (Multi-Database)

We will use [`stancl/tenancy`](https://tenancyforlaravel.com/) with **multi-database mode**.

- **Each tenant (organization)** will have its own database.  
- The `tenants` table (central DB) stores tenant metadata and DB connection info.  
- Migrations are run per-tenant DB to provision tenant-specific schema.

**Central Migration (tenants table):**
```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('database')->unique();
    $table->timestamps();
});
```

**Tenant DB Migration Example:**
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->timestamps();
});
```

**Tenant Identification:**
- By subdomain (`org1.example.com`, `org2.example.com`) or domain mapping.
- The middleware from stancl/tenancy switches DB connection dynamically per request.

---

## 3. Authentication & Accounts

- **JWT Auth**: Stateless, using `tymon/jwt-auth`.
- **Email Verification**: Must verify before login.
- **2FA (TOTP + Backup Codes)**: Encrypted secrets per tenant DB.
- **Brute-force Protection**: Rate-limiting per tenant/user basis.
- **Passwordless Magic Link**: Token stored in tenant DB, one-time use.
- **Idempotency Keys**: Global middleware, stored in Redis.

---

## 4. RBAC (Roles & Permissions)

Each tenant DB manages its own roles & permissions using `spatie/laravel-permission`.

- **Roles per tenant**: owner, admin, member, auditor.  
- **Permissions**: users.read, users.update, users.delete, users.invite, analytics.read.  
- **Invitations**: Tenant DB table `invitations` stores invite tokens.

---

## 5. User Lifecycle & GDPR

- **Soft Delete & Restore** per tenant DB.  
- **GDPR Export**: Per-tenant job exports user data from tenant DB.  
- **GDPR Delete Requests**: Stored in tenant DB, require approval by org owner/admin.

---

## 6. Login Analytics

Each tenant DB has its own analytics tables:

- `login_events`
- `login_daily`

**Flow:**
1. On login → update user.last_login_at + login_count.  
2. Queue job writes to `login_events` (tenant DB).  
3. Nightly job aggregates → `login_daily`.  

Endpoints are tenant-aware and query tenant DB only.

---

## 7. Querying & Pagination

- Cursor pagination in each tenant DB.  
- RSQL-like filters validated against whitelisted fields.  
- Sparse fieldsets (`?fields=id,name,email`).  
- Includes (`?include=roles`).  

---

## 8. Consistency & Concurrency

- **Optimistic Locking**: version column in tenant DB.  
- **Eventual Consistency**: login_events is source of truth, login_daily updated nightly.

---

## 9. Webhooks & Integrations

- **Outbound**: Tenant-specific webhook secrets (HMAC-SHA256).  
- **Inbound (Org Provisioning)**:  
  - Central DB endpoint creates tenant entry (`tenants` table).  
  - New tenant DB is provisioned and migrations executed.  
  - Owner account created in tenant DB.

---

## 10. Security

- CORS strict configuration.  
- JSON-only API responses.  
- Rate limiting per IP and per tenant.  
- Secrets from `.env`.  
- Audit logs in each tenant DB.  
- Form Request validation + error envelope.

---

## Example Flow: Register User (Multi-DB + Modular)

1. **Controller** (`Auth/Controllers/RegisterController`)
   - Validates request using `Auth/Requests/RegisterRequest`
   - Calls `Auth/Services/RegisterService`
2. **Service** (`RegisterService`)
   - Creates user via `UserRepository` in tenant DB
   - Dispatches email verification job
3. **Repository** (`UserRepository`)
   - Encapsulates tenant DB queries for User model

---

## Bonus

- **Search**: Full-text per tenant DB (MySQL fulltext or Meilisearch).  
- **Saga pattern**: For multi-step tenant provisioning with rollback.  
- **API Keys**: Tenant-specific API keys with scopes.  
- **Per-tenant rate metrics**: Logged in tenant DB.  
- **Localization**: Multi-language responses with `Accept-Language`.  
- **Load testing**: Provide K6/Gatling scripts.  
