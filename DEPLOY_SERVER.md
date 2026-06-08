# Deploying Kopafasta to a Linux Server

This guide uses Ubuntu + Nginx + PHP-FPM and the deploy script in scripts/deploy.sh.

## 1) Server requirements

Install required packages (example for Ubuntu 24.04):

```bash
sudo apt update
sudo apt install -y nginx mysql-client unzip git rsync supervisor
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2) Create app directory

```bash
sudo mkdir -p /var/www/kopafasta
sudo chown -R $USER:www-data /var/www/kopafasta
```

## 3) Add environment file on server

Create /var/www/kopafasta/.env with production values. Minimum important values:

- APP_ENV=production
- APP_DEBUG=false
- APP_URL=https://your-domain.com
- DB_* values
- CACHE_STORE=database or redis
- SESSION_DRIVER=database or redis
- QUEUE_CONNECTION=database or redis

Generate key (first time only):

```bash
cd /var/www/kopafasta
php artisan key:generate
```

## 4) Nginx virtual host

Create /etc/nginx/sites-available/kopafasta:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    root /var/www/kopafasta/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/kopafasta /etc/nginx/sites-enabled/kopafasta
sudo nginx -t
sudo systemctl reload nginx
```

## 5) Queue worker (Supervisor)

Create /etc/supervisor/conf.d/kopafasta-worker.conf:

```ini
[program:kopafasta-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/kopafasta/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/kopafasta/storage/logs/worker.log
stopwaitsecs=3600
```

Start it:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kopafasta-worker:*
```

## 6) Scheduler cron

```bash
crontab -e
```

Add line:

```cron
* * * * * cd /var/www/kopafasta && php artisan schedule:run >> /dev/null 2>&1
```

## 7) Deploy from local machine

From your project folder on Mac:

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh user@server-ip /var/www/kopafasta
```

If you want to skip Node build on server:

```bash
RUN_NPM_BUILD=0 ./scripts/deploy.sh user@server-ip /var/www/kopafasta
```

## 8) SSL certificate (recommended)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## Notes

- This script keeps server `.env` and storage uploads intact.
- It installs production PHP dependencies and runs migrations automatically.
- If you use Redis in production, install and configure Redis before deployment.
- **Document uploads** require raised limits on the server (see below).

## Upload limits (required for borrower documents)

Borrower uploads allow up to **5 MB per file** (bank statements, multi-page scans). The default Nginx/PHP limits are too low and cause **413 Request Entity Too Large**.

On the server, apply:

```bash
# Nginx — inside /etc/nginx/sites-available/kopafasta.triptz.net server block
client_max_body_size 25M;

# PHP-FPM
sudo cp deploy/php-upload-limits.ini /etc/php/8.3/fpm/conf.d/99-kopafasta-uploads.ini
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

Reference snippets are in `deploy/nginx-upload-limits.snippet` and `deploy/php-upload-limits.ini`.
