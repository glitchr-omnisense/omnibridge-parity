<?php

namespace App\Action;

use Base\Screen\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page views omnibase counted (its analytics_page_view table), all pages and sources: the parity suite reads it
 * to show that both hosts count a page they send, once, and nothing else (ParityTest::testBothHostsCountAPageSentAndNothingElse).
 */
#[Route('/page-views', name: 'page_views')]
final class PageViews
{
    public function __construct(private readonly EntityManagerInterface $entities)
    {
    }

    public function __invoke(): Json
    {
        return new Json(['views' => (int) $this->entities->getConnection()->fetchOne('SELECT COALESCE(SUM(views), 0) FROM analytics_page_view')]);
    }
}
