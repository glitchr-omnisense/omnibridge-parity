<?php

namespace App\Action;

use Base\Repository\Analytics\PageViews as Counters;
use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page views omnibase counted (its analytics_page_view table), all pages and sources, through the core's own
 * repository interface (the persistence's implementation): the parity suite reads it to show that every cell counts
 * a page it sends, once, and nothing else (ParityTest::testEveryCellCountsAPageSentAndNothingElse).
 */
#[Route('/page-views', name: 'page_views')]
final class PageViews
{
    public function __construct(private readonly Counters $counters)
    {
    }

    public function __invoke(): Json
    {
        return new Json(['views' => $this->counters->countViews()]);
    }
}
