<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ExplorimoCrawler
 *
 */
class ExplorimoCrawler extends AbstractCrawler
{

    const NAME = 'Explorimo';
    const SITE_URL = 'http://www.explorimmo.com';

    const SEARCH_URL = 'http://www.explorimmo.com/resultat/annonces.html?transaction=location&type=Maison&type=Villa&type=Ferme&type=Moulin&type=Chalet&type=Appartement&type=Chambre&location=paris';

    public function __construct()
    {
        parent::__construct();

        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;

        $this->searchCriterias = array(
          Search::MIN_BUDGET  => 'priceMin',
          Search::MAX_BUDGET  => 'priceMax',
          Search::MIN_SURFACE => 'areaMin',
          Search::MAX_SURFACE => 'areaMax',
          Search::MIN_ROOM => 'roomMin',
          Search::MAX_ROOM => 'roomMax',
        );
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '#vue .bloc-item-header a');
        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = $node ? $node->text() : '';

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

            $fullTitle = $this->nodeFilter($this->crawler, '.container-main-informations h1', $url);
            $title = $fullTitle ? $fullTitle->text() : $title;

            $description = $this->nodeFilter($this->crawler, '.container-main .description', $url);
            $description = $description ? $description->text() : '';

            $descriptionBis = $this->nodeFilter($this->crawler, '.container-main .features', $url);
            $description .= $descriptionBis ? $descriptionBis->text() : '';

            $image = $this->nodeFilter($this->crawler, '.container-detail-slider img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '.container-main-informations .price', $url);
            $price = $price ? $price->text() : '';

            $tel = $this->nodeFilter($this->crawler, '.informations-agency .contact-phone-button', $url);
            $tel = $tel ? $tel->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price, null, null, $tel);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }
}
