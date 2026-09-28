# FrontAccounting Module: sgw_import

[![Build Status](https://travis-ci.org/saygoweb/frontaccounting-module-sgw_import.svg?branch=master)](https://travis-ci.org/saygoweb/frontaccounting-module-sgw_import)

A module for Front Accounting that provides automated import of transactions from csv files exported from your bank.

- Match lines in a bank account file to a customer, supplier or a quick entry.
- Automatically generate a simple direct supplier invoice.
- Automatically allocate deposits to customer invoices, if there is a reference in the line that can be used to match to the invoice number.

## Installation
The module needs to be unpacked and exist in the folder
`./modules/sgw_import`.

As with all modules you will need to set permissions for any user, including the system admin, in the setup menu. You will need to logout and login again after doing this.

## Getting Started
TLDR ...
The menu items `Import Transactions` and `Configure Import Transactions` have been added under the `Banking and General Ledger` menu.


1. `Import Transactions`, upload a csv file setting the correct bank account.
2. `Configure Import Transactions`, set the date field, date format, and amount field. Optionally hide some columns you don't need to see in the import transactions screens.
3. Back to `Import Transactions`. Click the filename or number. Add line definitions to those pesky recurring transactions. It's ok to leave some that are one off, or better done manually.

## Tests

CI runs `tools/ci.sh` in the FrontAccounting CI image
([cambell-prince/frontaccounting `docker/ci`](https://github.com/cambell-prince/frontaccounting/tree/master-cp/docker/ci)),
with graphql and sgw_sales activated alongside, as they are deployed. It
lints, loads the autoloaders in production's order (graphql's last, so its
Anorm wins) to check every model still loads under it, and renders Import
Bank Files end to end. To run it locally, with that repository checked out
beside this one:

    ../frontaccounting/docker/ci/plugin-test.sh \
      --setup 'composer install --no-interaction --no-progress' \
      --with graphql=https://github.com/saygoweb/frontaccounting-module-graphql.git@main \
      --with sgw_sales=https://github.com/saygoweb/frontaccounting-module-sgw_sales.git@master \
      . -- sh tools/ci.sh
