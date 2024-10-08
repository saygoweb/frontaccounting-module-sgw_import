var gulp = require('gulp');
var gutil = require('gulp-util');
var child_process = require('child_process');
var exec2 = require('child_process').exec;
var async = require('async');
var template = require('lodash.template');
var rename = require("gulp-rename");

var execute = function(command, options, callback) {
  if (options == undefined) {
    options = {};
  }
  command = template(command, options);
  if (!options.silent) {
    gutil.log(gutil.colors.green(command));
  }
  if (!options.dryRun) {
    if (options.env == undefined) {
      exec2(command, function(err, stdout, stderr) {
        gutil.log(stdout);
        gutil.log(gutil.colors.yellow(stderr));
        callback(err);
      });
    } else {
      exec2(command, {env: options.env}, function(err, stdout, stderr) {
        gutil.log(stdout);
        gutil.log(gutil.colors.yellow(stderr));
        callback(err);
      });
    }
  } else {
    callback(null);
  }
};

var paths = {
  src: ['!vendor/**', '**/*.php'],
  testE2E: ['tests/e2e/**/*.js'],
  testUnit: ['tests/php/**/*.php']
};

gulp.task('default', function() {
  // place code for your default task here
});

gulp.task('package-zip', function(cb) {
  var options = {
    dryRun: false,
    silent: false,
    src: "./",
    name: "frontaccounting",
    version: "2.4.RC1",
    release: "-sgw_import.module.1.2.1"
  };
  execute(
    'rm -f *.zip && cd <%= src %> && zip -r -x@./upload-exclude-zip.txt -y -q ./<%= name %>-<%= version %><%= release %>.zip *',
    options,
    cb
  );
});

gulp.task('package-tar', function(cb) {
  var options = {
    dryRun: false,
    silent: false,
    src: "./",
    name: "frontaccounting",
    version: "2.4.RC1",
    release: "-sgw_import.module.1.2.1"
  };
  execute(
    'rm -f *.tgz && cd <%= src %> && tar -cvzf ./<%= name %>-<%= version %><%= release %>.tgz -X upload-exclude.txt *',
    options,
    cb
  );
});

gulp.task('package', ['package-zip', 'package-tar']);

gulp.task('upload', function(cb) {
  var options = {
    dryRun: false,
    silent : false,
    src : ".",
    dest : "root@saygoweb.com:/var/www/virtual/saygoweb.com/bms/htdocs/modules/sgw_import/",
  };
  execute(
    'rsync -rzlt --chmod=Dug=rwx,Fug=rw,o-rwx --delete --exclude-from="upload-exclude.txt" --stats --rsync-path="sudo -u vu2006 rsync" --rsh="ssh" <%= src %>/ <%= dest %>',
    options,
    cb
  );
});

gulp.task('db-backup', function(cb) {
  var options = {
    dryRun : false,
    silent : false,
    dest : "root@bms.saygoweb.com",
    key : "~/.ssh/dev_rsa",
    password : process.env.password_db
  };
  execute(
    'mysqldump -u cambell --password=<%= password %> fa_saygoweb | gzip > backup/current.sql.gz',
    options,
    cb
  );
});

gulp.task('db-restore', function(cb) {
  var options = {
    dryRun : false,
    silent : false,
    dest : "root@bms.saygoweb.com",
    key : "~/.ssh/dev_rsa",
    password : process.env.password_db
  };
  execute(
    'gunzip -c backup/current.sql.gz | mysql -u cambell --password=<%= password %> -D fa_saygoweb',
    options,
    cb
  );
});

gulp.task('db-copy', function(cb) {
  var options = {
    dryRun : false,
    silent : false,
    dest : "cambell@saygoweb.com",
    key : "~/.ssh/id_rsa",
    password_remote : process.env.password_saygoweb_fa,
    password_local : process.env.password_db
  };
  execute(
    'ssh -C -i <%= key %> <%= dest %> mysqldump -u cambell --password=<%= password_remote %> saygoweb_fa | mysql -u cambell --password=<%= password_local %> -D fa_saygoweb',
    options,
    cb
  );
});

gulp.task('env-files', function() {
  gulp.src('tests/data/*.php')
    .pipe(gulp.dest('_frontaccounting/'));
  gulp.src('tests/data/company/0/*.php')
  .pipe(gulp.dest('_frontaccounting/company/0/'));
  gulp.src('_frontaccounting/modules/tests/data/lang/*')
  .pipe(gulp.dest('_frontaccounting/lang/'));
});

gulp.task('env-db', function(cb) {
  execute(
      'gunzip -c tests/data/fa_test.sql.gz | mysql -u travis --password=\'\' -D fa_test',
      null,
      cb
    );
});

gulp.task('env-db-demo', function(cb) {
  execute(
      'gunzip -c tests/data/fa_demo.sql.gz | mysql -u travis -D fa_test',
      null,
      cb
    );
});

gulp.task('env-test', ['env-db', 'env-files'], function() {});

gulp.task('test-e2e-travis', ['env-test'], function(cb) {
  execute(
    './node_modules/protractor/bin/protractor modules/tests/e2e/phantom-conf.js',
    null,
    cb
  );
});

gulp.task('test-chrome', ['env-test'], function(cb) {
  execute(
    '/usr/local/bin/protractor modules/tests/e2e/chrome-conf.js',
    null,
    cb
  );
});

gulp.task('test-current', ['env-test'], function(cb) {
  execute(
    'protractor modules/tests/e2e/phantom-conf.js',
    null,
    cb
  );
});

gulp.task('test-restore', function() {
  gulp.src('htdocs/config_db_normal.php')
    .pipe(rename('config_db.php'))
    .pipe(gulp.dest('htdocs/'));
});

gulp.task('test-php', ['env-test'], function(cb) {
  var command = '/usr/bin/env php _frontaccounting/modules/tests/vendor/bin/phpunit -c tests/phpunit.xml';
  execute(command, null, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('test-php-fast', function(cb) {
  var command = '/usr/bin/env php _frontaccounting/modules/tests/vendor/bin/phpunit -c tests/phpunit.xml';
  execute(command, null, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('test-php-debug', ['env-db'], function(cb) {
  var options = {
      env: {'XDEBUG_CONFIG': 'idekey=VSCODE'}
  };
  var command = '/usr/bin/env php _frontaccounting/modules/tests/vendor/bin/phpunit -c tests/phpunit.xml';
  execute(command, options, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('test-php-debug-fast', function(cb) {
  var options = {
      env: {'XDEBUG_CONFIG': 'idekey=VSCODE'}
  };
  var command = '/usr/bin/env php _frontaccounting/modules/tests/vendor/bin/phpunit -c tests/phpunit.xml';
  execute(command, options, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('test-php-coverage', ['env-db'], function(cb) {
  var command = '/usr/bin/env php _frontaccounting/modules/tests/vendor/bin/phpunit -c tests/phpunit.xml --coverage-html ./modules/tests/wiki/code_coverage';
  execute(command, null, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('tasks', function(cb) {
  var command = 'grep gulp\.task gulpfile.js';
  execute(command, null, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('watch', function() {
//  gulp.watch([paths.src, paths.testE2E], ['test-current']);
  gulp.watch([paths.testUnit, paths.src], ['test-php']);
});
