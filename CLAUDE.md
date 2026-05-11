# Vitals — personal server monitoring dashboard

A self-hosted server monitoring and threat intelligence dashboard for a single Linux server.
Built for personal use, runs on the same server it monitors.

Live at: `vitals.martybuilds.dev`
Repo: github.com/MartynasJank/Vitals

---

## Project overview

A real-time dashboard with full visibility into server health, sites, databases, security,
honeypot activity, and threat intelligence. Lightweight personal alternative to Laravel Forge
or Ploi, self-hosted and built from scratch.

---

## Tech stack

- **Laravel 13** + **PHP 8.4**
- **MySQL** — app database and the monitored target service (same instance)
- **Livewire 3** — real-time UI, polling for live stats
- **Tailwind CSS v4** — dark theme styling
- **Alpine.js** — small interactive bits
- **Chart.js** — CPU/RAM history and attack volume charts

Do not use Vue, React, or Inertia. Keep it Livewire + Blade.

The app uses MySQL for its own data despite also monitoring MySQL — they share the same instance.

---

## Server context

The app monitors the server it runs on:
- OS: Ubuntu 24.04
- Server: Hetzner CPX32 (4 vCPU, 8GB RAM, 160GB SSD)
- IP: 162.55.219.28
- Sites running: martybuilds.dev, moviepicker.martybuilds.dev, tiktokshuffle.martybuilds.dev
- Services: Nginx, MySQL, PHP 8.4-FPM, Fail2ban, Cowrie SSH honeypot

---

## Architecture & code style

Follow standard Laravel conventions. Keep code clean, readable and consistent.

### Service classes
All business logic lives in `app/Services/`. Livewire components stay thin — they call
services and pass data to views, nothing more.

Existing services:
- `ServerService` — shell commands for CPU, RAM, disk, processes
- `SiteService` — HTTP uptime checks
- `LogService` — reading and parsing log files
- `DatabaseService` — MySQL stats queries
- `SecurityService` — fail2ban, SSH log parsing, firewall rules
- `ThreatIntelService` — all threat intel queries (top countries, ISPs, ASNs, attack volume, IP profiles)
- `MalwareFileService` — file type detection, ELF analysis, entropy, string extraction, family detection

Never call `exec()` or `shell_exec()` directly from a Livewire component or controller.
Always go through the relevant service class.

### Livewire components
- One Livewire component per page
- Components call services, store results in public properties, render views
- Use Livewire polling for live-updating data

### Naming conventions
- Services: `ServerService`, `ThreatIntelService` (PascalCase, suffix Service)
- Livewire components: `Dashboard`, `MalwareViewer`, `IpDetail` (PascalCase)
- Models: `ResourceSnapshot`, `MalwareFile`, `ThreatIp` (PascalCase singular)
- Migrations: `create_resource_snapshots_table` (snake_case)
- Blade views: `dashboard.blade.php`, `malware-viewer.blade.php` (snake_case, hyphens)

### General rules
- Thin controllers and Livewire components — logic belongs in services
- No raw queries — use Eloquent
- Type hint everything — method parameters and return types
- Keep methods short and focused — one method, one responsibility

---

## Pages and routes

| Route | Component | Description |
|---|---|---|
| `/` | `Dashboard` | Live CPU/RAM/disk sparklines, site status, 1h attack counter |
| `/resources` | `Resources` | Detailed CPU/RAM/disk, load avg, top processes |
| `/sites` | `Sites` | Uptime monitoring, response times, status badges |
| `/services` | `Services` | Nginx/MySQL/PHP-FPM/Fail2ban status + restart controls |
| `/security` | `Security` | Fail2ban, SSH attempts, bot scans, firewall rules |
| `/honeypot` | `Honeypot` | Cowrie sessions, credentials, commands, downloads |
| `/honeypot/malware` | `MalwareViewer` | Malware file table with filtering, sorting, indicators |
| `/honeypot/malware/{sha256}` | `MalwareDetail` | Full detail page for a single malware file |
| `/threat-intel` | `ThreatIntel` | Attack volume, top countries/ISPs/ASNs, heatmap, anonymiser breakdown |
| `/threat-intel/ip/{ip}` | `IpDetail` | Per-IP drilldown — sessions, SSH, Nginx hits, malware files |
| `/logs` | `Logs` | Real-time log viewer (Nginx, Laravel, system) |
| `/databases` | `Databases` | MySQL database sizes, table counts, server stats |

---

## Key models and relationships

- `ThreatIp` — enriched IP record (geo, ISP, ASN, VPN/proxy/Tor flags). Central to threat intel.
- `CowrieSession` → belongs to `ThreatIp`, has many `CowrieCommand`, `CowrieDownload`, `CowrieLogin`
- `CowrieDownload` → links a session to a file hash; joins to `MalwareFile` via `file_hash = sha256`
- `MalwareFile` → has many `MalwareString` (extracted strings), `highlights` (notable strings)
- `SshAttempt`, `NginxHit` → belong to `ThreatIp`

`MalwareFile.highlights` is a scoped relation on `MalwareString` returning only strings with a non-null category.

---

## Honeypot and malware pipeline

1. `vitals:parse-cowrie-logs` (every minute) — reads Cowrie JSON log, stores sessions, commands, logins, and `cowrie.session.file_download` events into `cowrie_downloads`
2. `malware:scan-downloads` (every 5 min) — scans Cowrie's downloads directory for new files named by SHA256 hash; creates `MalwareFile` records using `filemtime()` for accurate `first_seen_at`
3. `malware:fetch-vt` (every 30 min) — queries VirusTotal by hash for pending files

### One-time maintenance commands
- `malware:backfill-sources` — two-pass attribution: (1) scans Cowrie JSON logs for `file_download`/`file_upload` events matched by SHA256, (2) timestamp heuristic matching download commands within ±2h of `first_seen_at`
- `malware:backfill-timestamps` — fixes `first_seen_at` on existing records using actual file mtime
- `malware:analyze-existing` — re-runs ELF/entropy/family analysis on all files
- `malware:reextract-strings` — re-extracts and replaces all stored strings

---

## Scheduler (`routes/console.php`)

| Job | Frequency |
|---|---|
| `vitals:snapshot` | Every 5 min |
| `vitals:check-sites` | Every 5 min |
| `vitals:parse-nginx-logs` | Every 5 min |
| `vitals:parse-ssh-logs` | Every 5 min |
| `vitals:parse-cowrie-logs` | Every minute |
| `malware:scan-downloads` | Every 5 min |
| `malware:fetch-vt` | Every 30 min |
| Cleanup SSH attempts | Daily (keeps 90 days) |
| Cleanup Nginx hits | Daily (keeps 90 days) |
| Cleanup resource snapshots | Daily (keeps 7 days) |
| Cleanup site checks | Daily (keeps 30 days) |
| Cleanup failed Cowrie logins | Daily (keeps 30 days) |

---

## Configuration

- `config/security.php` — `ignored_ips` array: IPs excluded from all security stats and displays
- `config/sites.php` — list of monitored sites for uptime checks
- `.env` on server — managed manually at `/var/www/vitals/.env`, never overwritten by deployment

---

## Deployment

Pushing to `main` triggers GitHub Actions. The workflow:
1. Builds frontend assets locally
2. SCPs files to the server (`.env` is never touched)
3. Runs `php artisan migrate --force`
4. Caches config, routes, and views
5. Fixes storage permissions

Secrets required: `SSH_HOST`, `SSH_USER`, `SSH_KEY`.

---

## Security

This dashboard can restart services, read logs, and see full attack data.
It must never be publicly accessible.

Authentication uses **Google OAuth** via Laravel Socialite. Only the email in `GOOGLE_ALLOWED_EMAIL` (`.env`) can log in. All routes are protected by `RequireAuth` middleware except `/login` and the OAuth callback routes.

Never pass user input directly to shell commands — only run a predefined set of known-safe commands.

---

## UI design

- Dark theme — mandatory
- Sidebar navigation with icons
- Monospace font for logs, shell output, IPs, file paths, hashes
- Charts: simple line/bar charts using Chart.js
- Numbers and IPs highlighted in red in honeypot command output
- Optimised for desktop

---

## Git workflow

- `main` — production branch, auto-deploys on push
- Feature branches: `feat/`, `fix/` prefixes
- Never commit directly to main
- Conventional commits: `feat:`, `fix:`, `refactor:`, `chore:`
- Never commit: `.env`, `/vendor`, `/node_modules`, `_ide_helper.php`, `.phpstorm.meta.php`, secrets

---

## What NOT to build

- No user authentication (Nginx basic auth is enough)
- No multi-server support
- No email or Slack alerts
- No file manager
- No in-browser terminal (security risk)
- No deployment tools (GitHub Actions handles it)
- No repository pattern (overkill)

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
