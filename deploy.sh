#!/bin/sh
# Deploy the webapp to classic-computing.org (vm03).
#
#   ./deploy.sh            rsync code, seed the database on first deploy
#
# Layout on the server:
#   /var/www/hddb/html  document root (Alias /hddb)
#   /var/www/hddb/lib   PHP library + schema (outside the docroot)
#   /var/www/hddb/bin   maintenance scripts
#   /var/www/hddb/data  hddb.sqlite, owned by www-data (never overwritten)
#
# One-time server setup (root): install webapp/apache/hddb.conf including
# the OIDC client secret file, then reload Apache — see the README.

set -e
cd "$(dirname "$0")"

HOST=classic-computing.de
TARGET=/var/www/hddb

ssh "$HOST" "sudo install -d -o \$USER $TARGET $TARGET/data"
rsync -av --delete webapp/html webapp/lib webapp/bin "$HOST:$TARGET/"
rsync -av hddb.sqlite "$HOST:$TARGET/seed.sqlite"

ssh "$HOST" "
    set -e
    if [ ! -f $TARGET/data/hddb.sqlite ]; then
        php $TARGET/bin/init-db.php $TARGET/seed.sqlite
        echo 'database seeded'
    fi
    sudo chown -R www-data:www-data $TARGET/data
    sudo chmod 775 $TARGET/data
"
echo "deployed to https://classic-computing.org/hddb/"
