#!/bin/sh
# sgw_import's CI. Runs inside the FrontAccounting CI image (docker/ci in
# cambell-prince/frontaccounting), from this module's directory, on the demo
# dataset, after the module and the ones it is deployed with (graphql,
# sgw_sales) have been activated: lint, coding standard and analysis, the
# models under graphql's Anorm, then the unit, integration and http suites.
set -eu
: "${FA_ROOT:?run this inside the FrontAccounting CI image (docker/ci/plugin-test.sh)}"

echo "==> quality"
composer run quality

# Every extension's hooks share one PHP process, so with graphql active all
# of them get its Anorm. Composer *prepends* each autoload.php it loads, so
# whichever loads last wins a class name both define. In production,
# FrontAccounting includes hooks.php in registration order, and graphql is
# activated after sgw_import, so graphql's autoloader is the one added last
# and its Anorm 3 wins for everybody, including sgw_import's own models. The
# CI driver activates the plugin under test (sgw_import) last, the opposite
# order, under which the #14 fatal cannot appear. So load them here in
# production's order by hand, as tests/bootstrap.php does for the suites, and
# check every sgw_import model class still loads under graphql's Anorm.
echo "==> sgw_import's models load with graphql's Anorm (production autoloader order)"
php -r 'require $argv[1]; require $argv[2]; foreach (glob("includes/Model/*.php") as $f) { $c = "SGW_Import\\Model\\" . basename($f, ".php"); if (!class_exists($c)) { fwrite(STDERR, "cannot load $c\n"); exit(1); } } echo "ok\n";' vendor/autoload.php ../graphql/vendor/autoload.php

echo "==> tables and uploads"
sh tools/init.sh

echo "==> phpunit"
composer run test -- --fail-on-skipped
echo "==> all checks passed"
