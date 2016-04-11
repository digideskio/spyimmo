<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class OmmiCrawler
 *
 */
class OmmiCrawler extends AbstractCrawler
{
    const NAME = 'Ommi';
    const SITE_URL = 'https://www.ommi.fr';

    const SEARCH_URL = 'https://www.ommi.fr/recherche/locations-vide-paris-75-jusqu-a-%s-euros-a-partir-de-%s-m2-s787547';

    public function __construct()
    {
        parent::__construct();
        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        $this->searchUrl = $this->transformOmmiUrl();
        $this->searchUrl = $this->generateUrl($this->searchUrl, $search);
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.search-results .grid .item-title a');
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

    protected function transformOmmiUrl()
    {
        return sprintf(self::SEARCH_URL, Search::MAX_BUDGET, Search::MIN_SURFACE);
    }

    protected function getOfferDetail($url, $title)
    {
        $isNew = parent::getOfferDetail($url, $title);

        if(!$isNew) {
            return 0;
        }

        try {
            $this->crawler = $this->client->request('GET', $url);

            $description = '';

            $descriptionBis = $this->nodeFilter($this->crawler, '.table-discription .cellright p', $url);
            if ($descriptionBis) {
                $descriptionBis->each(
                    function (Crawler $node) use (&$description) {
                        $description .= ' ' . $node->text();
                    }
                );
            }

            $image = $this->nodeFilter($this->crawler, '.tab-annonce-photo .table-photo img', $url);
            $image = $image && (count($image) > 0) ? self::SITE_URL . $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '.box-price .price', $url);
            $price = $price ? $price->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }


        return 0;
    }
}
