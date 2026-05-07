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
| **Honeypot** | Cowrie SSH honeypot sessions, credentials, commands, and downloads |
| **Threat Intel** | Attack volume charts, top countries/ISPs, heatmap, origin map, cross-source IPs |
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

Registered in the Laravel scheduler and run automatically:

| Job | Frequency |
|---|---|
| Take resource snapshot | Every 5 minutes |
| Run site checks | Every 5 minutes |
| Parse SSH logs | Every 5 minutes |
| Parse Nginx logs | Every 5 minutes |
| Parse Cowrie honeypot logs | Every 5 minutes |
| Clean old resource snapshots | Daily (keeps 7 days) |
| Clean old site checks | Daily (keeps 30 days) |
| Clean old log entries | Daily (keeps 7 days) |

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
