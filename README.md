# Vitals

A self-hosted server monitoring and threat intelligence dashboard for a single Linux server. Built with Laravel, Livewire, and Tailwind CSS — a lightweight personal alternative to Forge or Ploi.

## Pages

| Page | What it shows |
|---|---|
| **Dashboard** | Live CPU, RAM, disk with sparkline charts and 1h attack counter |
| **Resources** | Detailed CPU/RAM/disk, load averages, top processes sortable by CPU or MEM |
| **Sites** | Uptime monitoring with response times and status badges |
| **Services** | Nginx, MySQL, PHP-FPM, Fail2ban status with restart controls |
| **Security** | Fail2ban banned IPs, SSH login attempts, bot scans, firewall rules |
| **Honeypot** | Cowrie SSH honeypot sessions, credentials, commands, downloads, and malware files |
| **Malware viewer** | Per-file analysis — entropy, ELF arch, UPX packing, VirusTotal results, extracted strings, source IPs |
| **Malware file detail** | Full detail page for a single file with all indicators and session attribution |
| **Threat Intel** | Attack volume charts, top countries/ISPs/ASNs, heatmap, origin map, anonymiser breakdown |
| **IP detail** | Per-IP drilldown — honeypot sessions, SSH attempts, Nginx hits, malware files dropped |
| **Logs** | Real-time log viewer for Nginx, Laravel, and system logs |
| **Databases** | MySQL database sizes, table counts, and server stats |

## Stack

- **Laravel 13** + **PHP 8.4**
- **Livewire 3** — real-time UI with polling
- **Tailwind CSS v4** — dark theme
- **Alpine.js** — small interactive bits
- **SQLite** — app database, no separate server needed
- **MySQL** — monitored as a target service, not used by the app itself
- **Chart.js** — CPU/RAM history and attack volume charts

## Requirements

- PHP 8.4
- Composer
- Node.js + npm

## Local setup

```bash
git clone https://github.com/MartynasJank/Vitals.git
cd Vitals
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
```

Open in [Laravel Herd](https://herd.laravel.com) or run `php artisan serve`.

## Scheduled jobs

Registered in the Laravel scheduler (`routes/console.php`) and run automatically via `* * * * * php artisan schedule:run`:

| Job | Frequency |
|---|---|
| Take resource snapshot | Every 5 minutes |
| Run site checks | Every 5 minutes |
| Parse SSH logs | Every 5 minutes |
| Parse Nginx logs | Every 5 minutes |
| Parse Cowrie honeypot logs | Every minute |
| Scan Cowrie downloads directory for new malware | Every 5 minutes |
| Fetch VirusTotal results for pending files | Every 30 minutes |
| Clean old SSH attempts | Daily (keeps 90 days) |
| Clean old Nginx hits | Daily (keeps 90 days) |
| Clean old resource snapshots | Daily (keeps 7 days) |
| Clean old site checks | Daily (keeps 30 days) |
| Clean old failed Cowrie logins | Daily (keeps 30 days) |

## One-time maintenance commands

These are run manually on the server when needed — not scheduled:

| Command | Purpose |
|---|---|
| `malware:backfill-sources` | Link existing malware files to the sessions that downloaded them by scanning Cowrie logs and commands |
| `malware:backfill-timestamps` | Fix `first_seen_at` on existing malware records to use actual file mtime instead of scan time |
| `malware:analyze-existing` | Re-run analysis (ELF, entropy, family detection) on all existing malware files |
| `malware:reextract-strings` | Re-extract and replace all stored strings for every malware file |
| `vitals:backfill-geo` | Backfill geo/ISP/ASN data for threat IPs that were enriched before the geo lookup was added |
| `vitals:enrich-ip {ip}` | Manually trigger geo/ISP/ASN enrichment for a single IP |
| `vitals:reset-threat-data` | Wipe and re-import all threat data (destructive) |

## Configuration

### Ignored IPs

IPs in `config/security.php` are excluded from all security stats and displays — useful for filtering out your own IP or known-safe scanners:

```php
'ignored_ips' => [
    '',
],
```

### Monitored sites

Sites are configured in `config/sites.php`. Each entry is checked for uptime every 5 minutes.

## Deployment

Pushing to `main` triggers an automatic deploy via GitHub Actions. The workflow:

1. Builds assets locally
2. SCPs files to the server (does not overwrite `.env`)
3. Runs `php artisan migrate --force`
4. Caches config, routes, and views
5. Fixes storage permissions

GitHub Actions secrets required: `SSH_HOST`, `SSH_USER`, `SSH_KEY`.

## Security

This dashboard exposes sensitive server information. It is protected by Nginx basic auth in production — never expose it publicly.

```nginx
auth_basic "Vitals";
auth_basic_user_file /etc/nginx/.htpasswd;
```

The `.htpasswd` file is created manually on the server and never committed to git.

## Server

Designed to monitor a single Linux server running Nginx, MySQL, PHP-FPM, Fail2ban, and a Cowrie SSH honeypot.
