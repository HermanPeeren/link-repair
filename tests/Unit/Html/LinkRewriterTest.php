<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Html;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Html\LinkRewriter;

final class LinkRewriterTest extends TestCase
{
    public function testReplacesOnlyTheHrefValue(): void
    {
        [$html, $count] = (new LinkRewriter())->rewrite(
            '<p>See <a class="btn" href="/user-manual/seo" title="SEO">the <b>SEO</b> page</a>.</p>',
            '/user-manual/seo',
            'index.php?Itemid=12'
        );

        self::assertSame('<p>See <a class="btn" href="index.php?Itemid=12" title="SEO">the <b>SEO</b> page</a>.</p>', $html);
        self::assertSame(1, $count);
    }

    public function testEncodesTheNewLinkForHtml(): void
    {
        [$html] = (new LinkRewriter())->rewrite('<a href="/fr/page">x</a>', '/fr/page', 'index.php?Itemid=5&lang=fr-FR#top');

        self::assertSame('<a href="index.php?Itemid=5&amp;lang=fr-FR#top">x</a>', $html);
    }

    public function testReplacesEveryOccurrenceAndNothingElse(): void
    {
        [$html, $count] = (new LinkRewriter())->rewrite(
            '<a href="/a">1</a> /a <a href="/a/b">2</a> <a href="/a">3</a> <img src="/a">',
            '/a',
            'index.php?Itemid=1'
        );

        self::assertSame('<a href="index.php?Itemid=1">1</a> /a <a href="/a/b">2</a> <a href="index.php?Itemid=1">3</a> <img src="/a">', $html);
        self::assertSame(2, $count);
    }

    public function testMatchesTheDecodedLinkAndKeepsSingleQuotes(): void
    {
        [$html, $count] = (new LinkRewriter())->rewrite("<a href='/x?a=1&amp;b=2'>x</a>", '/x?a=1&b=2', 'index.php?Itemid=3');

        self::assertSame("<a href='index.php?Itemid=3'>x</a>", $html);
        self::assertSame(1, $count);
    }

    public function testLeavesTheHtmlAloneWhenTheLinkIsNotThere(): void
    {
        $original = '<p><a href="/other">x</a></p>';

        self::assertSame([$original, 0], (new LinkRewriter())->rewrite($original, '/missing', 'index.php?Itemid=1'));
    }
}
