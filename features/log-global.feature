Feature: ee log show --cli and --global

  # --cli follows {EE_ROOT_DIR}/logs/ee.log; --global follows ee.log plus every site's logs.
  # These scenarios only read ee.log; the "watching logfile" lines ee itself writes there are used as markers.
  # The html site is created first on purpose: the undefined `$wp_logs_path` bug only shows when a non-WordPress site precedes the first WordPress site.

  Scenario: --cli follows EasyEngine's own log
    Given the file "{EE_ROOT_DIR}/logs/ee.log" should exist
    When I run "bin/ee log show --cli" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And STDOUT should contain "ee.INFO: watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And the file "{EE_ROOT_DIR}/logs/ee.log" should contain "ee.INFO: watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And the command should still be running when the timeout expires

  # The last ee.log line is whatever ee logged last (the tail command's debug line since log-command#8), not the INFO line.
  Scenario: --cli honours --n
    When I run "bin/ee log show --cli --n=1" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And STDOUT should contain "] ee." exactly 1 time
      And the command should still be running when the timeout expires

  Scenario: --cli ignores a site name and type flags
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test --cli --nginx" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And STDOUT should not contain "watching logfile bh-log-command-1.test"
      And the command should still be running when the timeout expires

  Scenario: --global follows ee.log and the logs of every site type
    Given the site "bh-log-command-1.test" of type "html" exists
      And the site "bh-log-command-2.test" of type "php" exists
      And the site "bh-log-command-3.test" of type "wp" exists
      And the file "{SITES_DIR}/bh-log-command-3.test/app/htdocs/wp-content/debug.log" contains:
      """
      bh-global-wp-marker
      """
    When I run "bin/ee log show --global" with a timeout of 10 seconds
    Then STDOUT should contain "watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/php/access.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/php/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/app/htdocs/wp-content/debug.log"
      And STDOUT should contain "==> {EE_ROOT_DIR}/logs/ee.log <=="
      And STDOUT should contain "bh-global-wp-marker"
      And STDOUT should not contain "bh-log-command-1.test/logs/php/"
      And the command should still be running when the timeout expires

  Scenario: --global warns for a WordPress site without debug.log but keeps following
    Given the site "bh-log-command-3.test" of type "wp" exists
      And the file "{SITES_DIR}/bh-log-command-3.test/app/htdocs/wp-content/debug.log" does not exist
    When I run "bin/ee log show --global" with a timeout of 10 seconds
    Then STDERR should contain "Warning: wp log doesn't exist!"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/access.log"
      And the command should still be running when the timeout expires

  Scenario: --global takes precedence over --cli and a site name
    Given the site "bh-log-command-1.test" of type "html" exists
      And the site "bh-log-command-99.test" does not exist
    When I run "bin/ee log show bh-log-command-99.test --global --cli" with a timeout of 10 seconds
    Then STDERR should not contain "Could not find the site"
      And STDOUT should contain "watching logfile {EE_ROOT_DIR}/logs/ee.log"
      And STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And the command should still be running when the timeout expires

  Scenario: --global includes disabled sites
    Given the site "bh-log-command-4.test" of type "html" exists
      And the site "bh-log-command-4.test" is disabled
    When I run "bin/ee log show --global" with a timeout of 10 seconds
    Then STDOUT should contain "watching logfile bh-log-command-4.test/logs/nginx/access.log"
      And the command should still be running when the timeout expires
      And the site "bh-log-command-4.test" should be disabled

  # Regression check (fixed in EasyEngine/log-command#8): in the --global loop $wp_logs_path is only assigned for WordPress sites, so a non-WordPress site
  # before the first WordPress site hits an undefined variable (later ones reuse a stale path). Only exposed while
  # the html site's DB row precedes the wp site's (true when the feature runs in order).
  Scenario: --global raises no PHP warnings for non-WordPress sites
    Given the site "bh-log-command-1.test" of type "html" exists
      And the site "bh-log-command-3.test" of type "wp" exists
    When I run "bin/ee log show --global" with a timeout of 10 seconds
    Then STDERR should not contain "wp_logs_path"
      And the command should still be running when the timeout expires
