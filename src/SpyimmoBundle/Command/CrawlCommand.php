<?php

namespace SpyimmoBundle\Command;

use SpyimmoBundle\Entity\Search;
use SpyimmoBundle\Notifier\PushbulletNotifier;
use SpyimmoBundle\Services\CrawlerService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrawlCommand extends ContainerAwareCommand
{

    /**
     * @var CrawlerService
     */
    protected $crawlerService;

    /**
     * @var PushbulletNotifier
     */
    protected $notifier;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
          ->setName('spyimmo:crawl')
          ->setDescription('Command to crawl offers')
          ->addOption(
            'crawler',
            null,
            InputOption::VALUE_OPTIONAL,
            'If set, the task will force specific crawler'
          );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $forcedCrawler = $input->getOption('crawler');
        $io = new SymfonyStyle($input, $output);
        $this->getContainer()->get('test.logger.service')->setLogger($io);
        $io->title('Spyimmo Crawler');

        // if(!$forcedCrawler) {
        //     // Just avoid crawling at fixed time
        //     $waitingTime = rand(0, 600);
        //     $io->text(sprintf('Waiting %d seconds before crawling ...', $waitingTime));
        //     sleep($waitingTime);
        // }

        $searchs = $this->getContainer()->get('search.manager')->findAll();

        foreach ($searchs as $search) {
            $this->doExecute($search, $io, $forcedCrawler);
        }
    }

    private function doExecute(Search $search, SymfonyStyle $io, $forcedCrawler)
    {
        $cptNew = $this->crawlerService->crawl($search, $forcedCrawler);

        if ($cptNew > 0) {
            $url = $this->getContainer()->getParameter('website');
            $this->getContainer()->get('app.pushbullet.notifier')->notify(sprintf('Spyimmo: %d new offer found', $cptNew), $url, "Go to see it!");
            $io->success(sprintf('[%s] %d new offer found', date('d/m/Y H:i'), $cptNew));
        } else {
            $io->note(sprintf('[%s] No new offer found', date('d/m/Y H:i')));
        }

    }
    /**
     * @return mixed
     */
    public function getCrawlerService()
    {
        return $this->crawlerService;
    }

    /**
     * @param mixed $crawlerService
     */
    public function setCrawlerService($crawlerService)
    {
        $this->crawlerService = $crawlerService;
    }


}
