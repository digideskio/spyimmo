<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class PapCrawler
 *
 */
class PapCrawler extends AbstractCrawler
{
    const NAME = 'Pap';
    const SITE_URL = 'http://www.pap.fr';

    const SEARCH_URL = 'http://www.pap.fr/annonce/location-appartement-maison-vide-paris-75-g439-%sjusqu-a-%s-euros-a-partir-de-%s-m2';

    public function __construct()
    {
        parent::__construct();
        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        $this->searchUrl = $this->transformPapUrl($criterias);
        $this->searchUrl = $this->generateUrl($this->searchUrl, $criterias);
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.annonce .header-annonce a');
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

    protected function transformPapUrl($criterias)
    {
        $roomString = '';
        if (isset($criterias[CrawlerService::MIN_NB_ROOM])) {
            $nbRoom = $criterias[CrawlerService::MIN_NB_ROOM];
            if ($nbRoom > 1) {
                $roomString = sprintf('a-partir-du-%s-pieces-', CrawlerService::MIN_NB_ROOM);
            } else {
                $roomString = sprintf('a-partir-du-studio-');
            }
        }

        return sprintf(self::SEARCH_URL, $roomString, CrawlerService::MAX_BUDGET, CrawlerService::MIN_SURFACE);
    }

    protected function getOfferDetail($url, $title)
    {
        $isNew = parent::getOfferDetail($url, $title);

        if(!$isNew) {
            return 0;
        }

        try {
            $this->crawler = $this->client->request('GET', $url);

            $description = $this->nodeFilter($this->crawler, '.text-annonce-container', $url);
            $description = $description ? $description->text() : '';

            $descriptionBis = $this->nodeFilter($this->crawler, '.footer-descriptif', $url);
            $description .= $descriptionBis ? $descriptionBis->text() : '';

            $image = $this->nodeFilter($this->crawler, '.showcase-content img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '.descriptif-container .prix', $url);
            $price = $price ? $price->text() : '';

            $tel = $this->nodeFilter($this->crawler, '.contact-proprietaire-simple span.telephone', $url);
            $tel = $tel && (count($tel) > 0) ? $tel->first()->text() : null;

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price, null, null, $tel);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }
}
