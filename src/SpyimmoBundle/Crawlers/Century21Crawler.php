<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Century21Crawler
 *
 */
class Century21Crawler extends AbstractCrawler
{
    const NAME = 'Century21';
    const SITE_URL = 'http://www.century21.fr/';

    const SEARCH_URL = 'http://www.century21.fr/annonces/location-appartement/v-paris/s-%s-/st-0-/b-0-%s/p-%s/page-1/';

    public function __construct()
    {
        parent::__construct();
        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        $this->searchUrl = $this->transformCenturyUrl();
        $this->searchUrl = $this->generateUrl($this->searchUrl, $search);
        parent::getOffers($search, $excludedCrawlers);
die(var_dump($this->searchUrl));
        $offerBlock = $this->nodeFilter($this->crawler, '.annoncesListeBien');
        $offers = $this->nodeFilter($offerBlock->first(), 'li a');
        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = '';
                  $url = self::SITE_URL . trim($node->attr('href'));

                  $isNew = $this->getOfferDetail($url, $title);
                  $this->cptNew += ($isNew) ? 1 : 0;
                  ++$this->cpt;

              }
            );
        }

        return $this->cptNew;
    }

    protected function transformCenturyUrl()
    {
        return sprintf(self::SEARCH_URL, Search::MIN_SURFACE, Search::MAX_BUDGET, Search::MIN_ROOM);
    }

    protected function getOfferDetail($url, $title)
    {
        $isNew = parent::getOfferDetail($url, $title);

        if(!$isNew) {
            return 0;
        }

        try {
            $this->crawler = $this->client->request('GET', $url);

            $fullTitle = $this->nodeFilter($this->crawler, 'title', $url);
            $title = $fullTitle ? $fullTitle->text() : $title;

            $description = $this->nodeFilter($this->crawler, '#focusAnnonceV2 .precision .desc-fr', $url);
            $description = $description ? $description->text() : '';

            $descriptionBisBlock = $this->nodeFilter($this->crawler, '#ficheDetail .box');
            $descriptionBis = $this->nodeFilter($descriptionBisBlock->first(), 'li');
            if ($descriptionBis) {
                $descriptionBis->each(
                  function (Crawler $node) use (&$description) {
                      $description .= ' ' . $node->text();
                  }
                );
            }

            $image = $this->nodeFilter($this->crawler, '#galeriePIX img.img-owl-bien', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '#focusAnnonceV2 .tarif .yellow b', $url);
            $price = $price ? $price->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }
}
