<?php namespace Mitch\LaravelDoctrine\Extensions;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;


class ExtendedMappingDriverChain implements  MappingDriver{
    /**
     * The default driver.
     *
     * @var MappingDriver|null
     */
    private $defaultDriver = null;

    /**
     * @var array
     */
    private $drivers = array();

    /**
     * Gets the default driver.
     *
     * @return MappingDriver|null
     */
    public function getDefaultDriver()
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver.
     *
     * @param MappingDriver $driver
     *
     * @return void
     */
    public function setDefaultDriver(MappingDriver $driver)
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Adds a nested driver.
     *
     * @param MappingDriver $nestedDriver
     * @param string        $namespace
     *
     * @return void
     */
    public function addDriver(MappingDriver $nestedDriver)
    {
        $this->drivers[] = $nestedDriver;
    }

    /**
     * Gets the array of nested drivers.
     *
     * @return array $drivers
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        if (null !== $this->defaultDriver) {
            $this->defaultDriver->loadMetadataForClass($className, $metadata);
        }
        /* @var $driver MappingDriver */
        foreach ($this->drivers as $driver) {
            $driver->loadMetadataForClass($className, $metadata);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames()
    {
        return $this->defaultDriver->getAllClassNames();
    }

    /**
     * {@inheritDoc}
     */
    public function isTransient($className)
    {
        if ($this->defaultDriver !== null) {
            return $this->defaultDriver->isTransient($className);
        }

        return true;
    }

}