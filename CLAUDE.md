# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository overview

Zabbix monorepo (AGPL-3.0). Several products live side by side:

| Component | Language | Location |
|---|---|---|
| Server, proxy, agent (v1), `zabbix_get`, `zabbix_sender`, `zabbix_js` | C | `src/zabbix_*`, shared code in `src/libs/zbx*`, headers in `include/` |
| Agent 2, web service (PDF reports), mock server | Go (`golang.zabbix.com/agent2`) | `src/go/` |
| Java gateway | Java | `src/zabbix_java/` |
| Frontend + JSON-RPC API | PHP ≥ 8.2, JS | `ui/` |
| Frontend styles/icons | SCSS | `sass/` (compiled output is committed to `ui/assets/`) |
| DB schema source & generators | Perl/PHP | `create/` |
| Official templates & media types (human-edited source) | YAML | `templates/` |

## Build (C / Go / Java)

Autotools-based. `configure` is not in git; generate it first:

```sh
./bootstrap.sh                 # generates configure (`./bootstrap.sh tests` to enable cmocka tests; plain bootstrap disables them again)
./configure --enable-server --enable-proxy --enable-agent --enable-agent2 --enable-java \
            --with-postgresql --with-libcurl --with-openssl --with-net-snmp   # pick what you need
make -j$(nproc)                # also generates src/libs/zbxdbschema/dbschema.c
make dbschema                  # generates database/{mysql,postgresql}/{schema,data}.sql and database/sqlite3/schema.sql
```

- A server/proxy binary is built against exactly one DB backend (`--with-mysql`, `--with-postgresql`, or `--with-sqlite3`, which is proxy only).
- Preset compiler flags live in `share/config.site.d/` (`gcc-debug`, `clang-debug-sanitize` for ASan/UBSan, ...), used as `CONFIG_SITE=share/config.site.d/gcc-debug ./configure ...`. They use bash-only `CFLAGS+=` syntax, so where `/bin/sh` is dash (Debian/Ubuntu, including this machine) most flags are silently dropped. Running configure under bash should avoid this: `CONFIG_SHELL=/bin/bash CONFIG_SITE=... /bin/bash ./configure ...` (not verified).
- Other top-level targets: `make gettext` (compiles `.mo`), `make css` (needs Ruby `sass`), `make icons` (needs npm and Ruby `sass`).

## Tests

### C unit tests (cmocka + YAML)
Prerequisites: cmocka and libyaml dev packages, and the Perl modules `YAML::XS`, `Path::Tiny` and `IPC::Run3`. Tests are enabled only when configure includes `--enable-server`, `--enable-proxy` or `--enable-agent`.

```sh
./bootstrap.sh tests && ./configure --enable-server --with-mysql ...   # include the component under test
make tests_build                      # builds daemons + test binaries (plain `make` does not build tests)
tests/tests_run.pl -s <suite>         # one suite; suite = test binary name, e.g. zbx_malloc
tests/tests_run.pl -x                 # all suites, JUnit XML, non-zero exit on failure
make tests                            # tests_build + run all
```
- `make tests` and `tests_run.pl` without `-x` **exit 0 even when tests fail**. Read the FAILURE/ERROR/SKIPPED columns. SKIPPED means the binary was not built, usually because its component was not enabled in configure.
- `-v` runs under valgrind, but memory errors show up only in the printed output and do not fail the case.
- Always rebuild through the top-level `make tests_build`. It passes the `-Wl,--wrap=` flags (DB, file, FS, `exit`, socket functions; see `COMMON_WRAP_FUNCS` in the top-level `Makefile.am`) that running `make` inside `tests/` would miss. Mocks are in `tests/mocks/` and `tests/zbxmock*.c`. If cmocka or libyaml live in a non-standard path, set `LD_LIBRARY_PATH`.
- Each test is a C binary under `tests/libs/<lib>/` or `tests/zabbix_server/...` with a same-named `.yaml` file of cases (`test case:`, `in:`, `out:`, `exit code:`). `tests_run.pl` finds every `*.yaml` except `*.inc.yaml` and feeds each YAML document to the matching binary on stdin.
- Registering a new test binary: add it to its directory's `Makefile.am`. A new directory also goes into the parent `Makefile.am` `SUBDIRS` and into `tests/conf_tests.m4`, then re-run `./bootstrap.sh tests` and configure. bootstrap *copies* `conf_tests.m4` into `m4/`, so edits to it take effect only then.

### Go (agent 2)
```sh
./configure --enable-agent2 ... && make check          # from top dir: builds the C libs, then `go test ./...` in src/go
cd src/go && go test ./plugins/<plugin>/... -run TestName   # single test (pure-Go packages)
make -C src/go style NEW_FROM_REV=<rev>                # golangci-lint v2 (.golangci.yaml), only on changes since <rev>
make -C src/go format                                  # go fmt
```
`src/go/Makefile` exists only after configure with `--enable-agent2`. Packages that use cgo/C libraries (anything importing `pkg/zbxlib`, `pkg/tls`, `pkg/zbxcomms`, `pkg/glexpr` or `internal/agent`, e.g. `plugins/log`, `plugins/systemrun`, `plugins/zabbix/*`) need a prior top-level `make`, plus the `CGO_CFLAGS`/`CGO_LDFLAGS` values from the generated `src/go/Makefile`.

### Java gateway
`make -C src/zabbix_java test` (after configure with `--enable-java`).

### PHP frontend
PHPUnit is not vendored in `ui/`; install it separately. The config uses options removed in PHPUnit 10, so 9.x is likely.
```sh
(cd ui/tests/unit && phpunit include/)                       # unit tests, no DB needed
(cd ui/tests/unit && phpunit --filter <TestName> include/)   # single unit test
```
API (`ui/tests/api_json/`), Selenium (`ui/tests/selenium/`) and integration (`ui/tests/integration/`, which run real server/proxy/agent binaries) tests need a live DB plus a generated `ui/tests/bootstrap.php` and `ui/conf/zabbix.conf.php`. Ant targets in `build.xml` produce them, e.g. `ant -DDBTYPE=POSTGRESQL -DDBHOST=... -DDBPORT=5432 -DDBNAME=... -DDBUSER=... -DDBPASSWORD=... -DURL=... build-api-postgresql`; use `-DPHPUNIT_FILTER=` to select tests. Entry points: `frontend.php`, `api_json/ApiJsonTests.php`, `integration/IntegrationTests.php`.

**These Ant targets are destructive.** They drop and recreate `DBNAME`, and re-run `./bootstrap.sh` and `./configure` (overwriting your configure setup and disabling cmocka tests). The frontend targets `git apply` a patch and copy files into `ui/modules`. The integration targets `make install` into the repository root. Don't run them against a database or working tree you care about. Backend CI targets (cppcheck, clang analyzer, cmocka matrix, agent2 and Java tests) are in `build-backend.xml`.

## Architecture notes

### C daemons
- `src/zabbix_server/server.c` and `src/zabbix_proxy/proxy.c` parse config and own the process lifecycle. They fork the worker processes configured by the `Start*` parameters (pollers, trappers, preprocessing, history syncers, escalator, housekeeper, LLD, ...); many default to 0. The server's main process first runs the HA manager: workers start, in runlevels, only on the active HA node and are torn down on standby. The main process also serves runtime control (`zbxrtc`, `zabbix_server -R ...`).
- Server and proxy share most of `src/libs`. There are no server/proxy `#ifdef`s; behaviour that differs is chosen at runtime by program type or by callbacks and config structs passed from the entry point. The exception is `HAVE_SQLITE3` in the DB layer, which only proxy builds can define.
- Value flow:
  - Collectors (pollers, trappers, ...) pass values over IPC (`zbxipcservice`) to the preprocessing manager and workers.
  - Preprocessed values land in the shared-memory history cache (`zbxcachehistory`).
  - On the server, history syncers then write them through `zbxhistory` (SQL, Elasticsearch or ClickHouse backends) and evaluate triggers and events.
  - On the proxy, values go to `zbxproxybuffer` and the data sender instead.
- The configuration cache (`src/libs/zbxcacheconfig`) is a shared-memory copy of configuration, refreshed by the config syncer process. Tables marked `CHANGELOG|n` in `schema.tmpl` sync incrementally through DB triggers; the rest are diff-compared on every sync. Frontend/API changes reach the server only on the next sync. Proxies receive configuration from the server (`proxyconfigread` → `proxyconfigwrite`).
- DB access goes through `zbxdb` (low level), `zbxdbhigh` and `zbxdbwrap`, and must work on MySQL, PostgreSQL and SQLite (proxy).

### Database schema and upgrades
- `create/src/schema.tmpl` is the single source of truth for the schema; seed data is in `data.tmpl`. `make dbschema` generates per-backend SQL and `dbschema.c` (git-ignored) via `create/bin/gen_schema.pl` and `gen_data.pl`.
- `ui/include/schema.inc.php` is generated too, but **is** committed and is often forgotten. Regenerate it with `php create/bin/gen_php.php create/src/schema.tmpl ui/include/schema.inc.php`. Both arguments are needed; with none, the script writes to `create/src/schema.inc.php`.
- Checklist for a schema change:
  1. Edit `schema.tmpl`. Only fields flagged `ZBX_PROXY` are sent from server to proxy.
  2. Add `static int DBpatch_<v>(void)` inside the `#ifndef HAVE_SQLITE3` block of the newest upgrade file. That is the last `DBPATCH_VERSION` in `dbversions[]` in `src/libs/zbxdbupgrade/dbupgrade.c`, currently `dbupgrade_7050.c`. Register it with `DBPATCH_ADD(<v>, 0, 1)` at the end of that file's list.
     - Do one schema operation per patch; each runs in its own transaction.
     - Define fields inline (`zbx_db_field_t`) with helpers like `DBadd_field` and `DBcreate_table`, not from the generated schema.
     - MySQL/PostgreSQL proxies run the same patches, so data patches for server-only tables start with `if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER)) return SUCCEED;`. SQLite proxies are never upgraded.
  3. Bump the DB version to `<v>` in the three places that must match: the `dbversion` row (`ROW |1 |<v> |<v>`) in `schema.tmpl`, the last `DBPATCH_ADD`, and `ZABBIX_DB_VERSION` in `ui/include/defines.inc.php`.
  4. Regenerate `ui/include/schema.inc.php` (command above).
  5. Update dependent code as applicable:
     - The config-cache SELECTs in `zbxcacheconfig/dbsync.c` and the positional `row[N]` indexes in `dbconfig.c`.
     - For a new `CHANGELOG` table, `ZBX_DBSYNC_OBJ_*` in `dbsync.h` plus changelog trigger patches.
     - Length constants such as `src/zabbix_server/db_lengths_constants.h`.
     - API, import and `CAudit` maps.
     - The test fixtures `ui/tests/{api_json,selenium}/data/data_test.sql`.
  6. For history/trends tables, also update `create/bin/gen_history_upgrade.pl` and `database/{elasticsearch,clickhouse}`.

### Frontend (`ui/`)
- Two coexisting styles: legacy page scripts at the `ui/` root (`*.php` plus `ui/include/*.inc.php` helpers), and the MVC framework. New work goes into MVC.
- MVC:
  - Core actions are mapped to controllers and views in `ui/include/classes/mvc/CRouter.php`. Legacy `*.php` pages go through `CLegacyAction`.
  - Controllers are in `ui/app/controllers/` (`CController*`). Views and partials are in `ui/app/views/` and `ui/app/partials/`.
  - Widget and module actions are declared in their `manifest.json` and registered via `CRouter::addActions()`.
- The JSON-RPC API (`api_jsonrpc.php`) is implemented by service classes in `ui/include/classes/api/services/` (`CHost`, `CItem`, ...). New frontend code reads and writes data through `API::...()`; some legacy helpers still use `DBselect` directly.
- Live operations such as item testing, queue and server status go to the running server over its trapper socket (`ui/include/classes/server/CZabbixServer.php`).
- Dashboard widgets are self-contained modules in `ui/widgets/<name>/` (`manifest.json`, `Widget.php`, `actions/`, `views/`, optionally `includes/` and `assets/`). `ui/modules/` is the empty directory for external modules.
- Translations use the gettext wrappers `_()`, `_s()`, `_n()`, `_x()`, `_xs()` and `_xn()`.
  - `.po`/`.pot` files under `ui/locale/` are updated by CI, so don't edit them.
  - `xgettext` scans only PHP. A JS `t('…')` string must also be listed in `$translate_strings` in `ui/jsLoader.php`, or passed in from a `.js.php` view.

## Conventions

- Commit messages start with a 10-character ChangeLog component mask, then the ticket ID(s), e.g. `..F....... [ZBX-12345] fixed ...`.
  - Positions are listed in `ChangeLog.legend` (A, D, F, G, I, J, L, P, S, T). DB changes usually set `I`. Use `..........` for changes with no user-visible effect, such as tests.
  - Several tickets are comma-separated: `[ZBXNEXT-1,ZBXNEXT-2]`.
- User-visible changes add a one-line file `ChangeLog.d/{bugfix|feature}/<TICKET>` containing `<mask> [<TICKET>] <text> (<author>)`. CI merges these into `ChangeLog`; never edit `ChangeLog` directly.
- Official templates and media types are edited as YAML in `templates/` (mask `.........T`). CI regenerates the DB seed exports `create/src/templates-*.tmpl`, `dashboards.tmpl` and the media-type rows in `data.tmpl` from them ("automatic deployment of official Zabbix templates" commits). Don't hand-edit those exports, except when a schema change renames or removes a column they list.
- Source files carry the AGPL copyright header (see any existing file or `src/go/fileheader.txt`), except vendored/third-party and generated files.
- C and PHP code use tabs for indentation.
