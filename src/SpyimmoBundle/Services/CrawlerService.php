<?php

namespace SpyimmoBundle\Services;

use SpyimmoBundle\Crawlers\AbstractCrawler;
use SpyimmoBundle\Crawlers\BaoCrawler;
use SpyimmoBundle\Crawlers\GdcCrawler;
use SpyimmoBundle\Crawlers\OmmiCrawler;
use SpyimmoBundle\Entity\Search;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrawlerService
{
    /**
     * @var AbstractCrawler[]
     */
    protected $crawlers;

    public function __construct()
    {
        $this->crawlers = array();
    }

    /**
     * @return AbstractCrawler[]
     */
    public function getCrawlers()
    {
        return $this->crawlers;
    }

    /**
     * @param mixed AbstractCrawler
     */
    public function addCrawler($crawler)
    {
        $this->crawlers[] = $crawler;
    }

    public function crawl(Search $search, $forceCrawler = null)
    {
        $cptNewOffers = 0;

        foreach ($this->crawlers as $crawler) {

            if ($forceCrawler && !$crawler->isForced($forceCrawler) ) {
                continue;
            }

            $cptNewOffers += $crawler->getOffers(
              // array(
              //   self::MIN_NB_BEDROOM => 1,
              //   self::MIN_NB_ROOM    => 2,
              //   self::MIN_SURFACE    => 25,
              //   self::MAX_BUDGET     => 950
              // ),
                $search,
                array()
            );

        }

        return $cptNewOffers;
    }
}
