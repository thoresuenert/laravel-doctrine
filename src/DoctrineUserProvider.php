<?php namespace Mitch\LaravelDoctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Auth\UserProviderInterface;
use ReflectionClass;

class DoctrineUserProvider implements UserProviderInterface
{
    /**
     * @var HasherContract
     */
    private $hasher;
    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var string
     */
    private $entity;

    /**
     * @param HasherContract $hasher
     * @param EntityManager $entityManager
     * @param $entity
     */
    public function __construct(HasherContract $hasher, EntityManager $entityManager, $entity)
    {
        $this->hasher = $hasher;
        $this->entityManager = $entityManager;
        $this->entity = $entity;
    }
    /**
     * Retrieve a user by their unique identifier.

     * @param  mixed $identifier
     * @return UserInterface|null
     */
    public function retrieveById($identifier)
    {
        return $this->getRepository()->find($identifier);
    }

    /**
     * Retrieve a user by by their unique identifier and "remember me" token.

     * @param  mixed $identifier
     * @param  string $token
     * @return UserInterface|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $entity = $this->getEntity();
        return $this->getRepository()->findOneBy([
            $entity->getKeyName() => $identifier,
            $entity->getRememberTokenName() => $token
        ]);
    }

    /**
     * Update the "remember me" token for the given user in storage.

     * @param  UserInterface $user
     * @param  string $token
     * @return void
     */
    public function updateRememberToken(UserContract $user, $token)
    {
        $user->setRememberToken($token);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Retrieve a user by the given credentials.

     * @param  array $credentials
     * @return UserInterface|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $criteria = [];
        foreach ($credentials as $key => $value)
            if ( ! str_contains($key, 'password'))
                $criteria[$key] = $value;

        return $this->getRepository()->findOneBy($criteria);
    }

    /**
     * Validate a user against the given credentials.

     * @param  UserInterface $user
     * @param  array $credentials
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        return $this->hasher->check($credentials['password'], $user->getAuthPassword());
    }

    /**
     * Returns repository for the entity.
     *
     * @return EntityRepository
     */
    private function getRepository()
    {
        return $this->entityManager->getRepository($this->entity);
    }

    /**
     * Returns instantiated entity.
     *
     * @return mixed
     */
    private function getEntity()
    {
        $refEntity = new ReflectionClass($this->entity);
        return $refEntity->newInstanceWithoutConstructor();
    }
}
