<?php namespace Mitch\LaravelDoctrine;

use App;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\EntityManagerInterface;
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
use Mitch\LaravelDoctrine\Extensions\ExtendedClassMetadataFactory;
use Mitch\LaravelDoctrine\Extensions\ExtendedMappingDriverChain;

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
            // workbench: __DIR__.'/..';
//            $basePath = __DIR__.'/..';
            $basePath = $app['path.base'];
            AnnotationRegistry::registerFile($basePath."/vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php");

            $metadata = Setup::createConfiguration(
                $app['config']['app.debug'],
                $config['proxy']['directory'],
                $app[CacheManager::class]->getCache($config['cache_provider'])
            );

            $metadata->setAutoGenerateProxyClasses($config['proxy']['auto_generate']);
            $metadata->setDefaultRepositoryClassName($config['repository']);
            $metadata->setSQLLogger($config['logger']);
            $metadata->setClassMetadataFactoryName(ExtendedClassMetadataFactory::class);

            if (isset($config['proxy']['namespace']))
                $metadata->setProxyNamespace($config['proxy']['namespace']);

            // globally used cache driver, in production use APC or memcached
            $cache = $metadata->getMetadataCacheImpl();
            // standard annotation reader
            $cachedAnnotationReader = $this->buildAnnotaionReader($cache);
            // create a driver chain for metadata reading
            $driverChain = new ExtendedMappingDriverChain();
            $defaultDriver = new AnnotationDriver(
                $cachedAnnotationReader, // our cached annotation reader
                (array) $config['metadata'] // paths to look in
            );

            $driverChain->setDefaultDriver($defaultDriver);
            // add driverChain
            $metadata->setMetadataDriverImpl($driverChain);

            // EventManager
            $eventManager = new EventManager;

            // EntityManager
            $entityManager = EntityManager::create($this->mapLaravelToDoctrineConfig($app['config']), $metadata, $eventManager);

            // load extensions
            foreach($config['extensions'] as $name)
            {
                $this->loadExtension($name, $metadata, $config, $driverChain, $cachedAnnotationReader, $entityManager, $eventManager);
            }

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
     * @param DoctrineCache $cache
     * @return CachedReader
     */
    private function buildAnnotaionReader(DoctrineCache $cache)
    {
        return new CachedReader(
            new AnnotationReader(), // use reader
            $cache // and a cache driver
        );
    }


    /**
     * Load an extension from config
     * @param $name
     * @param $metadata
     * @param $driverChain
     * @param $cachedAnnotationReader
     * @param $entityManager
     * @param $eventManager
     */
    private function loadExtension($name, $metadata, $config, $driverChain, $cachedAnnotationReader, $entityManager, $eventManager)
    {
        $extensionProvider = 'Mitch\LaravelDoctrine\Extensions\\'.$name.'\\'.$name.'Provider';
        call_user_func_array(array($extensionProvider, "loadExtension"), array($metadata, $config, $driverChain, $cachedAnnotationReader, $entityManager, $eventManager));
    }
}
