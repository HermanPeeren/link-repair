# Plan: a task plugin that finds and repairs hard-coded internal links

## The problem

Articles on guide.joomla.org link to each other with URLs typed into the editor:
`/user-manual/seo/...`, `/1-introduction/...`. When a menu item's alias or place in
the menu changes, those URLs break, or keep working only through redirects in
`.htaccess`. A link made with the editor's **CMS Content > Menu** button is stored as
`index.php?Itemid=123` instead, and Joomla turns it into the current URL on every
page view. This plugin turns typed internal links into such menu item links.

## Two task routines

| Routine | What it does | Changes content? |
|---|---|---|
| **Link repair: scan** | Reads every article, category description and custom module, finds the internal links, works out which menu item each one means, and records the result. Writes a CSV report. | No |
| **Link repair: repair** | Takes the latest finished scan and rewrites the links it could resolve, item by item. Dry run by default. | Yes |

Two routines rather than one, so that a scan can run on its own (as a report, or on a
schedule), someone can read the report, and only then is repair run.

## No time-outs

Both routines work within a **time budget** per run (a task parameter, default 20
seconds). When the budget is used up and work remains, the routine saves where it was
and returns `Status::WILL_RESUME`; the scheduler runs it again straight away (in the
next scheduler tick), until the routine returns `Status::OK`. Items are read in
small pages (`id > cursor`, ordered by id), so memory stays flat however many items
there are. The position (the cursor) is in the plugin's own table, so a crash or a
time-out loses at most the item being worked on.

## What counts as an internal link

Where links are looked for (since 0.2.0): articles (intro text and full text), category
descriptions of every component, and custom modules (`mod_custom`) on the site. Each
kind is a `ContentSource`; items are read kind by kind (article, category, module), by id.

From each `<a href="...">` in those texts:

- **Ignored:** empty links, `#anchors`, `mailto:`, `tel:`, `javascript:`, `data:`,
  links that are already Joomla links (`index.php?...`), links to other hosts, and
  links to files (anything with an extension other than `.html` or `.php`).
- **Internal:** a path (`/user-guide/...`, `user-guide/...`) or an absolute URL whose
  host is one of the site's hosts. Those hosts are a task parameter, one per line;
  empty means this site's own host.

## Finding the menu item

1. **Normalise the path:** strip the site's base path, `index.php/`, a trailing slash
   and a `.html` suffix. On a multilingual site, a language prefix (`/en/...`) selects
   the language.
2. **Look the path up** among the published site menu items. Joomla stores each item's
   full route in `#__menu.path`, so this is one lookup per link in an index read once
   per run. The site root `/` is the home menu item.
3. **Not found? Follow the redirects** (if enabled, on by default): an HTTP `HEAD`
   request to the link, following up to 5 redirects by hand, and only to the site's
   own hosts (so the plugin never fetches anything elsewhere). The final URL is
   normalised and looked up again.
4. **Still nothing:** the link is reported as *broken* (a 404 or no answer) or as *no
   matching menu item* (the page exists, but is not a menu item; for example an article
   shown under a category blog). Those are left for a person.

Links with a query string (`?start=10`) are reported and not repaired: they may mean
more than the menu item alone.

## Repairing

The new link is what the editor's Menu button inserts: `index.php?Itemid=123`, plus
`&lang=xx` for a menu item in one language, plus the original `#anchor` if there was
one. Only the `href` value of the matching `<a>` tags changes; the rest of the HTML is
left exactly as it was (no DOM round trip that would re-format the article).

Before changing an article, repair checks:

- **Unchanged since the scan:** the scan recorded a hash of the intro text and full
  text. If someone edited the article since, it is skipped with "changed since the
  scan; scan again".
- **Not checked out:** an item open in someone's editor is skipped, so their save
  does not undo the repair (or the other way round).

Each kind is saved through its own component's model, as its editor saves it:
com_content's ArticleModel, com_categories' CategoryModel (with the category's own
extension, so its versions are filed under `com_content.category`, `com_contact.category`
and so on), and com_modules' ModuleModel. That last one deletes a module's menu
assignments on every save and writes them again from the form data, so the save sends
the current assignment along in the form's shape (the mode, and positive menu item ids;
the model's own getItem() returns them signed, which would turn "all pages except" into
"only").

An article is saved through com_content's own ArticleModel, as the article editor saves
it: content plugins, Smart Search and the version history all take part. (In Joomla 6
versions are written by the model, not the table.) So every repair has a version, with
the note "Link repair: N link(s) now point to menu items", and can be undone from
*Versions* in the article editor. *Modified by* is a user chosen in the task (for example
a "Link repair" user), so the changes are recognisable.

**Dry run** (on by default) does everything except the save, and logs what it would
change.

## What it stores

| Table | Holds |
|---|---|
| `#__linkrepair_scans` | one row per scan: task, status (running / finished), cursor, counts, start and end time |
| `#__linkrepair_links` | one row per link found: scan, item (kind and id), field, link text, link as found, resolution (state, final URL, menu item, new link), repair result and message |

States of a link: `repairable`, `query` (has a query string), `unmatched` (exists, no
menu item), `broken`, and after repair `repaired`, `skipped`, `failed`.

A new scan deletes the links of all but the previous scan, so the tables do not grow
without end. Uninstalling drops both tables.

## Reports

At the end of a scan, and after every repair run, a CSV is written to the site's log
folder: `linkrepair-scan-<id>.csv`, with the item's kind, id and title, the page URL (when the
article or category has its own menu item), link text, link as found, state, menu item and new
link, and the message. The task log in *System > Scheduled Tasks* has the totals.

## Security

- All SQL through the query builder with bound parameters, including inserts.
- HTTP only to the site's own hosts, `HEAD` only, short time-out, at most 5 hops.
- Nothing is changed in a dry run, and the default is a dry run.
- Only `href` values are rewritten, only in items from the scan, only when the
  item is unchanged since.

## Code structure

Composition root in `services/provider.php` (lazy plugin, constructor injection, no
`new` for services elsewhere). The pure parts are unit-tested without Joomla:

| Class | Job |
|---|---|
| `Html\LinkExtractor` | find `<a href>` and the link text in HTML |
| `Html\LinkRewriter` | replace one `href` value in `<a>` tags, nothing else |
| `Url\InternalLinkFilter` | internal or not, and the normalised path |
| `Resolve\MenuIndex` (interface) + `DatabaseMenuIndex` | path → menu item; article or category → page path |
| `Resolve\RedirectFollower` (interface) + `HttpRedirectFollower` | follow redirects on the own hosts |
| `Resolve\LinkResolver` | the steps under "Finding the menu item" |
| `Scan\Scanner` | one scan run within the time budget |
| `Repair\Repairer` | one repair run within the time budget |
| `Store\...` | the two tables, behind interfaces |
| `Content\ContentSources` + `ArticleSource`, `CategorySource`, `ModuleSource` | read items a page at a time; save one through its component's model (`AdminModelSaver`) |
| `Report\CsvReport` | the CSV |
| `Extension\LinkRepair` | the task plugin: parameters in, exit code out |

## Later

- Links to articles that are not a menu item (category blog items): link to the
  article (`index.php?option=com_content&view=article&id=..&catid=..`) instead.
- A small admin view of the report instead of the CSV.
