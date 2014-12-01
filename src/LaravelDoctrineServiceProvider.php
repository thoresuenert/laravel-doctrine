<?php namespace Mitch\LaravelDoctrine;

use App;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Gedmo;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Common\EventManager;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\ServiceProvider;
use Mitch\LaravelDoctrine\Cache;
use Mitch\LaravelDoctrine\Configuration\DriverMapper;
use Mitch\LaravelDoctrine\Configuration\SqlMapper;
use Mitch\LaravelDoctrine\Configuration\SqliteMapper;
use Mitch\LaravelDoctrine\EventListeners\SoftDeletableListener;
use Mitch\LaravelDoctrine\Filters\TrashedFilter;
use ReflectionClass;

class LaravelDoctrineServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        $this->package('mitchellvanw/laravel-doctrine', 'doctrine', __DIR__ . '/..');
        $this->extendAuthManager();
    }

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->registerConfigurationMapper();
        $this->registerCacheManager();
        $this->registerEntityManager();
        $this->registerClassMetadataFactory();

        $this->commands([
            'Mitch\LaravelDoctrine\Console\GenerateProxiesCommand',
            'Mitch\LaravelDoctrine\Console\SchemaCreateCommand',
            'Mitch\LaravelDoctrine\Console\SchemaUpdateCommand',
            'Mitch\LaravelDoctrine\Console\SchemaDropCommand'
        ]);
    }

    /**
     * The driver mapper's instance needs to be accessible from anywhere in the application,
     * for registering new mapping configurations or other storage libraries.
     */
    private function registerConfigurationMapper()
    {
        $this->app->bind(DriverMapper::class, function () {
            $mapper = new DriverMapper;
            $mapper->registerMapper(new SqlMapper);
            $mapper->registerMapper(new SqliteMapper);
            return $mapper;
        });
    }

    public function registerCacheManager()
    {
        $this->app->bind(CacheManager::class, function ($app) {
            $manager = new CacheManager($app['config']['doctrine::doctrine.cache']);
            $manager->add(new Cache\ApcProvider);
            $manager->add(new Cache\MemcacheProvider);
            $manager->add(new Cache\RedisProvider);
            $manager->add(new Cache\XcacheProvider);
            $manager->add(new Cache\NullProvider);
            return $manager;
        });
    }

    private function registerEntityManager()
    {
        $this->app->singleton(EntityManager::class, function ($app) {
            $config = $app['config']['doctrine::doctrine'];
            // workbench: __DIR__
            $basePath = __DIR__.'/..'; //$app['path.base'];
            $this->autoLoadFiles($basePath, $config['doctrine_extension']);

            $metadata = Setup::createConfiguration(
                $app['config']['app.debug'],
                $config['proxy']['directory'],
                $app[CacheManager::class]->getCache($config['cache_provider'])
            );

            $metadata->addFilter('trashed', TrashedFilter::class);
            $metadata->setAutoGenerateProxyClasses($config['proxy']['auto_generate']);
            $metadata->setDefaultRepositoryClassName($config['repository']);
            $metadata->setSQLLogger($config['logger']);

            if (isset($config['proxy']['namespace']))
                $metadata->setProxyNamespace($config['proxy']['namespace']);


            // Second configure ORM
            // globally used cache driver, in production use APC or memcached
            $cache = $metadata->getMetadataCacheImpl();
            // standard annotation reader
            $cachedAnnotationReader = $this->buildAnnotaionReader($cache);
            // create a driver chain for metadata reading


            $metadata->setMetadataDriverImpl($this->buildDriverChain($cachedAnnotationReader, $config['metadata'], 'Portal'));

            // EventManager
            $eventManager = new EventManager;
            $eventManager->addEventListener(Events::onFlush, new SoftDeletableListener);

            // load all listeners from config
            $this->loadEventListeners($config['listeners'],$cachedAnnotationReader, $eventManager);

            // EntityManager
            $entityManager = EntityManager::create($this->mapLaravelToDoctrineConfig($app['config']), $metadata, $eventManager);
            $entityManager->getFilters()->enable('trashed');
            return $entityManager;
        });
        $this->app->singleton(EntityManagerInterface::class, EntityManager::class);
    }


    private function registerClassMetadataFactory()
    {
        $this->app->singleton(ClassMetadataFactory::class, function ($app) {
            return $app[EntityManager::class]->getMetadataFactory();
        });
    }


    private function extendAuthManager()
    {
        $this->app[AuthManager::class]->extend('doctrine', function ($app) {
            return new DoctrineUserProvider(
                $app['Illuminate\Contracts\Hashing\Hasher'],
                $app[EntityManager::class],
                $app['config']['auth.model']
            );
        });
    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [
            CacheManager::class,
            EntityManagerInterface::class,
            EntityManager::class,
            ClassMetadataFactory::class,
            DriverMapper::class,
            AuthManager::class,
        ];
    }

    /**
     * Map Laravel's to Doctrine's database configuration requirements.
     * @param $config
     * @throws \Exception
     * @return array
     */
    private function mapLaravelToDoctrineConfig($config)
    {
        $default = $config['database.default'];
        $connection = $config["database.connections.{$default}"];
        return App::make(DriverMapper::class)->map($connection);
    }

    /**
     * Load annotaions and lib for doctrine and extension if needed
     * @param $basePath
     * @param bool $loadExtension
     */
    private function autoLoadFiles($basePath,$loadExtension = false)
    {
        if($loadExtension)
        {
            $namespace = 'Gedmo\Mapping\Annotation';
            $lib = 'vendor/gedmo/doctrine-extensions/lib';
            AnnotationRegistry::registerAutoloadNamespace($namespace, $lib);

            Gedmo\DoctrineExtensions::registerAnnotations();
        }

        AnnotationRegistry::registerFile($basePath."/vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php");
    }

    /**
     * @param Cache $cache
     * @return CachedReader
     */
    private function buildAnnotaionReader(DoctrineCache $cache)
    {
        $annotationReader = new AnnotationReader();
        return new CachedReader(
            $annotationReader, // use reader
            $cache // and a cache driver
        );
    }

    /**
     * @param CachedReader $cachedAnnotationReader
     * @param array $path
     * @param $namespace
     * @return MappingDriverChain
     */
    private function buildDriverChain(CachedReader $cachedAnnotationReader, Array $path, $namespace)
    {
        $driverChain = new MappingDriverChain();
        // load superclass metadata mapping only, into driver chain
        // also registers Gedmo annotations.NOTE: you can personalize it
        Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
            $driverChain, // our metadata driver chain, to hook into
            $cachedAnnotationReader // our cached annotation reader
        );
        // now we want to register our application entities,
        // for that we need another metadata driver used for Entity namespace
        $annotationDriver = new AnnotationDriver(
            $cachedAnnotationReader, // our cached annotation reader
            (array) $path// paths to look in
        );
        // NOTE: driver for application Entity can be different, Yaml, Xml or whatever
        // register annotation driver for our application Entity namespace
        $driverChain->addDriver($annotationDriver, $namespace);

        return $driverChain;
    }

    /**
     * @param array $listeners
     * @param CachedReader $cachedAnnotationReader
     * @param EventManager $eventManager
     */
    private function loadEventListeners(Array $listeners, CachedReader $cachedAnnotationReader, EventManager $eventManager)
    {
        foreach($listeners as $listener)
        {
            $eventListener = new $listener();
            $eventListener->setAnnotationReader($cachedAnnotationReader);
            $eventManager->addEventSubscriber($eventListener);

        }

    }
}
