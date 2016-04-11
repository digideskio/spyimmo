<?php

namespace SpyimmoBundle\Crawlers;

use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class SelogerCrawler
 *
 */
class SelogerCrawler extends AbstractCrawler
{
    const NAME = 'SeLoger';

    const SEARCH_URL = 'http://www.seloger.com/list.htm?idtt=1&idtypebien=1,9&cp=75&tri=initial&si_meuble=0';

    public function __construct()
    {
        parent::__construct();

        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;

        $this->searchCriterias = array(
          Search::MIN_BUDGET => 'pxmin',
          Search::MAX_BUDGET => 'pxmax',
          Search::MIN_SURFACE => 'surfacemin',
          Search::MAX_SURFACE => 'surfacemax',
          Search::MIN_BEDROOM => 'nb_chambresmin',
          Search::MIN_ROOM => 'nb_piecesmin',
          Search::MAX_ROOM => 'nb_piecesmax',
        );
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.listing_infos h2 a');

        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = $node ? $node->text() : '';
                  if ($title != '') {
                      $url = trim($node->attr('href'));
                      $isNew = $this->getOfferDetail($url, $title);
                      $this->cptNew += ($isNew) ? 1 : 0;
                  }
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

            $fullTitle = $this->nodeFilter($this->crawler, 'h1.detail-title', $url);
            $title = $fullTitle ? $fullTitle->text() : $title;

            $description = $this->nodeFilter($this->crawler, '.detail__description .description', $url);
            $description = $description ? $description->text() : '';

            $descriptionBis = $this->nodeFilter($this->crawler, '.detail__description .description-liste li', $url);
            if ($descriptionBis) {
                $descriptionBis->each(
                  function (Crawler $node) use (&$description) {
                      $description .= ' ' . $node->text();
                  }
                );
            }

            $image = $this->nodeFilter($this->crawler, '.carrousel_image img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '#price', $url);
            $price = $price ? $price->text() : '';

            $tel = $this->nodeFilter($this->crawler, '.detail__description .action__detail-tel span', $url);
            $tel = $tel ? $tel->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price, null, null, $tel);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }
}
