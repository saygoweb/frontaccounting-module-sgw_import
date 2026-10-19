#!/bin/sh
# Prepares this module in the FrontAccounting CI image after activation:
#  - sgw_import's tables, when they are missing. activate_extension() creates
#    none (production's came from data/0.1.0.sql, imported by hand once).
#  - uploads/, where Import Transactions keeps the files it is given, writable
#    by Apache: group www-data, which the CI package's commands run with too,
#    so tests can remove what Apache stored. Only the folder changes, not the
#    files already in it.
# Run by tools/ci.sh and tools/coverage.sh, and by the CI package's development
# environments after they activate this module.
set -eu
: "${FA_ROOT:?run this inside the FrontAccounting CI image (docker/ci)}"
here="$(cd "$(dirname "$0")/.." && pwd)"
db() {
    mariadb -h "$FA_DB_HOST" -u "$FA_DB_USER" -p"$FA_DB_PASSWORD" "$@" "$FA_DB_NAME"
}
if ! db -N -e "SHOW TABLES LIKE '0_import_file'" | grep -q .; then
    db < "$here/data/0.1.0.sql"
    echo "created sgw_import's tables (data/0.1.0.sql)"
fi
mkdir -p "$here/uploads"
chgrp www-data "$here/uploads"
chmod g+ws "$here/uploads"
