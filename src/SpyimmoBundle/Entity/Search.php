<?php

namespace SpyimmoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class Search
{
    const MIN_BUDGET = 'minBudget';
    const MAX_BUDGET = 'maxBudget';
    const MIN_SURFACE = 'minSurface';
    const MAX_SURFACE = 'maxSurface';
    const MIN_BEDROOM = 'minBedroom';
    const MIN_ROOM = 'minRoom';
    const MAX_ROOM = 'maxRoom';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\Column(type="array")
     */
    private $criterias;

    /**
     * @ORM\ManyToOne(targetEntity="SpyimmoBundle\Entity\Profile")
     */
    private $profile;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->criterias = [
            self::MIN_BUDGET => null,
            self::MAX_BUDGET => null,
            self::MIN_SURFACE => null,
            self::MAX_SURFACE => null,
            self::MIN_BEDROOM => null,
            self::MIN_ROOM => null,
            self::MAX_ROOM => null,
        ];

        $this->createdAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCriterias()
    {
        $criterias = [];

        foreach ($this->criterias as $key => $criteria) {
            if (null === $criteria) {
                continue;
            }

            $criterias[$key] = $criteria;
        }

        return $criterias;
    }

    public function getCriteria($key)
    {
        return $this->criterias[$key];
    }

    public function getMinBudget()
    {
        return $this->criterias[self::MIN_BUDGET];
    }

    public function setMinBudget($minBudget)
    {
        $this->criterias[self::MIN_BUDGET] = $minBudget;

        return $this;
    }

    public function getMaxBudget()
    {
        return $this->criterias[self::MAX_BUDGET];
    }

    public function setMaxBudget($maxBudget)
    {
        $this->criterias[self::MAX_BUDGET] = $maxBudget;

        return $this;
    }

    public function getMinSurface()
    {
        return $this->criterias[self::MIN_SURFACE];
    }

    public function setMinSurface($minSurface)
    {
        $this->criterias[self::MIN_SURFACE] = $minSurface;

        return $this;
    }

    public function getMaxSurface()
    {
        return $this->criterias[self::MAX_SURFACE];
    }

    public function setMaxSurface($maxSurface)
    {
        $this->criterias[self::MAX_SURFACE] = $maxSurface;

        return $this;
    }

    public function getMinBedroom()
    {
        return $this->criterias[self::MIN_BEDROOM];
    }

    public function setMinBedroom($minBedroom)
    {
        $this->criterias[self::MIN_BEDROOM] = $minBedroom;

        return $this;
    }

    public function getMinRoom()
    {
        return $this->criterias[self::MIN_ROOM];
    }

    public function setMinRoom($minRoom)
    {
        $this->criterias[self::MIN_ROOM] = $minRoom;

        return $this;
    }

    public function getMaxRoom()
    {
        return $this->criterias[SELF::MAX_ROOM];
    }

    public function setMaxRoom($maxRoom)
    {
        $this->criterias[self::MAX_ROOM] = $maxRoom;

        return $this;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile)
    {
        $this->profile = $profile;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
