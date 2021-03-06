<?php namespace Mitch\LaravelDoctrine\Extensions\Sortable\Mapping\Annotation;
use Doctrine\ORM\Mapping\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class Sortable implements Annotation{

    /**
     * @var string
     */
    public $index;

    /**
     * @var string
     */
    public $group;

    /**
     * @return mixed
     */
    public function getIndex()
    {
        return 'get'.ucfirst($this->index);
    }
    /**
     * @return mixed
     */
    public function setIndex()
    {
        return 'set'.ucfirst($this->index);
    }

    /**
     * @return boolean
     */
    public function getGroup()
    {
        return 'get'.ucfirst($this->group);
    }

    public function hasGroup()
    {
        return (bool) $this->group;
    }

    public function getIndexFieldName()
    {
        return $this->index;
    }

    public function getGroupFieldName()
    {
        return $this->group;
    }
}