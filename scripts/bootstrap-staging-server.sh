#!/usr/bin/env bash
# Idempotent Ubuntu 24.04 bootstrap for the Kopafasta STAGING droplet.
# Does not touch production. Run as root on the new droplet.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/kopafasta}"
DB_NAME="${DB_NAME:-kopafasta_staging}"
DB_USER="${DB_USER:-kopafasta_staging}"
STAGING_HOST="${STAGING_SERVER_NAME:-staging.kopafasta.com}"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx mysql-server unzip git rsync supervisor curl ufw \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-readline

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
fi

if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
fi

mkdir -p "$APP_DIR" /var/backups/kopafasta-staging
chown -R www-data:www-data "$APP_DIR" || true

# Independent MySQL database + user
DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
umask 077
echo "${DB_PASS}" > /root/.kopafasta_staging_db_password
chmod 600 /root/.kopafasta_staging_db_password

if [[ ! -f ${APP_DIR}/.env ]]; then
  echo "Create ${APP_DIR}/.env from .env.staging.example after the first code sync."
fi

# PHP upload limits
cat >/etc/php/8.3/fpm/conf.d/99-kopafasta-uploads.ini <<'INI'
upload_max_filesize = 25M
post_max_size = 25M
memory_limit = 256M
INI

# Nginx placeholder until SSL
cat >/etc/nginx/sites-available/kopafasta-staging <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${STAGING_HOST};
    root ${APP_DIR}/public;
    index index.php;
    add_header X-Robots-Tag "noindex, nofollow" always;
    client_max_body_size 25M;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sfn /etc/nginx/sites-available/kopafasta-staging /etc/nginx/sites-enabled/kopafasta-staging
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl enable --now php8.3-fpm nginx mysql supervisor

# Queue worker
cat >/etc/supervisor/conf.d/kopafasta-staging-worker.conf <<INI
[program:kopafasta-staging-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
stopwaitsecs=3600
INI
supervisorctl reread
supervisorctl update

# Scheduler + daily backup
CRON_FILE=/etc/cron.d/kopafasta-staging
cat >"$CRON_FILE" <<CRON
* * * * * www-data cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
15 2 * * * root ${APP_DIR}/scripts/staging-backup.sh >> /var/log/kopafasta-staging-backup.log 2>&1
CRON
chmod 644 "$CRON_FILE"

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable || true

echo "Staging bootstrap finished. DB ${DB_NAME} created. Password stored at /root/.kopafasta_staging_db_password"
