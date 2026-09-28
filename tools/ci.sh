#!/bin/sh
# sgw_import's checks. Runs inside the FrontAccounting CI image, in this
# module's directory, after the module and the ones it is deployed with
# (graphql, sgw_sales) have been activated. See docker/ci/README.md in
# cambell-prince/frontaccounting.
set -eu

echo "==> php -l"
find . -path ./vendor -prune -o -path ./node_modules -prune -o -name '*.php' -print |
while IFS= read -r f; do
    php -l "$f" >/dev/null || { php -l "$f"; exit 1; }
done

# Every extension's hooks share one PHP process, so with graphql active all
# of them get its Anorm. Composer *prepends* each autoload.php it loads, so
# whichever loads last wins a class name both define. In production,
# FrontAccounting includes hooks.php in registration order, and graphql is
# activated after sgw_import (see .github/workflows/ci.yml), so graphql's
# autoloader is the one added last and its Anorm 3 wins for everybody,
# including sgw_import's own models. This driver activates the plugin under
# test (sgw_import) last, so on its own sgw_import's autoloader would be the
# one prepended last and its Anorm would win instead - the opposite order,
# under which the #14 fatal cannot appear. So load them here in production's
# order by hand: sgw_import's autoloader first, graphql's second, and check
# every sgw_import model class still loads under graphql's Anorm.
echo "==> sgw_import's models load with graphql's Anorm (production autoloader order)"
php -r 'require $argv[1]; require $argv[2]; foreach (glob("includes/Model/*.php") as $f) { $c = "SGW_Import\\Model\\" . basename($f, ".php"); if (!class_exists($c)) { fwrite(STDERR, "cannot load $c\n"); exit(1); } } echo "ok\n";' vendor/autoload.php ../graphql/vendor/autoload.php

# sgw_import's activate_extension() creates no tables (production's came
# from data/0.1.0.sql, imported by hand once); create them here if missing,
# so the page below has schema to query against instead of tripping over
# Anorm's own schema-auto-create fallback (which doesn't handle the aliased
# columns sgw_import's queries use).
if ! mariadb -h "$FA_DB_HOST" -u "$FA_DB_USER" -p"$FA_DB_PASSWORD" -N "$FA_DB_NAME" -e "SHOW TABLES LIKE '0_import_file'" | grep -q .; then
    mariadb -h "$FA_DB_HOST" -u "$FA_DB_USER" -p"$FA_DB_PASSWORD" "$FA_DB_NAME" < data/0.1.0.sql
fi

# A class sgw_import cannot load there is a fatal error part-way through the
# page (as before #14), so check the page renders to the end: its upload
# form.
echo "==> Import Bank Files renders with graphql and sgw_sales active"
jar="$(mktemp)"
page="$(mktemp)"
fa-ci-login "$jar"
curl -fsS -b "$jar" -o "$page" "$FA_URL/modules/sgw_import/import_files.php"
if ! grep -qi 'upload' "$page"; then
    echo "import_files.php stopped before its upload form. FrontAccounting's log:" >&2
    tail -n 20 "$FA_ROOT/tmp/errors.log" >&2 2>/dev/null || true
    exit 1
fi
echo "ok"
