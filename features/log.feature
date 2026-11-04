Feature: ee log command surface, site resolution and validation errors

  # `ee log show` ends in `tail -f`, so every invocation that can reach tail runs under a timeout;
  # exit code 124 ("still running when the timeout expired") is the success signal for following.

  Scenario: ee log without a subcommand lists show
    When I run 'bin/ee log'
    Then STDOUT should contain "usage: ee log show"
      And the command should exit with code 0

  Scenario: ee help log show documents every option
    When I run 'bin/ee help log show'
    Then the command should exit with code 0
      And STDOUT should contain "ee log show [<site-name>] [--n=<line-count>] [--global] [--cli] [--all]"
      And STDOUT should contain "[--nginx] [--php] [--wp] [--access] [--error]"
      And STDOUT should contain "Monitor site or global logs."

  Scenario: log tail is not a registered subcommand
    When I run 'bin/ee log tail'
    Then STDERR should contain "Error: 'tail' is not a registered subcommand of 'log'. See 'ee help log' for available subcommands."
      And the command should exit with code 1

  Scenario: An unknown flag is rejected before any log is followed
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test --foo" with a timeout of 20 seconds
    Then STDERR should contain "unknown --foo parameter"
      And STDOUT should not contain "watching logfile"
      And the command should exit with code 1

  Scenario: A misspelt flag gets a suggestion
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test --ngix" with a timeout of 20 seconds
    Then STDERR should contain "unknown --ngix parameter"
      And STDERR should contain "Did you mean '--nginx'?"
      And the command should exit with code 1

  Scenario: Too many positional arguments are rejected
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test extra-arg" with a timeout of 20 seconds
    Then STDERR should contain "Too many positional arguments: extra-arg"
      And the command should exit with code 1

  Scenario: Without a site name and outside a site directory the command explains how to call it
    When I run "bin/ee log show" with a timeout of 20 seconds
    Then STDERR should contain "Could not find the site you wish to run log show command on."
      And STDERR should contain "ee log show <site-name>"
      And the command should exit with code 1

  Scenario: An unknown site is reported
    Given the site "bh-log-command-99.test" does not exist
    When I run "bin/ee log show bh-log-command-99.test" with a timeout of 20 seconds
    Then STDERR should contain "Could not find the site you wish to run log show command on."
      And STDOUT should not contain "watching logfile"
      And the command should exit with code 1

  Scenario: A disabled site is refused
    Given the site "bh-log-command-4.test" of type "html" exists
      And the site "bh-log-command-4.test" is disabled
    When I run "bin/ee log show bh-log-command-4.test" with a timeout of 20 seconds
    Then STDERR should contain "Site bh-log-command-4.test is not enabled. Use `ee site enable bh-log-command-4.test` to enable it."
      And STDOUT should not contain "watching logfile"
      And the command should exit with code 1
      And the site "bh-log-command-4.test" should be disabled

  Scenario: The site name is detected from the current directory
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show" from "{SITES_DIR}/bh-log-command-1.test/app/htdocs" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And the command should still be running when the timeout expires

  Scenario: A wrong site name inside a site directory suggests the detected site
    Given the site "bh-log-command-1.test" of type "html" exists
      And the site "bh-log-command-99.test" does not exist
    When I run "bin/ee log show bh-log-command-99.test" from "{SITES_DIR}/bh-log-command-1.test" with a timeout of 20 seconds
    Then STDERR should contain "bh-log-command-99.test is not a valid site-name. Did you mean `ee log show bh-log-command-1.test`?"
      And the command should exit with code 1

  Scenario: A trailing slash on the site name is accepted
    Given the site "bh-log-command-1.test" of type "html" exists
    When I run "bin/ee log show bh-log-command-1.test/" with a timeout of 8 seconds
    Then STDOUT should contain "watching logfile bh-log-command-1.test/logs/nginx/access.log"
      And the command should still be running when the timeout expires
