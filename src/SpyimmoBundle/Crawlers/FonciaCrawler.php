<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class FonciaCrawler
 *
 */
class FonciaCrawler extends AbstractCrawler
{
    const NAME = 'Foncia';
    const SITE_URL = 'http://fr.foncia.com';

    const SEARCH_URL = 'http://fr.foncia.com/location/paris-75/(params)/on/(surface_min)/%s/(prix_max)/%s/(pieces)/%s';

    public function __construct()
    {
        parent::__construct();
        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {

        $this->searchUrl = $this->transformFonciaUrl($criterias);
        $this->searchUrl = $this->generateUrl($this->searchUrl, $criterias);
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.Content-overflow article.TeaserOffer a:not(.Modal-link):not(.TeaserOffer-ill)');
        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = $node ? $node->text() : '';
                  $url = self::SITE_URL . trim($node->attr('href'));

                  $isNew = $this->getOfferDetail($url, $title);
                  $this->cptNew += ($isNew) ? 1 : 0;
                  ++$this->cpt;

              }
            );
        }

        return $this->cptNew;
    }

    protected function getOfferDetail($url, $title)
    {
        $isNew = parent::getOfferDetail($url, $title);

        if(!$isNew) {
            return 0;
        }

        try {
            $this->crawler = $this->client->request('GET', $url);

            $fullTitle = $this->nodeFilter($this->crawler, '.Content-overflow .OfferTop-head h1', $url);
            $title = $fullTitle ? $fullTitle->text() : $title;

            $descriptionBlock = $this->nodeFilter($this->crawler, '.Content-overflow .OfferDetails', $url);
            $description = $this->nodeFilter($descriptionBlock->first(), '.OfferDetails-content', $url);
            $description = $description ? $description->text() : '';

            $image = $this->nodeFilter($this->crawler, '.OfferSlider .OfferSlider-main-slides li img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '.Content-main .OfferTop-price', $url);
            $price = $price ? $price->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }

    protected function transformFonciaUrl($criterias)
    {
        $roomString = '';
        if (isset($criterias[CrawlerService::MIN_NB_ROOM])) {
            $nbRoom = $criterias[CrawlerService::MIN_NB_ROOM];
            for($i=$nbRoom;$i<6;++$i) {
                $roomString .= $i.'--';
            }
            $roomString = substr($roomString, 0, -2);
        }

        return sprintf(self::SEARCH_URL, CrawlerService::MIN_SURFACE, CrawlerService::MAX_BUDGET, $roomString);
    }
}
