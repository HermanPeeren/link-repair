<?php

/**
 * Fills the development site with what the link repair needs to be tried on:
 * menu items, articles with typed links of every kind, URL rewriting and a
 * redirect in .htaccess.
 *
 *   php tools/seed-dev-site.php [site URL]     default http://localhost/link-repair/joomla
 *
 * Content is made through Joomla's Web Services API, so Joomla's own models build
 * it: the menu tree, the routes and the article versions are as on a real site.
 * For that the script gives the admin user an API token. Development site only.
 * Run it once, on a fresh site.
 */

declare(strict_types=1);

$root    = \dirname(__DIR__);
$joomla  = $root . '/joomla';
$siteUrl = rtrim($argv[1] ?? 'http://localhost/link-repair/joomla', '/');

// The site's settings, read from configuration.php without loading Joomla.
$configuration = (string) file_get_contents($joomla . '/configuration.php');
$setting       = static function (string $name) use ($configuration): string {
    return preg_match('/public \$' . $name . " = '([^']*)'/", $configuration, $match) ? $match[1] : '';
};

$db     = new mysqli($setting('host'), $setting('user'), $setting('password'), $setting('db'));
$prefix = $setting('dbprefix');

/**
 * The first column of the first row of a query.
 *
 * @param list<int|string> $params
 */
$value = static function (string $sql, array $params = []) use ($db): mixed {
    $result = $db->execute_query($sql, $params);

    return $result instanceof mysqli_result ? $result->fetch_column() : null;
};

// An API token for the admin user, as the "User - Joomla API Token" plugin makes it.
$admin = (int) $value("SELECT id FROM {$prefix}users WHERE username = ?", ['admin']);

if ($admin === 0) {
    fwrite(STDERR, "No user 'admin' on the development site.\n");
    exit(1);
}

$seed = base64_encode(random_bytes(32));
$db->execute_query("DELETE FROM {$prefix}user_profiles WHERE user_id = ? AND profile_key LIKE 'joomlatoken.%'", [$admin]);
$db->execute_query(
    "INSERT INTO {$prefix}user_profiles (user_id, profile_key, profile_value, ordering)"
    . " VALUES (?, 'joomlatoken.token', ?, 1), (?, 'joomlatoken.enabled', '1', 2)",
    [$admin, $seed, $admin]
);

$token = base64_encode('sha256:' . $admin . ':' . hash_hmac('sha256', base64_decode($seed), $setting('secret')));

/**
 * @param non-empty-string     $method
 * @param array<string, mixed> $body
 *
 * @return array<string, mixed>
 */
$api = static function (string $method, string $path, array $body) use ($siteUrl, $token): array {
    if ($method === '') {
        throw new InvalidArgumentException('An HTTP method is needed.');
    }

    $curl = curl_init($siteUrl . '/api/index.php/v1/' . $path);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/vnd.api+json',
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
    ]);
    $response = (string) curl_exec($curl);
    $status   = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    $data = json_decode($response, true);

    if ($status >= 300 || !\is_array($data)) {
        fwrite(STDERR, "{$method} {$path}: HTTP {$status}\n{$response}\n");
        exit(1);
    }

    return $data;
};

$article = static function (string $title, string $intro, string $full = '') use ($api): int {
    $data = $api('POST', 'content/articles', [
        'title'       => $title,
        'alias'       => strtolower(str_replace(' ', '-', $title)),
        'catid'       => 2,
        'articletext' => $full === '' ? $intro : $intro . '<hr id="system-readmore">' . $full,
        'state'       => 1,
        'language'    => '*',
    ]);

    return (int) $data['data']['id'];
};

// The API does not fill in a menu item's component from its link; without it the
// site answers "Component not found".
$contentComponent = (int) $value("SELECT extension_id FROM {$prefix}extensions WHERE type = 'component' AND element = 'com_content'");

$menuItem = static function (string $title, string $alias, int $articleId, int $parentId = 1) use ($api, $contentComponent): int {
    $data = $api('POST', 'menus/site/items', [
        'menutype'     => 'mainmenu',
        'title'        => $title,
        'alias'        => $alias,
        'type'         => 'component',
        'component_id' => $contentComponent,
        'link'         => 'index.php?option=com_content&view=article&id=' . $articleId,
        'request'      => ['id' => $articleId],
        'parent_id'    => $parentId,
        'published'    => 1,
        'language'     => '*',
    ]);

    return (int) $data['data']['id'];
};

// The pages links point to.
$guide = $article('User Guide', '<p>The user guide.</p>');
$seo   = $article('SEO Basics', '<p>About search engines.</p>');
$menus = $article('Menus Explained', '<p>About menus.</p><h2 id="items">Menu items</h2>');

$guideItem = $menuItem('User Guide', 'user-guide', $guide);
$menuItem('SEO Basics', 'seo-basics', $seo, $guideItem);
$menuItem('Menus', 'menus', $menus, $guideItem);

$host = (string) parse_url($siteUrl, PHP_URL_HOST);
$base = (string) parse_url($siteUrl, PHP_URL_PATH);

// The articles with typed links, of every kind the plugin meets.
$source = $article(
    'Links Of Every Kind',
    '<p>Current path: <a href="' . $base . '/user-guide/seo-basics">SEO basics</a>.</p>'
    . '<p>Old path, redirected: <a href="' . $base . '/user-manual/seo-basics">SEO, old link</a>.</p>'
    . '<p>With index.php and an anchor: <a href="' . $base . '/index.php/user-guide/menus#items">menu items</a>.</p>'
    . '<p>Absolute: <a href="http://' . $host . $base . '/user-guide/menus">menus, absolute</a>.</p>'
    . '<p>With a query: <a href="' . $base . '/user-guide/menus?start=10">menus, page 2</a>.</p>'
    . '<p>Broken: <a href="' . $base . '/gone-page">a page that is gone</a>.</p>'
    . '<p>Untouched: <a href="https://www.joomla.org/">joomla.org</a>, <a href="images/joomla_black.png">an image</a>, '
    . '<a href="index.php?Itemid=101">home, already a menu link</a>, <a href="#top">the top</a>, <a href="mailto:a@example.org">mail</a>.</p>',
    '<p>In the full text: <a href="' . $base . '/user-manual/seo-basics">SEO again</a>.</p>'
);
$linksItem = $menuItem('Links Of Every Kind', 'links', $source);

$twice = $article(
    'Same Link Twice',
    '<p><a href="' . $base . '/user-manual/seo-basics">once</a> and <a href="' . $base . '/user-manual/seo-basics">twice</a>.</p>'
);

// A category description and a custom module with typed links. The module is shown on
// all pages except "Links Of Every Kind": a repair must keep exactly that assignment.
$category = $api('POST', 'content/categories', [
    'title'       => 'Guides',
    'alias'       => 'guides',
    'extension'   => 'com_content',
    'parent_id'   => 1,
    'published'   => 1,
    'language'    => '*',
    'description' => '<p>See <a href="' . $base . '/user-manual/seo-basics">SEO</a> and <a href="' . $base . '/user-guide/menus">menus</a>.</p>',
]);

// The Web Services API cannot make a module (it drops the params, which the table
// requires), so the module goes into the database directly. "All pages except" is
// stored as the menu item id made negative.
$db->execute_query(
    "INSERT INTO {$prefix}modules (title, note, content, ordering, position, published, module, access, showtitle, params, client_id, language)"
    . " VALUES (?, '', ?, 1, 'sidebar-right', 1, 'mod_custom', 1, 1, '{\"prepare_content\":\"0\"}', 0, '*')",
    ['Useful Links', '<ul><li><a href="' . $base . '/user-manual/seo-basics">SEO</a></li></ul>']
);
$module = (int) $db->insert_id;
$db->execute_query("INSERT INTO {$prefix}modules_menu (moduleid, menuid) VALUES (?, ?)", [$module, -$linksItem]);

printf("Category %d, custom module %d.\n", (int) $category['data']['id'], $module);

// The "Welcome to Joomla" tour starts by itself on a fresh site and sends the browser
// to the dashboard, which makes the Cypress specs land there instead of on the task form.
$db->execute_query("UPDATE {$prefix}guidedtours SET autostart = 0 WHERE autostart = 1");

// URL rewriting, with one redirect rule like the ones guide.joomla.org uses.
$htaccess = (string) file_get_contents($joomla . '/htaccess.txt');
$rules    = "RewriteBase {$base}/\n\n"
    . "RewriteRule ^(?:index\\.php/)?user-manual(/.*)?$ {$base}/user-guide\$1 [R=301,L]";
$htaccess = str_replace('# RewriteBase /', $rules, $htaccess);
file_put_contents($joomla . '/.htaccess', $htaccess);
passthru('php ' . escapeshellarg($joomla . '/cli/joomla.php') . ' config:set sef_rewrite=true');

printf("Articles %d, %d, %d (targets), %d (links of every kind), %d (same link twice).\n", $guide, $seo, $menus, $source, $twice);
