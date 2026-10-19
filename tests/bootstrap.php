<?php

// Production's autoloader order when the GraphQL module is beside this one (the
// CI image and dev environments): FrontAccounting includes sgw_import's
// hooks.php before graphql's, and Composer prepends each autoloader it loads,
// so graphql's Anorm is the one every model gets. Unit tests need only ours.
require dirname(__DIR__) . '/vendor/autoload.php';
$graphql = dirname(__DIR__, 2) . '/graphql/vendor/autoload.php';
if (is_file($graphql)) {
    require $graphql;
}
