<?php

namespace SpyimmoBundle\Crawlers;

use GuzzleHttp\Exception\RequestException;
use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class LeboncoinCrawler
 *
 */
class LeboncoinCrawler extends AbstractCrawler
{

    const NAME = 'LeBonCoin';

    const SEARCH_URL = 'http://www.leboncoin.fr/locations/offres/ile_de_france/paris/?th=1&furn=2';

    public function __construct()
    {
        parent::__construct();

        $this->name = self::NAME;
        $this->searchUrl = self::SEARCH_URL;

        $this->searchCriterias = array(
          Search::MIN_BUDGET  => 'mrs',
          Search::MAX_BUDGET  => 'mre',
          Search::MIN_SURFACE => 'sqs',
          Search::MAX_SURFACE => 'sqe',
          Search::MIN_ROOM => 'ros',
          Search::MAX_ROOM => 'roe',
        );

        $sizeClosure = function ($surf) {
            $valueSize = array(
              0,
              20,
              25,
              30,
              35,
              40,
              50,
              60,
              70,
              80,
              90,
              100,
              110,
              120,
              150,
              300
            );
            foreach ($valueSize as $key => $val) {
                if ($surf <= $val) {
                    return $key;
                }
            }

            return count($valueSize);
        };

        $this->criteriaClosures = array(
          'sqs' => $sizeClosure,
          'sqe' => $sizeClosure
        );
    }

    public function getOffers(Search $search, $excludedCrawlers = array())
    {
        parent::getOffers($search, $excludedCrawlers);

        $offers = $this->nodeFilter($this->crawler, '.mainList .list_item');
        if ($offers) {
            $offers->each(
              function (Crawler $node) {

                  $title = $this->nodeFilter($node, '.item_title');
                  $title = $title ? $title->text() : '';
                  if ($title != '') {
                      $url = 'http:' . trim($node->attr('href'));
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

            $description = $this->nodeFilter($this->crawler, '.properties_description .value', $url);
            $description = $description ? $description->text() : '';

            $descriptionBis = $this->nodeFilter($this->crawler, '.properties .line:not(.properties_description)', $url);
            if ($descriptionBis) {
                $descriptionBis->each(
                  function (Crawler $node) use (&$description) {
                      $description .= ' ' . $node->text();
                  }
                );
            }
            $description = $this->removeDescriptionInfo($description);

            $image = $this->nodeFilter($this->crawler, '.item_image img', $url);
            $image = $image && (count($image) > 0) ? 'http:' . $image->first()->attr('src') : null;

            $price = $this->nodeFilter($this->crawler, '.item_price .value', $url);
            $price = $price ? $price->text() : '';

            // get Contact Phone Number via API
            $tel = $this->fetchTel($url);

            return $this->offerManager->createOffer($title, $description, $image, $url, self::NAME, $price, null, null, $tel);
        } catch (\InvalidArgumentException $e) {
            echo sprintf("[%s] unable to parse %s: %s\n", self::NAME, $url, $e->getMessage());
        }

        return 0;
    }

    protected function fetchTel($url)
    {
        $tel = null;
        $offerId = $this->nodeFilter($this->crawler, '.saveAd', $url);
        $offerId = $offerId ? $offerId->attr('data-savead-id') : '';
        preg_match('/var\s+apiKey\s*=\s*"([a-z0-9]+)"/mi', $this->crawler->html(), $result);

        if (isset($result[1]) && $offerId) {
            $apiKey = $result[1];
            $response = $this->client->getClient()->post(
                'https://api.leboncoin.fr/api/utils/phonenumber.json',
                [
                    'form_params' => [
                        'list_id' => $offerId,
                        'app_id' => 'leboncoin_web_utils',
                        'key' => $apiKey,
                        'text' => '1'
                    ]
                ]
            );
            $result = json_decode($response->getBody());
            if(property_exists($result->utils, 'phonenumber') ) {
                $tel = $result->utils->phonenumber;
            }
        }

        return $tel;
    }

    protected function removeDescriptionInfo($description)
    {
        // remove meublé/non meublé legend
        $leBonCoinRegexp = '/(meubl[éÉ]\W*\/\W*non\W+meubl[éÉ]\W+)/mi';
        return preg_replace($leBonCoinRegexp, '', $description);
    }
}
