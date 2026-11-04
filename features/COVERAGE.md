# log-command Behat coverage

Suite: `log.feature` (command surface, site resolution, errors), `log-site.feature` (single-site logs), `log-global.feature` (`--cli`, `--global`). Shared steps live in the marked section at the end of `bootstrap/FeatureContext.php`.

## Inventory

The package registers one command, `ee log`, with one subcommand, `show` (there is no `tail` subcommand: `show` itself ends in `tail -n <n> -f <files>` via `system()`).

`ee log show [<site-name>] [--n=<line-count>] [--global] [--cli] [--all] [--nginx] [--php] [--wp] [--access] [--error]`

| Item | Behaviour (from `src/Log_Command.php`) | Covered by |
|---|---|---|
| `ee log` (no subcommand) | Prints usage of `show` | log.feature "ee log without a subcommand lists show" |
| `ee help log show` | Synopsis and option docs | log.feature "ee help log show documents every option" |
| `ee log tail` | Not a subcommand, framework error | log.feature "log tail is not a registered subcommand" |
| Unknown / misspelt flag | Framework "unknown --x parameter" (+ suggestion), exit 1 | log.feature "An unknown flag…", "A misspelt flag…" |
| Extra positional | "Too many positional arguments", exit 1 | log.feature "Too many positional arguments…" |
| `<site-name>` omitted, outside a site dir | `auto_site_name()` error, exit 1 | log.feature "Without a site name…" |
| `<site-name>` omitted, inside a site dir | Site detected from cwd (`get_site_name()` matches the logical path; the step passes it as `PWD` because `app/` is a volume symlink) | log.feature "The site name is detected from the current directory" |
| Unknown site | `auto_site_name()` error ("Could not find the site…") | log.feature "An unknown site is reported" |
| Wrong name inside a site dir | "…is not a valid site-name. Did you mean…" | log.feature "A wrong site name inside a site directory…" |
| Disabled site | `get_site_info()` "is not enabled" error | log.feature "A disabled site is refused" |
| Trailing slash in site name | Accepted | log.feature "A trailing slash…" |
| `http://<site>/` form | Should resolve; fails (`auto_site_name()` returns the URL form unchanged) | not covered yet (its scenario comes back with the fix) |
| Default types (nginx, php, wp) on html | nginx `access.log` + `error.log` only | log-site "html site default…" |
| Default types on php | nginx + php logs, no wp warning | log-site "php site default…" |
| Default types on wp | nginx + php logs, "wp log doesn't exist!" warning when no debug.log | log-site "wp site default…" |
| `--nginx` | All `*.log` under `logs/nginx` (recursive) | log-site "--nginx…", "Log files in subdirectories…" |
| `--php` | All `*.log` under `logs/php` | log-site "--php…" |
| `--wp` | `app/htdocs/wp-content/debug.log`; warning + "No logs found!" when missing | log-site "--wp without a debug.log…", "--wp follows…" |
| `--access` | Files named `access.log` in nginx (+ php for non-html) | log-site "--access shows…", "--access only picks files named access.log", "--access and --error on an html site…" |
| `--error` | Files named `error.log` in nginx (+ php for non-html) | log-site "--error shows…", "--access and --error on an html site…" |
| `--all` | All five types | log-site "--all on a wp site…" |
| Combined type flags | Union, de-duplicated (`array_unique`) | log-site "Combined type flags…", "--nginx --php on a php site…" |
| Type the site lacks | "No logs found!", exit 1 | log-site outline "A log type the site does not have…" (html `--php`, html `--wp`, php `--wp`) |
| html/php sites drop the wp type | Intended by `unset()`, broken (the `unset()` is a no-op) | not covered yet (its scenario comes back with the fix) |
| Non-`.log` files and rotated `*.log.1` | Ignored | log-site "Files without a .log extension…" |
| File without extension | PHP warning (`extension` read without `isset()`) | not covered yet (its scenario comes back with the fix) |
| `--n` default 10 | `tail -n 10` | log-site "The default line count is 10" |
| `--n=<k>` / `--n=0` | Initial lines limited / none | log-site "--n limits…", "--n=0…", log-global "--cli honours --n" (exactly one ee.log line) |
| `--n` without value | Framework warning, falls back to 10 | log-site "--n without a value…" |
| `--n=abc` | Rejected up front, exit 1 (fixed in EasyEngine/log-command#8) | log-site "An invalid --n value…" |
| Shell injection via `--n` or a log file name | tail runs without a shell (fixed in EasyEngine/log-command#8) | log-site "--n is not interpreted by the shell", "A log file name is not interpreted by the shell" |
| Following (`tail -f`) | New lines stream until killed | log-site "New lines are streamed while following"; every happy path asserts exit 124 (still running at the timeout) |
| Real nginx access log entry | HTTP request appears in `--access` output | log-site "A real request shows up in the nginx access log" |
| Links in a site log directory | Followed only if the real path is a regular file inside the log directory (the app volume for `debug.log`), otherwise skipped with a warning (fixed in EasyEngine/log-command#8) | log-site outline "A log link that leaves the site's log directory is skipped" (php `logs/php`, html nested `logs/nginx`, wp `debug.log`), "A log link inside the site's log directory is still followed" |
| "watching logfile" lines | `EE::log`, path shown relative to the sites dir; should be hidden by `--quiet`, isn't (core sets up the logger before parsing `--quiet`) | all happy paths; `--quiet` not covered yet (its scenario comes back with the fix) |
| `--cli` | Follows `{EE_ROOT_DIR}/logs/ee.log`; the "watching logfile" line is also written to ee.log (side effect) | log-global "--cli follows EasyEngine's own log" |
| `--cli` with a site name / type flags | Both ignored | log-global "--cli ignores a site name and type flags" |
| `--global` | ee.log + nginx/php/wp logs of every site | log-global "--global follows ee.log and the logs of every site type" |
| `--global`, WP site without debug.log | Warning, keeps following | log-global "--global warns for a WordPress site…" |
| `--global` with `--cli` and a (nonexistent) site name | `--global` wins, site name ignored (documents current behaviour) | log-global "--global takes precedence…" |
| `--global` and disabled sites | Included | log-global "--global includes disabled sites" |
| `--global` undefined `$wp_logs_path` | No PHP warning (fixed in EasyEngine/log-command#8) | log-global "--global raises no PHP warnings…" |
| `--global` with type filters | Filters ignored | not covered yet (its scenario comes back with the fix) |
| Service logs (help promises them for `--global`/`--all`) | Never followed | not covered yet (its scenario comes back with the fix) |
| `--cli`/`--global` "cli log doesn't exist!" and global "No logs found!" | Not covered: unreachable, ee's file logger creates ee.log before the command runs | – |
| Local-DB mariadb logs (`services/mariadb/logs`) | Not covered: never read by the command (part of the missing service logs below), and a local-DB site adds ~1 min | – |

Side effects: the command is read-only apart from appending its "watching logfile" lines to ee.log (asserted). There are no DB rows, containers, proxy config or hooks to verify; idempotency is implicit (every scenario can be rerun; site state is asserted unchanged for the disabled site).

Estimated coverage: about 95% of the branches of `Log_Command` (every branch except the unreachable missing-ee.log paths) once the held-back known-bug scenarios are back; until then the paths of the html/php type drop, `--global` type filters, files without extension and the `http://` site form are not reached.

## Known bugs (scenarios held back until fixed)

The scenarios for the open bugs below assert the fixed behaviour, so they are not in the suite yet and return as regular scenarios with their fixes. The fixed ones are covered by regular scenarios.

- Fixed (High): `--n` and the file list were interpolated unescaped into `system( "tail -n $lines -f $final_string" )`. `logs/php` is writable by www-data in the php container, so a planted file name like `x$(cmd).log` ran as root on the host when the admin followed the logs. Fixed in EasyEngine/log-command#8.
- Fixed (Low): `--n` was not validated and tail's exit status was ignored, so invalid values or tail failures exited 0. Fixed in EasyEngine/log-command#8.
- Open (Low): `unset( $final_types['php'], $final_types['wp'] )` targets string keys of a list, so it is a no-op; html/php sites follow a `wp-content/debug.log` (and an html site would follow `logs/php`).
- Fixed (Low): `--global`: `$wp_logs_path` is only set for WP sites, so a non-WP site before the first WP site raises an undefined-variable warning and later ones reuse a stale path. Fixed in EasyEngine/log-command#8.
- Open (Low): `--global` ignores `--nginx/--php/--wp/--access/--error` (`$final_types` unused), and it also probes nonexistent `logs/access` and `logs/error` directories.
- Open (Low): help says `--global` includes "all services" and `--all` "service logs", but no service logs (nginx-proxy, local mariadb) are followed. The docblock examples also show wrong paths (`~/easyengine/sites/example.com/logs/debug.log`).
- Open (Low): `pathinfo()['extension']` read without `isset()`: a file without extension in a log dir raises a PHP warning.
- Open (Low, site-command helper): `auto_site_name()` accepts `http(s)://<site>/` but returns the args unchanged, so `get_site_info()` fails with "Site http://… does not exist."; site-command's own subcommands use the same pair.
- Fixed (Low): links in a site log directory (writable by the site's containers) were followed by the root `tail` on the host. Fixed in EasyEngine/log-command#8.
- Open (Low, core): the logger is set up before the config and arguments are parsed, so `--quiet` never hides the "watching logfile" lines (found on the CI runner, https://github.com/mrrobot47/log-command/actions/runs/36085159478).

## Tags and runtime

- No `@known-bug` scenarios ship here: the 7 scenario rows written for the open bugs above (2 for the html/php type drop, 1 each for the others) are held back until their fixes land and then return as regular scenarios.
- No `@le`, `@remote` or `@destructive` scenarios: the command needs no public DNS or backups and changes no global state.
- Sites (prefix `bh-log-command-`, deleted by `@AfterFeature`, which only touches that prefix): `-1` html, `-2` php, `-3` wp, `-4` html (disabled), `-99` guaranteed absent. Sites are created lazily by `Given the site … of type … exists`, so each scenario runs on its own and only the first scenario of a feature pays for creation.
- Every `ee log show` run is wrapped in `timeout` (own process group, so `tail` dies too); exit 124 means "still following".
- Estimated runtime: about 12 minutes (site creation ~4 minutes over the three features, WordPress dominating; 52 scenarios at 3–10 s each).
