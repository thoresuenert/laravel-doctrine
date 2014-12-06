<?php namespace Mitch\LaravelDoctrine\Traits;

use Doctrine\ORM\Mapping AS ORM;

trait RememberToken
{
    /**
     * @ORM\Column(name="rememberToken", type="string", nullable=true)
     */
    private $rememberToken;

    private $keyName = 'id';

    private $rememberTokenName = 'rememberToken';
    /**
     * Get the identifier name: default id
     * @return bool
     */
    public function getKeyName()
    {
        return $this->keyName;
    }
    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
        return $this->rememberToken;
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string $value
     * @return void
     */
    public function setRememberToken($value)
    {
        $this->rememberToken = $value;
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return $this->rememberTokenName;
    }

    /**
     * Get the e-mail address where password reminders are sent.
     *
     * @return string
     */
    public function getReminderEmail()
    {
        return $this->email;
    }
} 
