#!/usr/bin/env bash
# BC Staff Nginx + Let's Encrypt. Run with sudo bash, after setup-site.sh.
set -Eeuo pipefail
trap 'echo "FEJL: Web/SSL-opsætningen stoppede ved linje $LINENO" >&2' ERR
DOMAIN=dev-staff01.bellcom.dk
APP=/var/www/bc-staff
[ "$EUID" -eq 0 ] || { echo "FEJL: Brug sudo." >&2; exit 1; }
[ -f "$APP/web/sites/default/settings.php" ] || { echo "FEJL: Drupal er ikke installeret." >&2; exit 1; }
[ -S /run/php/php8.4-fpm.sock ] || { echo "FEJL: PHP-FPM socket mangler." >&2; exit 1; }
[ ! -e /etc/nginx/sites-available/bc-staff ] || { echo "FEJL: BC Staff vhost findes allerede; stopper." >&2; exit 1; }
cat > /etc/nginx/sites-available/bc-staff <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name dev-staff01.bellcom.dk;
    root /var/www/bc-staff/web;
    index index.php;
    client_max_body_size 32m;
    location = /favicon.ico { log_not_found off; access_log off; }
    location = /robots.txt { allow all; log_not_found off; access_log off; }
    location ~ /\. { deny all; }
    location ~* ^/sites/[^/]+/files/.*\.(?:php|phtml|phar)$ { deny all; }
    location ~* \.(?:yml|yaml|twig|module|inc|install|sql|sh|md|log)$ { deny all; }
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
NGINX
ln -s /etc/nginx/sites-available/bc-staff /etc/nginx/sites-enabled/bc-staff
nginx -t
systemctl reload nginx
certbot --nginx --non-interactive --agree-tos --email js@bellcom.dk --redirect -d "$DOMAIN"
nginx -t
systemctl reload nginx
curl -fsSI "https://$DOMAIN/" | head -n 8
echo "OK: https://$DOMAIN"
