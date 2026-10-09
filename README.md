# Link Repair

A Joomla 6.1+ task plugin that finds links in articles, category descriptions and custom
modules that were typed as URLs to pages of the same site (`/user-manual/seo/...`), and turns them into links to menu items
(`index.php?Itemid=123`), as the editor's **CMS Content > Menu** button makes them.

Typed links break, or only keep working through redirects, as soon as a menu item's
alias or place in the menu changes. Links to menu items don't: Joomla builds the
current URL from the menu item every time the page is shown.

Written for guide.joomla.org, where hundreds of articles link to each other by typed
URL, and usable on any Joomla 6.1 or later site.

## What it does

The plugin adds two task types to **System > Scheduled Tasks**:

- **Link repair: scan** reads every article, category description (of any component) and
  custom module, and finds the links to pages of this site.
  For each one it works out which menu item it means: by its path, or, for an old URL,
  by following the site's own redirects. It changes nothing, and writes a report
  (`administrator/logs/linkrepair-scan-<n>.csv`).
- **Link repair: repair** rewrites the links the latest scan could resolve. It saves
  through each component's own model, as its editor does, so every change has a version
  in the history (for modules only when *Enable Versions* is on in the Modules options),
  with the note "Link repair: ...". A custom module keeps its menu assignment. It is a
  **dry run** until you switch that off.

Links that cannot be repaired are in the report, with the reason: a link with a query
string, a link that leads to an error page, or a page that is not a menu item.

Both tasks work in short runs (20 seconds by default) and continue where they stopped,
so a site with thousands of articles never hits a time limit.

## Using it

1. Install the package and create a task **Link repair: scan**. Fill in the site's
   address (for example `https://guide.joomla.org`); the task needs it because it may
   run from the command line. Run it.
2. Read the report in the log folder.
3. Create a task **Link repair: repair**. Choose a user under *Record changes as*. Run it
   once as a dry run and read the task log; then switch dry run off and run it again.
4. Run a new scan to see what is left.

The repair leaves an item alone when it changed since the scan, or when someone has it
open in the editor; scan again and repair again for those.

## Requirements

Joomla 6.1 or later (the plugin is loaded lazily, which Joomla 6.0 cannot do), PHP 8.3, MySQL or MariaDB.

## Development

See [docs/development.md](docs/development.md); the design is in
[docs/plan.md](docs/plan.md).

## Licence

GNU General Public License version 3 or later; see [LICENSE](LICENSE).
