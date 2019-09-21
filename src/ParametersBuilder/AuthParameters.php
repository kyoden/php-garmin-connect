<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace GarminConnect\ParametersBuilder;

class AuthParameters extends ParametersBuilder
{
    public function __construct()
    {
        $this->set('_eventId', self::EQUAL, 'submit');
        $this->set('embed', self::EQUAL, 'true');
        $this->set('displayNameRequired', self::EQUAL, 'false');
    }

    /**
     * @param string $username
     *
     * @return AuthParameters
     *
     * @throws \InvalidArgumentException
     */
    public function username(string $username): AuthParameters
    {
        return $this->set('username', self::EQUAL, $username);
    }

    /**
     * @param string $password
     *
     * @return AuthParameters
     *
     * @throws \InvalidArgumentException
     */
    public function password(string $password): AuthParameters
    {
        return $this->set('password', self::EQUAL, $password);
    }

    /**
     * @param string $csrf
     *
     * @return AuthParameters
     *
     * @throws \InvalidArgumentException
     */
    public function csrf(string $csrf): AuthParameters
    {
        return $this->set('_csrf', self::EQUAL, $csrf);
    }
}
