<?php

return [
    'simple_annotations' => false,
    'metadata' => [
//         base_path('app/models')
    ],

    'proxy' => [
        'auto_generate' => false,
        'directory'     => null,
        'namespace'     => null
    ],

    // Available: null, apc, xcache, redis, memcache
    'cache_provider' => null,

    'cache' => [
        'redis' => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'database' => 1
        ],
        'memcache' => [
            'host' => '127.0.0.1',
            'port' => 11211
        ]
    ],

    'repository' => 'Doctrine\ORM\EntityRepository',

    'repositoryFactory' => null,

    'logger' => null,

    // doctrine extensions
    // Available: false, true
    'doctrine_extension' => true,

    // Available: Translatable, Loggable, Tree, Sluggable, Timestampable, Blameable,
    //            Sortable, Translator, Softdeleteable, Uploadable, References, IpTraceable
    'listeners' => ['Gedmo\Sortable\SortableListener']
];
