# Great Solomon CT4 — Microservices Architecture

The project keeps the existing PHP pages as the **web/API gateway UI**, while business and data operations are separated into service classes under `services/`.

## Services

1. **Health & Safety Service** — owns safety incidents and health records.
2. **Legal & Compliance Service** — owns compliance obligations and audits.
3. **Administration & Security Service** — owns user administration, login history views, and security/audit views.
4. **Asset & Equipment Service** — owns asset registry, issuance, and return transactions.
5. **Reports Service** — read-only aggregator that connects the four business services for the main dashboard.
6. **Audit Service** — shared event/audit capability used by business services so module actions continue to appear in the dashboard and audit trail.

## Connection flow

Browser -> PHP UI / Gateway -> Module Service -> MySQL-owned tables

Reports Dashboard -> Reports Service -> Health / Legal / Admin / Asset Services -> MySQL

Module Service -> Audit Service -> Audit Log -> Reports Dashboard

The UI pages no longer contain their own SQL for module CRUD operations. They call the service layer through `includes/service_client.php`.

## JSON API gateway

Authenticated JSON endpoints are available through:

`services/api/index.php?service=<service>&action=<operation>`

Examples of service names: `health`, `legal`, `admin`, `assets`, `reports`.

The default local deployment uses in-process service calls for maximum compatibility with XAMPP/WAMP and shared hosting. Service boundaries are isolated in their own classes, so they can later be moved to separate hosts/containers while keeping the UI contract stable.

## Reliability changes

- Asset issuance and returns use database transactions and row locks to prevent double issuance.
- Service actions validate allowed status/role values before writing.
- Existing session authentication is preserved.
- Existing module audit activity is preserved through the Audit Service.
- Existing dashboard, login/OTP, logo, roles, activate/deactivate buttons, forms, and navigation are retained.
