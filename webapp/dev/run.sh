#!/bin/sh
# Start a local development server on port 8080 with a scratch database
# seeded from the repository's hddb.sqlite.
set -e
cd "$(dirname "$0")"

export HDDB_DATA_DIR="${HDDB_DATA_DIR:-$PWD/data}"
if [ ! -f "$HDDB_DATA_DIR/hddb.sqlite" ]; then
    [ -f ../../hddb.sqlite ] || (cd ../.. && python3 convert.py)
    php ../bin/init-db.php ../../hddb.sqlite
fi

echo "http://localhost:8080/ (data in $HDDB_DATA_DIR)"
exec php -S localhost:8080 -t ../html router.php
