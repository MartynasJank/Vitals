# Vitals — personal server monitoring dashboard

A self-hosted server management dashboard for monitoring and managing a single Linux server.
Built for personal use, runs on the same server it monitors.

Live at: `vitals.martybuilds.dev` (to be set up)
Repo: github.com/MartynasJank/Vitals

---

## Project overview

A beautiful, real-time dashboard that gives full visibility into the server health,
sites, databases, security, and logs — all in one place. Think a lightweight personal
alternative to Laravel Forge or Ploi, but self-hosted and built from scratch.

---

## Tech stack

- **Laravel 13** (latest)
- **PHP 8.4**
- **SQLite** — lightweight, no separate DB server needed for this app
- **Livewire 3** — real-time UI, polling for live stats
- **Tailwind CSS** — styling
- **Alpine.js** — small interactive bits
- **Chart.js** — CPU/RAM/disk history charts

Do not use Vue, React, or Inertia. Keep it Livewire + Blade.

Use SQLite instead of MySQL — this app does not need a heavy database, and it avoids
a circular dependency (monitoring MySQL from an app that needs MySQL to run).

---

## Server context

The app monitors the server it runs on:
- OS: Ubuntu 24.04
- Server: Hetzner CPX32 (4 vCPU, 8GB RAM, 160GB SSD)
- IP: 162.55.219.28
- Sites running: martybuilds.dev, moviepicker.martybuilds.dev, tiktokshuffle.martybuilds.dev
- Services: Nginx, MySQL, PHP 8.4-FPM, Fail2ban

---

## Architecture & code style

Follow standard Laravel conventions. Keep code clean, readable and consistent.

### Service classes
All business logic lives in `app/Services/`. Livewire components and controllers
stay thin — they call services and pass data to views, nothing more.

Key services to create:
- `ServerService` — all shell commands for CPU, RAM, disk, processes
- `SiteService` — HTTP checks for site uptime
- `LogService` — reading and parsing log files
- `DatabaseService` — MySQL stats queries
- `SecurityService` — fail2ban, SSH log parsing, firewall rules

Never call `exec()` or `shell_exec()` directly from a Livewire component or controller.
Always go through the relevant service class.

### Livewire components
- One Livewire component per page to start
- Components call services, store results in public properties, render views
- Use Livewire polling (`wire:poll`) for live-updating data
- Break into smaller sub-components only when a page becomes complex

### Naming conventions
- Services: `ServerService`, `SiteService` (PascalCase, suffix Service)
- Livewire components: `Dashboard`, `ResourceMonitor`, `SiteManager` (PascalCase)
- Models: `ResourceSnapshot`, `SiteCheck`, `LogEntry` (PascalCase singular)
- Migrations: `create_resource_snapshots_table` (snake_case)
- Blade views: `dashboard.blade.php`, `resources.blade.php` (snake_case)

### General rules
- Thin controllers and Livewire components — logic belongs in services
- No raw queries — use Eloquent
- No hardcoded strings — use constants or config values where it makes sense
- Type hint everything — method parameters and return types
- Keep methods short and focused — one method, one responsibility

---

## Database schema (SQLite)

```sql
-- Store historical snapshots for charts
resource_snapshots
  id, cpu_percent, ram_used_mb, ram_total_mb,
  disk_used_gb, disk_total_gb, recorded_at

-- Store site uptime check results
site_checks
  id, site_name, url, status (up/down), response_ms,
  status_code, checked_at

-- Store captured log entries
log_entries
  id, source (nginx_error/laravel/system), message,
  level (info/warning/error), captured_at
```

---

## Pages and features

### 1. Dashboard (/)
Overview of everything at a glance:
- CPU usage — current % + sparkline chart (last 60 snapshots)
- RAM usage — used/total GB + sparkline chart
- Disk usage — used/total GB + progress bar
- Sites status — quick cards showing up/down for each site
- Recent alerts — anything that looks wrong

Livewire polls every 5 seconds to keep stats live.

### 2. Resources (/resources)
Detailed server resource monitoring:
- CPU: current usage %, load average (1m, 5m, 15m), core count
- RAM: used, free, cached, swap
- Disk: usage per partition
- Line charts showing history over last hour / 24h / 7 days
- Top 10 processes by CPU and RAM (like a web-based top command)

Shell commands to use:
```bash
top -bn1 | grep "Cpu(s)"
free -m
df -h
cat /proc/loadavg
ps aux --sort=-%cpu | head -11
```

### 3. Sites (/sites)
Manage and monitor all sites running on the server:
- List of all sites with status badge (up/down), response time, last checked
- Each site card shows: domain, tech stack label, Nginx config status
- Click a site to see: uptime history, recent response times, Nginx config viewer
- Manual "check now" button per site
- Site checks run automatically every 5 minutes via Laravel scheduler

Sites to monitor (hardcoded in config initially):
```php
[
    ['name' => 'Portfolio', 'url' => 'https://martybuilds.dev'],
    ['name' => 'Movie Picker', 'url' => 'https://moviepicker.martybuilds.dev'],
    ['name' => 'TikTok Shuffle', 'url' => 'https://tiktokshuffle.martybuilds.dev'],
]
```

### 4. Services (/services)
Status of all system services:
- Nginx — running/stopped, reload/restart buttons
- MySQL — running/stopped, restart button
- PHP 8.4-FPM — running/stopped, restart button
- Fail2ban — running/stopped

For each service show status (green/red), uptime, memory usage.

Shell commands:
```bash
systemctl status nginx
systemctl status mysql
systemctl status php8.4-fpm
systemctl status fail2ban
```

### 5. Security (/security)
Security overview:
- Fail2ban: total banned IPs, currently banned list with unban button
- Recent SSH login attempts (last 20) from auth.log
- UFW firewall rules list
- Last 10 successful SSH logins

Shell commands:
```bash
fail2ban-client status sshd
grep "Failed password" /var/log/auth.log | tail -20
ufw status numbered
grep "Accepted" /var/log/auth.log | tail -10
```

### 6. Logs (/logs)
Real-time log viewer:
- Tab switcher: Nginx error, Nginx access, Laravel (moviepicker), System (syslog)
- Shows last 100 lines, auto-refreshes every 10 seconds via Livewire
- Color coded: errors red, warnings amber, info grey
- Search/filter input to grep through logs

Shell commands:
```bash
tail -100 /var/log/nginx/error.log
tail -100 /var/log/nginx/access.log
tail -100 /var/www/moviepicker/storage/logs/laravel.log
tail -100 /var/log/syslog
```

### 7. Databases (/databases)
MySQL overview:
- List of all databases with size
- Per database: table count, total rows, size on disk
- Quick stats: MySQL version, uptime, connections

---

## Security

This dashboard is powerful — it can restart services, read logs, see banned IPs.
It must never be publicly accessible.

Protect the entire app with Nginx basic auth:
```nginx
auth_basic "Vitals";
auth_basic_user_file /etc/nginx/.htpasswd;
```

The .htpasswd file is created manually on the server, never committed to git.

Never pass user input directly to shell commands — sanitize everything to prevent
command injection. Only run a predefined set of known safe commands.

---

## Git workflow

### Branch strategy
- `main` — production branch, auto-deploys to server via GitHub Actions
- Never commit directly to main during active development
- Create a feature branch for each new feature:
  ```bash
  git checkout -b feat/dashboard
  git checkout -b feat/sites-page
  git checkout -b feat/services-page
  ```

### Commit often
Commit after every meaningful working change. Never commit broken code.
Think of commits as save points — save at every good checkpoint.

### Commit message format
Use conventional commits:
```
feat: add dashboard CPU and RAM stats
feat: add sites uptime checker
feat: add services status page
fix: correct RAM calculation on dashboard
chore: add ide-helper and pint
refactor: extract shell commands to ServerService
```

### When to push to main
Only merge to main when a feature is fully working and tested locally.
Merging to main triggers a deployment — broken code means broken production.

### How to merge a feature branch
```bash
git checkout main
git merge feat/dashboard
git push origin main
```

### Never commit
- .env file
- /vendor directory
- /node_modules directory
- _ide_helper.php and .phpstorm.meta.php (ide-helper generated files)
- Any file containing API keys, passwords or secrets

---

## Laravel scheduler

Register these jobs in the scheduler:
- Take resource snapshot — every 5 minutes
- Run site checks — every 5 minutes
- Clean old resource snapshots older than 7 days — daily
- Clean old site checks older than 30 days — daily
- Clean old log entries older than 7 days — daily

---

## Deployment

GitHub Actions deploys on push to main. The workflow should:
1. SSH into the server
2. Pull latest code
3. Run `composer install --no-dev --optimize-autoloader`
4. Run `php artisan migrate --force`
5. Run `php artisan config:cache`
6. Run `php artisan route:cache`
7. Run `php artisan view:cache`
8. Fix storage permissions

The .env file is managed manually on the server at `/var/www/vitals/.env`.
Never overwrite it during deployment.

---

## UI design

- Dark theme — this is a sysadmin tool, dark mode is mandatory
- Sidebar navigation with icons for each section
- Status badges: green dot = healthy, amber = warning, red = down/error
- Monospace font for logs, shell output, IPs, file paths
- Charts: simple line charts using Chart.js, no 3D or decorative effects
- Optimised for desktop — this will not be used on mobile

---

## Build order

Build in this exact order, one step at a time. Do not move to the next step
until the current one is fully working.

1. Livewire 3 + Tailwind + SQLite configured
2. Run all migrations
3. Shared dark layout with sidebar navigation
4. Dashboard page — live CPU/RAM/disk numbers, no charts yet
5. Resource snapshots job — store every 5 min, add Chart.js charts to dashboard
6. Sites page — uptime checks every 5 min, status badges
7. Services page — status display + restart buttons
8. Security page — fail2ban banned IPs + SSH login log
9. Logs page — real-time log viewer with tab switcher
10. Databases page — MySQL stats

---

## What NOT to build

- No user authentication (Nginx basic auth is enough)
- No multi-server support (single server only for now)
- No email or Slack alerts (can add later)
- No file manager
- No in-browser terminal (security risk)
- No deployment tools (GitHub Actions handles deployments)
- No repository pattern (overkill for this project)
- No tests initially — get it working first, add tests later

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
