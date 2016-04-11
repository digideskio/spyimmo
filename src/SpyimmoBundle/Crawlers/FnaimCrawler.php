<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class FnaimCrawler
 *
 */
class FnaimCrawler extends AbstractCrawler
{
    const NAME = 'Fnaim';
    const SITE_URL = 'http://www.fnaim.fr';

    const SEARCH_URL = 'http://www.fnaim.fr/18-louer.htm?TRANSACTION=2&localites=[{"id":75,"type":2,"label":"PARIS (75)"}]&TYPE[]=1&TYPE[]=2&NB_PIECES[]=%s&SURFACE[]=%s&SURFACE[]=&SURFACE_TERRAIN[]=&SURFACE_TERRAIN[]=&PRIX[]=&PRIX[]=%s&submit=Valider&idtf=18&op=&cp=d41d8cd98f00b204e980&mp=20';

    public function __construct()
    {
        parent::__construct();
        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        $this->searchUrl = $this->transformFnaimUrl();
        $this->searchUrl = $this->generateUrl($this->searchUrl, $criterias);
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.annonce_liste ul li h3 a');
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

            $fullTitle = $this->nodeFilter($this->crawler, '#contenu .annonce_fiche h1', $url);
            $title = $fullTitle ? $fullTitle->text() : $title;

            $description = $this->nodeFilter($this->crawler, '#contenu .annonce_fiche .description p', $url);
            $description = $description ? $description->text() : '';

            $descriptionBisBlock = $this->nodeFilter($this->crawler, '#contenu .annonce_fiche .caracteristique li');
            $descriptionBis = $this->nodeFilter($descriptionBisBlock->first(), 'li');
            if ($descriptionBis) {
                $descriptionBis->each(
                  function (Crawler $node) use (&$description) {
                      $description .= ' ' . $node->text();
                  }
                );
            }

            $image = $this->nodeFilter($this->crawler, '#contenu .annonce_fiche #diapo_annonce img', $url);
            $image = $image && (count($image) > 0) ? $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '#contenu .annonce_fiche .entete h3 span', $url);
            $price = $price ? $price->text() : '';

            $tel = $this->nodeFilter($this->crawler, '#contenu .actions #agence_call', $url);
            $tel = $tel ? $tel->text() : '';

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price, null, null, $tel);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }

    protected function transformFnaimUrl()
    {
        return sprintf(self::SEARCH_URL, CrawlerService::MIN_NB_ROOM, CrawlerService::MIN_SURFACE, CrawlerService::MAX_BUDGET);
    }

}
