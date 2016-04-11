<?php

namespace SpyimmoBundle\Manager;

use SpyimmoBundle\Entity\Search;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SearchManager
{
    private $doctrine;
    private $tokenStorage;

    public function __construct(RegistryInterface $doctrine, TokenStorageInterface $tokenStorage)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
    }

    public function getSearch()
    {
        $profile = $this->tokenStorage->getToken()->getUser()->getProfile();

        $search = $this->doctrine->getRepository('SpyimmoBundle:Search')->findOneByProfile($profile);

        if (null === $search) {
            $search = new Search();
        }

        return $search;
    }

    public function save(Search $search)
    {
        $profile = $this->tokenStorage->getToken()->getUser()->getProfile();
        $search->setProfile($profile);

        $em = $this->doctrine->getManager();
        $em->persist($search);
        $em->flush();
    }

    public function findAll()
    {
        return $this->doctrine->getRepository('SpyimmoBundle:Search')->findAll();
    }
}
