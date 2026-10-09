# Developing the link repair plugin

## Layout

`src/` is the root of the installable package, laid out as it will be on a site:

```
src/
  linkrepair.xml                         the manifest; <files folder="plugins/task/linkrepair">
  script.php                             install script (a service provider): Joomla 6.1 / PHP 8.3 checks, enables the plugin
  plugins/task/linkrepair/
    services/provider.php                the composition root: registers the services, builds the plugin lazily
    forms/scan.xml, forms/repair.xml     the tasks' parameters
    language/en-GB/                      plg_task_linkrepair(.sys).ini
    sql/                                 the two tables, and updates/mysql/<version>.sql
    src/
      Extension/LinkRepair.php           the task plugin: parameters in, exit code out
      Run/RoutineFactory.php             builds a Scanner or Repairer from one task's parameters
      Run/Clock.php, SystemClock.php     time, for the time budget
      Scan/Scanner.php                   one scan run
      Repair/Repairer.php                one repair run
      Html/LinkExtractor.php             <a href> and link text out of HTML
      Html/LinkRewriter.php              replace one href value, nothing else
      Url/InternalLinkFilter.php         internal or not; the path, query and fragment
      Url/SiteAddress.php                the site's address and host names
      Resolve/LinkResolver.php           path -> menu item, following redirects if needed
      Resolve/DatabaseMenuIndex.php      menu items by route (#__menu.path)
      Resolve/HttpRedirectFollower.php   redirects, by hand, own hosts only
      Content/ContentSources.php         the kinds of content, in the order they are read
      Content/TableSource.php            reading a page of rows, or one row
      Content/ArticleSource.php          articles; saved through com_content's ArticleModel
      Content/CategorySource.php         category descriptions; saved through com_categories' CategoryModel
      Content/ModuleSource.php           custom modules; saved through com_modules' ModuleModel
      Content/AdminModelSaver.php        a fresh component model per save, as the item's user
      Store/                             the two tables, behind ScanStore and LinkStore
      Report/CsvReport.php               the CSV in the log folder
tests/
  Unit/                                  PHPUnit, no Joomla, no database, no network
  Support/                               in-memory articles and stores, fake menus, redirects and clock
  cypress/                               specs against the development site
build/build.php                          zips src/ into build/plg_task_linkrepair-<version>.zip
tools/                                   install-local.php, seed-dev-site.php, phpstan-bootstrap.php
```

The namespace is `Yepr\Plugin\Task\LinkRepair`. The design and its reasons are in
[plan.md](plan.md).

## Dependency injection

Services are never built where they are used. `services/provider.php` is the only place
that wires them together, in the child container Joomla gives each extension:

| Service | Built from |
|---|---|
| `MenuIndex` | `DatabaseMenuIndex` on the site's `DatabaseInterface` |
| `RedirectFollower` | `HttpRedirectFollower` on Joomla's HTTP client, with automatic redirects off |
| `ScanStore`, `LinkStore` | `DatabaseScanStore`, `DatabaseLinkStore` on the database |
| `AdminModelSaver` | the application and `UserFactoryInterface` |
| `ContentSources` | `ArticleSource`, `CategorySource`, `ModuleSource` on the database and the saver |
| `CsvReport` | the link store and the site's log folder |
| `LinkExtractor`, `LinkRewriter`, `InternalLinkFilter`, `Clock` | themselves |
| `RoutineFactory` | all of the above |
| `PluginInterface` | `LinkRepair`, with the factory, through `$container->lazy()` |

All are shared and built on first use; the plugin is a lazy proxy (PHP 8.4), so a request
that runs no task builds none of it.

What differs per task (the site's address, follow redirects, dry run, the user, the time
budget) comes from the task's parameters, so `RoutineFactory` makes the resolver, the
scanner and the repairer per run; it is the only place they are made. Per save, com_content's
component model is made by `AdminModelSaver`, because a model keeps state between saves.

Value objects (`ContentItem`, `HtmlLink`, `InternalLink`, `MenuItem`, `Resolution`,
`RedirectResult`, `LinkRecord`, `Scan`, `RepairSettings`, `RunOutcome`) and exceptions are
created where they arise.

## Database

All queries go through the query builder with bound parameters, inserts included. The
two tables:

- `#__linkrepair_scans`: one row per scan, with its cursor and the repair's cursor.
- `#__linkrepair_links`: one row per link found, with its state and message.

A new scan removes the finished scans before the latest one, with their links.
Uninstalling drops both tables.

## The development site

`/joomla` holds Joomla 6.1.4, installed and git-ignored:

- URL: http://localhost/link-repair/joomla, login `admin` / `adminadminadmin`
- database `jlatest-link-repair` (user `root`, no password), table prefix `jlr_`

To set it up again from scratch:

```bash
curl -sSL -o joomla.tar.gz https://github.com/joomla/joomla-cms/releases/download/6.1.4/Joomla_6.1.4-Stable-Full_Package.tar.gz
mkdir -p joomla && tar -xzf joomla.tar.gz -C joomla && rm joomla.tar.gz
php joomla/installation/joomla.php install -n --site-name="Link repair dev" --admin-user=Admin --admin-username=admin --admin-password=adminadminadmin --admin-email=admin@example.test --db-type=mysqli --db-host=localhost --db-user=root --db-pass= --db-name=jlatest-link-repair --db-prefix=jlr_
rm -rf joomla/installation
composer install-local
php tools/seed-dev-site.php
```

`tools/seed-dev-site.php` creates, through Joomla's Web Services API: a small menu
(User Guide > SEO Basics, Menus), an article with every kind of link the plugin meets
(current path, old path, `index.php/` with an anchor, absolute, with a query, broken, and
links it must leave alone), an article with the same old link twice, a category with links
in its description, a custom module with a link that is shown on all pages except one, URL
rewriting, and a redirect `user-manual/... -> user-guide/...` in `.htaccess`. The scan then
finds 12 links: 10 repairable, 1 with a query string, 1 broken.

It also switches off the auto-start of Joomla's guided tours. On a fresh site the "Welcome
to Joomla" tour starts by itself and takes the browser to the dashboard, which now and then
makes a Cypress spec land there instead of on the task form.

Create the tasks in *System > Scheduled Tasks* (site address
`http://localhost/link-repair/joomla`), and run them from the command line:

```bash
php joomla/cli/joomla.php scheduler:list
php joomla/cli/joomla.php scheduler:run --id=<id>
```

The task log is `joomla/administrator/logs/joomla_scheduler.php`; the report is
`joomla/administrator/logs/linkrepair-scan-<n>.csv`.

## Commands

| Command | What it does |
|---|---|
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan, level 8, against the Joomla in `/joomla` |
| `composer cs` | phpcs: PSR-12, with Joomla's tabs in `src/` |
| `composer cs-fix` | php-cs-fixer on tests, build and tools |
| `composer build` | the package zip in `build/` |
| `composer install-local` | build, then install into `/joomla` through Joomla's CLI |
| `npx cypress run` | the Cypress specs; copy `cypress.env.json.dist` to `cypress.env.json` first |

## Known limits

- **Who made a version.** The version history records the logged-in user as the editor
  (`VersionableModelTrait::storeHistory()` makes its `ContentHistory` table without the
  model's user). A scheduled task has no logged-in user, so the editor is empty. *Modified
  by* (the user chosen in the task) and the version note do identify the change. Setting
  the application's identity during the save would fix it, but a task can run inside a
  visitor's request (lazy scheduler), so the plugin does not do that. Better fixed in core.
- **Links to pages that are not a menu item** (an article shown through a category blog)
  are reported as *unmatched*, not repaired. See "Later" in [plan.md](plan.md).
- **Module versions** are only recorded when *Enable Versions* is on in the Modules options;
  Joomla has it off by default. Articles and categories have it on by default.
- Other content (contacts' misc info, fields with HTML) is not scanned yet.

## Releasing

Bump `<version>` in `src/linkrepair.xml`, add `sql/updates/mysql/<version>.sql` (a comment
is enough when the schema did not change), commit, and push a tag `v<version>`. The release
workflow checks that tag and manifest agree, runs the checks, builds the package and
attaches it to a GitHub release.
