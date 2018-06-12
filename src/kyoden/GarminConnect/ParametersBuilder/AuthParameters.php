<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace kyoden\GarminConnect\ParametersBuilder;

class AuthParameters extends ParametersBuilder
{
    public function __construct()
    {
        $this->set('_eventId', self::EQUAL, 'submit');
        $this->set('embed', self::EQUAL, 'true');
        $this->set('displayNameRequired', self::EQUAL, 'false');
    }

    public function username($username)
    {
        return $this->set('username', self::EQUAL, $username);
    }

    public function password($password)
    {
        return $this->set('password', self::EQUAL, $password);
    }
}