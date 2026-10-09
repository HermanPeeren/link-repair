<?php

/**
 * The Joomla constants the plugin reads.
 *
 * Joomla defines these when an application boots, so they exist at runtime but
 * in no file an analyser reads. Only the ones this plugin uses are here.
 */

declare(strict_types=1);

// The Joomla version, which the install script checks.
\define('JVERSION', '6.1.4');

// The administrator folder, the fallback for the site's log folder in the provider.
\define('JPATH_ADMINISTRATOR', __DIR__ . '/../joomla/administrator');
