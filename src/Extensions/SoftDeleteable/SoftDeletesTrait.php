<?php namespace Mitch\LaravelDoctrine\Extensions\SoftDeleteable;

use Doctrine\ORM\Mapping AS ORM;
use DateTime;

trait SoftDeletesTrait
{
    /**
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true)
     * @var \DateTime
     */
    private $deletedAt;

    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(DateTime $deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    public function isDeleted()
    {
        if( is_null($this->deletedAt) )
            return false;

        return new DateTime > $this->deletedAt;
    }
}
