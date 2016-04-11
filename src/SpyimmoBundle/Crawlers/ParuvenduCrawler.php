<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ParuvenduCrawler
 *
 */
class ParuvenduCrawler extends AbstractCrawler
{

    const NAME = 'ParuVendu';

    const SITE_URL = 'http://www.paruvendu.fr';

    const SEARCH_URL = 'http://www.paruvendu.fr/immobilier/annonceimmofo/liste/listeAnnonces?tt=5&tbApp=1&tbDup=1&tbChb=1&tbLof=1&tbAtl=1&tbPla=1&tbMai=1&tbVil=1&tbCha=1&tbPro=1&tbHot=1&tbMou=1&tbFer=1&at=1&pa=FR&lo=75&co=1&mb=0&ddlTri=dateMiseAJour&ddlOrd=desc&ddlFiltres=nofilter';

    public function __construct()
    {
        parent::__construct();

        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;

        $this->searchCriterias = array(
          Search::MIN_BUDGET  => 'px0',
          Search::MAX_BUDGET  => 'px1',
          Search::MIN_SURFACE => 'sur0',
          Search::MAX_SURFACE => 'sur1',
          Search::MIN_ROOM => 'nbp0',
          Search::MAX_ROOM => 'nbp1',
        );

        $this->criteriaClosures = array(
          'nbp0' => function ($val) {
              return $val * 10;
          },
          'nbp1' => function ($val) {
              return $val * 10;
          }
        );
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.annonce a:not(.im11_shop):not(.im11_v3d):not(.im16_consultpro)');
        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = $this->nodeFilter($node, 'h3');
                  $title = $title ? $title->text() : '';

                  if ($title != '') {
                      $url = self::SITE_URL . trim($node->attr('href'));

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

            $description = $this->nodeFilter($this->crawler, '.im12_txt_ann', $url);
            $description = $description ? $description->text() : '';

            $image = $this->nodeFilter($this->crawler, '#listePhotos .imdet15-blcphomain img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '#autoprix', $url);
            $price = $price ? $price->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }
}
