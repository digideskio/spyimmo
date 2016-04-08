<?php

namespace SpyimmoBundle\Crawlers;

use Goutte\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use SpyimmoBundle\Logger\SpyimmoLogger;
use SpyimmoBundle\Manager\OfferManager;

/**
 * Class AbstractCrawler
 *
 */
abstract class AbstractCrawler
{

    const TIMEOUT = 30;

    protected $name;
    protected $searchUrl;

    /** @var Client */
    protected $client;
    /** @var  Crawler */
    protected $crawler;
    /** @var  SpyimmoLogger */
    protected $spyimmoLogger;

    /** @var  OfferManager */
    protected $offerManager;

    protected $cptNew;
    protected $cpt;
    protected $searchCriterias;
    protected $criteriaClosures;

    public function __construct()
    {
        $this->client = new Client(['timeout' => self::TIMEOUT]);
        $this->cptNew = 0;
        $this->cpt = 0;
        $this->searchCriterias = array();
        $this->criteriaClosures = array();
    }

    public function getOffers($criterias, $excludedCrawlers = array())
    {
        $this->cpt = 0;
        $this->cptNew = 0;

        if (!$this->isScheduled() || in_array($this->name, $excludedCrawlers)) {
            return 0;
        }

        $this->spyimmoLogger->logSection(sprintf('[%s] Fetching offers %s', $this->name,
            implode(', ', array_map(
                function ($v, $k) { return $k . '=' . $v; },
                $criterias,
                array_keys($criterias)
            ))
        ));

        if ($this->searchUrl) {
            $params = $this->generateSearchUrl($criterias, $this->criteriaClosures);;
            $this->searchUrl = $this->searchUrl . ($params ? '&'.$params : '');
            try {
                $this->crawler = $this->client->request('GET', $this->searchUrl);
            } catch (RequestException $e) {
                $this->spyimmoLogger->logError(sprintf("EXCEPTION: %s\n", $e->getMessage()));

                return 0;
            }
        }
    }

    protected function getOfferDetail($url, $title)
    {
        $url = $this->offerManager->cleanUrl($url);
        $isNew = $this->offerManager->isNew($url);
        if($isNew){
            $this->spyimmoLogger->logInfo(sprintf('   <%s>Fetching new url [%s]</%s>', 'info', substr($url, 0, 120), 'info'));
        } else {
            $this->spyimmoLogger->logDebug(sprintf('   <%s>Fetching url [%s]</%s>', 'comment', substr($url, 0, 120), 'comment'));
        }

        return $isNew;
    }

    public function generateSearchUrl($criterias, $criteriaClosures = array())
    {
        $argsQuery = array();
        foreach ($criterias as $key => $val) {
            if (isset($this->searchCriterias[$key])) {
                $urlParamKey = $this->searchCriterias[$key];
                if (isset($criteriaClosures[$urlParamKey])) {
                    $closure = $criteriaClosures[$urlParamKey];
                    $val = $closure($val);
                }

                $argsQuery[$urlParamKey] = $val;
            }
        }

        return http_build_query($argsQuery);
    }

    public function isScheduled()
    {
        $authorizedHours = array(
          7,
          8,
          9,
          10,
          11,
          12,
          13,
          14,
          15,
          16,
          17,
          18,
          19,
          20,
          21,
          22,
          23,
          0
        );

        $currentHour = intval(date('H'));
        if (in_array($currentHour, $authorizedHours)) {
            return true;
        }

        return false;
    }

    public function isForced($name = null)
    {
        if($name && $this->name == $name){
            return true;
        }
        return false;
    }

    /**
     * @param Crawler $node
     * @param         $selector
     * @param null    $url
     *
     * @return Crawler|void
     */
    protected function nodeFilter(Crawler $node, $selector, $url = null)
    {
        try {
            $filtered = $node->filter($selector);
            if ($filtered->count() > 0) {
                return $filtered;
            } else {
                $this->spyimmoLogger->logInfo(sprintf("     Unable to fetch selector %s on %s\n", $selector, $url ? $url : $this->searchUrl));

                return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->spyimmoLogger->logInfo(sprintf("     Unable to fetch selector %s on %s\n", $selector, $url ? $url : $this->searchUrl));

            return;
        }
    }

    protected function generateUrl($url, $criteria)
    {
        return str_replace(array_keys($criteria), array_values($criteria), $url);
    }

    protected function formatHtmlData($string)
    {
        return str_replace('<br>', ' ', nl2br(trim($string)));
    }

    /**
     * @param OfferManager $offerManager
     */
    public function setOfferManager($offerManager)
    {
        $this->offerManager = $offerManager;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getSearchUrl()
    {
        return $this->searchUrl;
    }

    /**
     * @param mixed $searchUrl
     */
    public function setSearchUrl($searchUrl)
    {
        $this->searchUrl = $searchUrl;
    }

    /**
     * @param SpyimmoLogger $logger
     */
    public function setSpyimmoLogger(SpyimmoLogger $logger)
    {
        $this->spyimmoLogger = $logger;
    }
}
