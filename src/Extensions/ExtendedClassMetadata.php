<?php namespace Mitch\LaravelDoctrine\Extensions;


use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class ExtendedClassMetadata
 * @package Mitch\LaravelDoctrine\Extensions
 */
class ExtendedClassMetadata extends ClassMetadata{

    /**
     * @var array
     */
    private $extensionData = [];

    /**
     * @param $key
     * @param $data
     */
    public function addExtensionData($key,$data)
    {

        $this->extensionData[$key] = $data;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getExtensionData($key)
    {
        return $this->extensionData[$key];
    }

}