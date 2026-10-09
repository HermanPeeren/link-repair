<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LinkRepair\Tests\Unit\Html;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LinkRepair\Html\HtmlLink;
use Yepr\Plugin\Task\LinkRepair\Html\LinkExtractor;

final class LinkExtractorTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    private function extract(string $html): array
    {
        return array_map(
            static fn (HtmlLink $link): array => [$link->href, $link->text],
            (new LinkExtractor())->extract($html)
        );
    }

    public function testFindsLinksWithTheirText(): void
    {
        self::assertSame(
            [['/user-guide/seo', 'SEO basics'], ['https://example.org', 'elsewhere']],
            $this->extract(
                '<p>See <a href="/user-guide/seo">SEO basics</a> and '
                . '<a class="x" href="https://example.org" target="_blank">elsewhere</a>.</p>'
            )
        );
    }

    public function testDecodesEntitiesInTheLinkAndTheText(): void
    {
        self::assertSame(
            [['index.php?option=com_content&view=article&id=1', 'Fish & Chips']],
            $this->extract('<a href="index.php?option=com_content&amp;view=article&amp;id=1">Fish &amp; Chips</a>')
        );
    }

    public function testReadsSingleQuotedAndUnquotedLinks(): void
    {
        self::assertSame(
            [['/one', 'One'], ['/two', 'Two']],
            $this->extract("<a href='/one'>One</a> <a href=/two>Two</a>")
        );
    }

    public function testTextIsFlattenedAndAnImageUsesItsAltText(): void
    {
        self::assertSame(
            [['/a', 'Bold and italic'], ['/b', '[image] Logo'], ['/c', '[image]']],
            $this->extract('<a href="/a"><strong>Bold</strong>  and
                <em>italic</em></a><a href="/b"><img src="x.png" alt="Logo"></a><a href="/c"><img src="y.png"></a>')
        );
    }

    public function testAnchorsWithoutHrefAreSkipped(): void
    {
        self::assertSame([['/x', 'x']], $this->extract('<a name="top">Top</a><a href="/x">x</a>'));
    }

    public function testTagsThatOnlyStartWithAAreNoLinks(): void
    {
        self::assertSame([], $this->extract('<abbr title="Search Engine Friendly">SEF</abbr><aside>no link</aside>'));
    }

    public function testEmptyHtml(): void
    {
        self::assertSame([], $this->extract(''));
    }
}
