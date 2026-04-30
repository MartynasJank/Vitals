# Vitals

A self-hosted server monitoring dashboard for a single Linux server. Built with Laravel, Livewire, and Tailwind CSS.

## Features

- **Dashboard** — live CPU, RAM, and disk usage with sparkline charts
- **Resources** — detailed CPU, RAM, disk, load average, and top processes
- **Sites** — uptime monitoring with response times for all hosted sites
- **Services** — Nginx, MySQL, PHP-FPM, Fail2ban status and restart controls
- **Security** — Fail2ban banned IPs, SSH login attempts, UFW firewall rules
- **Logs** — real-time log viewer for Nginx, Laravel, and system logs
- **Databases** — MySQL database sizes, table counts, and server stats

## Stack

- **Laravel 13** + **PHP 8.4**
- **Livewire 4** — real-time UI with polling
- **Tailwind CSS v4** — dark theme
- **SQLite** — no separate database server needed
- **Chart.js** — CPU/RAM history charts

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

Then open in [Laravel Herd](https://herd.laravel.com) or run `php artisan serve`.

## Security

This dashboard exposes sensitive server information. It is protected by Nginx basic auth in production — never expose it publicly.

```nginx
auth_basic "Vitals";
auth_basic_user_file /etc/nginx/.htpasswd;
```

## Server

Monitors a Hetzner CPX32 running Ubuntu 24.04 with Nginx, MySQL, PHP 8.4-FPM, and Fail2ban.