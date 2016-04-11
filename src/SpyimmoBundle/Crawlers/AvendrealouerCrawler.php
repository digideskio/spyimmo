<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class AvendrealouerCrawler
 *
 */
class AvendrealouerCrawler extends AbstractCrawler
{
    const NAME = 'Avendrealouer';

    const SITE_URL = 'http://www.avendrealouer.fr';

    const SEARCH_URL = 'http://www.avendrealouer.fr/recherche.html?pageIndex=1&sortPropertyName=Price&sortDirection=Descending&searchTypeID=2&typeGroupCategoryID=6&transactionId=2&localityIds=3-75&typeGroupIds=47,48';

    public function __construct()
    {
        parent::__construct();

        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;

        $this->searchCriterias = array(
          Search::MIN_BUDGET => 'minimumPrice',
          Search::MAX_BUDGET => 'maximumPrice',
          Search::MIN_SURFACE => 'minimumSurface',
          Search::MAX_SURFACE => 'maximumSurface',
          Search::MIN_BEDROOM => 'bedroomComfortIds'
        );
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '#result-list li .details .linkCtnr');
        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = $this->nodeFilter($node, 'ul');
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

            $fullTitle = $this->nodeFilter($this->crawler, '.header h1 .mainh1', $url);
            $title = $fullTitle ? $fullTitle->text() : $title;

            $description = $this->nodeFilter($this->crawler, '.col-main #propertyDesc', $url);
            $description = $description ? $description->text() : '';

            $descriptionBis = $this->nodeFilter($this->crawler, '.col-main .descCtnr #desc-items li', $url);
            if ($descriptionBis) {
                $descriptionBis->each(
                  function (Crawler $node) use (&$description) {
                      $description .= ' ' . $node->text();
                  }
                );
            }

            $image = $this->nodeFilter($this->crawler, '.topSummary .slideCtnr ul img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '.topSummary .display-price', $url);
            $price = $price ? $price->text() : '';

            $tel = $this->nodeFilter($this->crawler, '.rightFormCtnr #display-phonenumber-1', $url);
            $tel = $tel ? $tel->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price, null, null, $tel);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", $this->name, $url, $e->getMessage());
        } catch (RequestException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", $this->name, $url, $e->getMessage());
        }


        return 0;
    }
}
