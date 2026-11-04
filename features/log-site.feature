Feature: ee log show for a single site (type filters, line count, following)

  # Sites: bh-log-command-1.test (html), bh-log-command-2.test (php), bh-log-command-3.test (wp).
  # `ee log show` ends in `tail -f`, so runs are bounded by a timeout and exit 124 means "still following".
  # Extra *.log files written by scenarios live inside the site's own log volume and are removed after each scenario.

  Scenario: html site default shows only the nginx logs
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile" exactly 2 times
      And STDOUT should contain "==> {SITES_DIR}/bh-log-command-1.test/logs/nginx/access.log <=="
      And STDOUT should contain "==> {SITES_DIR}/bh-log-command-1.test/logs/nginx/error.log <=="
      And STDERR should not contain "Warning"
      And the command should still be running when the timeout expires

  Scenario: php site default shows nginx and php logs without a wp warning
    Given the site "bh-log-command-2.test" of type "php" exists
    When I run "bin/ee log show bh-log-command-2.test" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-2.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/php/access.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/php/error.log"
      And STDOUT should contain "watching logfile" exactly 4 times
      And STDERR should not contain "wp log doesn't exist"
      And the command should still be running when the timeout expires

  Scenario: wp site default shows nginx and php logs and warns about the missing debug.log
    Given the site "bh-log-command-3.test" of type "wp" exists
      And the file "{SITES_DIR}/bh-log-command-3.test/app/htdocs/wp-content/debug.log" does not exist
    When I run "bin/ee log show bh-log-command-3.test" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/error.log"
      And STDOUT should contain "watching logfile" exactly 4 times
      And STDERR should contain "Warning: wp log doesn't exist!"
      And the command should still be running when the timeout expires

  Scenario: --nginx shows only the nginx logs
    Given the site "bh-log-command-3.test" of type "wp" exists
    When I run "bin/ee log show bh-log-command-3.test --nginx" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile" exactly 2 times
      And STDOUT should not contain "/logs/php/"
      And STDERR should not contain "wp log doesn't exist"
      And the command should still be running when the timeout expires

  Scenario: --php shows only the php logs
    Given the site "bh-log-command-3.test" of type "wp" exists
    When I run "bin/ee log show bh-log-command-3.test --php" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/error.log"
      And STDOUT should contain "watching logfile" exactly 2 times
      And STDOUT should not contain "/logs/nginx/"
      And the command should still be running when the timeout expires

  Scenario: --wp without a debug.log warns and fails
    Given the site "bh-log-command-3.test" of type "wp" exists
      And the file "{SITES_DIR}/bh-log-command-3.test/app/htdocs/wp-content/debug.log" does not exist
    When I run "bin/ee log show bh-log-command-3.test --wp" with a timeout of 20 seconds
    Then STDERR should contain "Warning: wp log doesn't exist!"
      And STDERR should contain "Error: No logs found!"
      And STDOUT should not contain "watching logfile"
      And the command should exit with code 1

  Scenario: --wp follows the WordPress debug.log
    Given the site "bh-log-command-3.test" of type "wp" exists
      And the file "{SITES_DIR}/bh-log-command-3.test/app/htdocs/wp-content/debug.log" contains:
      """
      bh-wp-debug-marker
      """
    When I run "bin/ee log show bh-log-command-3.test --wp" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/app/htdocs/wp-content/debug.log"
      And STDOUT should contain "watching logfile" exactly 1 time
      And STDOUT should contain "bh-wp-debug-marker"
      And STDERR should not contain "Warning"
      And the command should still be running when the timeout expires

  Scenario: --all on a wp site shows nginx, php and the debug.log
    Given the site "bh-log-command-3.test" of type "wp" exists
      And the file "{SITES_DIR}/bh-log-command-3.test/app/htdocs/wp-content/debug.log" contains:
      """
      bh-wp-all-marker
      """
    When I run "bin/ee log show bh-log-command-3.test --all" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/app/htdocs/wp-content/debug.log"
      And STDOUT should contain "watching logfile" exactly 5 times
      And STDOUT should contain "bh-wp-all-marker"
      And the command should still be running when the timeout expires

  Scenario: --access shows the nginx and php access logs only
    Given the site "bh-log-command-3.test" of type "wp" exists
    When I run "bin/ee log show bh-log-command-3.test --access" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/access.log"
      And STDOUT should contain "watching logfile" exactly 2 times
      And STDOUT should not contain "error.log"
      And the command should still be running when the timeout expires

  Scenario: --error shows the nginx and php error logs only
    Given the site "bh-log-command-3.test" of type "wp" exists
    When I run "bin/ee log show bh-log-command-3.test --error" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/error.log"
      And STDOUT should contain "watching logfile" exactly 2 times
      And STDOUT should not contain "access.log"
      And the command should still be running when the timeout expires

  Scenario: --access and --error on an html site use the nginx logs only
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test --access --error" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/error.log"
      And STDOUT should contain "watching logfile" exactly 2 times
      And the command should still be running when the timeout expires

  Scenario: --access only picks files named access.log
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-other.log" contains:
      """
      bh-other-marker
      """
    When I run "bin/ee log show bh-log-command-1.test --access" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And STDOUT should contain "watching logfile" exactly 1 time
      And STDOUT should not contain "bh-other"
      And the command should still be running when the timeout expires

  Scenario: Combined type flags are merged without duplicates
    Given the site "bh-log-command-3.test" of type "wp" exists
    When I run "bin/ee log show bh-log-command-3.test --nginx --access" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/access.log" exactly 1 time
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/nginx/error.log" exactly 1 time
      And STDOUT should contain "watching logfile bh-log-command-3.test/logs/php/access.log" exactly 1 time
      And STDOUT should contain "watching logfile" exactly 3 times
      And the command should still be running when the timeout expires

  Scenario: --nginx --php on a php site shows both log directories
    Given the site "bh-log-command-2.test" of type "php" exists
    When I run "bin/ee log show bh-log-command-2.test --nginx --php" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile" exactly 4 times
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/php/error.log"
      And STDOUT should contain "watching logfile bh-log-command-2.test/logs/nginx/error.log"
      And the command should still be running when the timeout expires

  Scenario Outline: A log type the site does not have fails with "No logs found!"
    Given the site "<site>" of type "<type>" exists
    When I run "bin/ee log show <site> <flag>" with a timeout of 20 seconds
    Then STDERR should contain "Error: No logs found!"
      And STDOUT should not contain "watching logfile"
      And the command should exit with code 1

    Examples:
      | site                  | type | flag  |
      | bh-log-command-1.test | html | --php |
      | bh-log-command-1.test | html | --wp  |
      | bh-log-command-2.test | php  | --wp  |

  Scenario: Log files in subdirectories are discovered recursively
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-sub/bh-nested.log" contains:
      """
      bh-nested-marker
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/bh-sub/bh-nested.log"
      And STDOUT should contain "bh-nested-marker"
      And the command should still be running when the timeout expires

  Scenario: Files without a .log extension, including rotated logs, are ignored
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-notes.txt" contains:
      """
      bh-notes-marker
      """
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-rotated.log.1" contains:
      """
      bh-rotated-marker
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile" exactly 2 times
      And STDOUT should not contain "bh-notes"
      And STDOUT should not contain "bh-rotated"
      And the command should still be running when the timeout expires

  Scenario: The default line count is 10
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-lines.log" contains:
      """
      bh-line-01
      bh-line-02
      bh-line-03
      bh-line-04
      bh-line-05
      bh-line-06
      bh-line-07
      bh-line-08
      bh-line-09
      bh-line-10
      bh-line-11
      bh-line-12
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx" with a timeout of 8 seconds
    Then STDOUT should contain "bh-line-03"
      And STDOUT should contain "bh-line-12"
      And STDOUT should not contain "bh-line-02"
      And STDOUT should not contain "bh-line-01"
      And the command should still be running when the timeout expires

  Scenario: --n limits the initial lines
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-lines.log" contains:
      """
      bh-line-01
      bh-line-02
      bh-line-03
      bh-line-04
      bh-line-05
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx --n=2" with a timeout of 8 seconds
    Then STDOUT should contain "bh-line-04"
      And STDOUT should contain "bh-line-05"
      And STDOUT should not contain "bh-line-03"
      And the command should still be running when the timeout expires

  Scenario: --n=0 shows no existing lines but still follows
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-lines.log" contains:
      """
      bh-line-01
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx --n=0" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/bh-lines.log"
      And STDOUT should not contain "bh-line-01"
      And the command should still be running when the timeout expires

  Scenario: --n without a value warns and falls back to 10 lines
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-lines.log" contains:
      """
      bh-line-01
      bh-line-02
      bh-line-03
      bh-line-04
      bh-line-05
      bh-line-06
      bh-line-07
      bh-line-08
      bh-line-09
      bh-line-10
      bh-line-11
      bh-line-12
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx --n" with a timeout of 8 seconds
    Then STDERR should contain "Warning: --n parameter needs a value"
      And STDOUT should contain "bh-line-03"
      And STDOUT should not contain "bh-line-02"
      And the command should still be running when the timeout expires

  # Regression check (fixed in EasyEngine/log-command#8): --n was never validated and the exit status of tail was ignored, so an invalid
  # line count printed tail's error but ee still exited 0.
  Scenario: An invalid --n value fails with a non-zero exit code
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test --nginx --n=abc" with a timeout of 20 seconds
    Then STDERR should contain "Invalid --n value 'abc'"
      And STDOUT should not contain "watching logfile"
      And the command should exit with a non-zero code

  # Regression check (fixed in EasyEngine/log-command#8): --n and the file list were interpolated unescaped into system( "tail -n $lines -f ..." ).
  Scenario: --n is not interpreted by the shell
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test --nginx --n='1 -q /dev/null; echo BH_INJ_$((6*7)); #'" with a timeout of 8 seconds
    Then STDOUT should not contain "BH_INJ_42"
      And STDERR should contain "Invalid --n value"
      And the command should exit with code 1

  # Regression check (same fix): log file names were interpolated unescaped too; logs/php is writable by www-data in the php
  # container, so a site's PHP code could plant a name that ran as root on the host when the admin followed the logs.
  Scenario: A log file name is not interpreted by the shell
    Given the site "bh-log-command-2.test" of type "php" exists
      And the file "{SITES_DIR}/bh-log-command-2.test/logs/php/bh-inj$(echo${IFS}BH_FILE_INJ_$((6*7))).log" contains:
      """
      bh-inj-marker
      """
    When I run "bin/ee log show bh-log-command-2.test --php" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-2.test/logs/php/access.log"
      And STDOUT should not contain "BH_FILE_INJ_42"
      And STDERR should not contain "BH_FILE_INJ_42"
      And STDOUT should contain "bh-inj-marker"
      And the command should still be running when the timeout expires

  # Regression check (fixed in EasyEngine/log-command#8): the site's containers can write to its log directories and wp-content, and ee log show runs tail as
  # root on the host, so a link planted there must not be followed out of the directory.
  Scenario Outline: A log link that leaves the site's log directory is skipped
    Given the site "<site>" of type "<type>" exists
      And the file "{SITES_DIR}/<site>/<link>" is a link to a file outside the site containing "bh-outside-marker"
    When I run "bin/ee log show <site> <flag>" with a timeout of 8 seconds
    Then STDOUT should not contain "bh-outside-marker"
      And STDOUT should not contain "watching logfile <site>/<link>"
      And STDERR should contain "Warning: Skipping <site>/<link>: it is not a regular file inside the log directory."
      And the command should still be running when the timeout expires

    Examples:
      | site                  | type | link                             | flag    |
      | bh-log-command-2.test | php  | logs/php/bh-outside.log          | --php   |
      | bh-log-command-1.test | html | logs/nginx/bh-sub/bh-outside.log | --nginx |
      | bh-log-command-3.test | wp   | app/htdocs/wp-content/debug.log  | --all   |

  Scenario: A log link inside the site's log directory is still followed
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-real.log" contains:
      """
      bh-inside-marker
      """
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-current.log" is a link to "bh-real.log"
    When I run "bin/ee log show bh-log-command-1.test --nginx" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/bh-current.log"
      And STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/bh-real.log"
      And STDERR should not contain "Skipping"
      And the command should still be running when the timeout expires

  Scenario: New lines are streamed while following
    Given the site "bh-log-command-1.test" of type "html" exists
      And the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-follow.log" contains:
      """
      bh-follow-old
      """
    When I run "bin/ee log show bh-log-command-1.test --nginx --n=0" with a timeout of 12 seconds while "bh-follow-new" is appended to the file "{SITES_DIR}/bh-log-command-1.test/logs/nginx/bh-follow.log" once tail follows it
    Then STDOUT should contain "bh-follow-new"
      And STDOUT should not contain "bh-follow-old"
      And the command should still be running when the timeout expires

  Scenario: A real request shows up in the nginx access log
    Given the site "bh-log-command-1.test" of type "html" exists
    Then a GET request to "http://127.0.0.1/bh-log-command-access-probe" with Host "bh-log-command-1.test" should return 404
    When I run "bin/ee log show bh-log-command-1.test --access --n=50" with a timeout of 8 seconds
    Then STDOUT should contain "GET /bh-log-command-access-probe"
      And the command should still be running when the timeout expires
