<?php
/**
 * @author    Gwenael HELLEUX <gwenael [.] helleux [@] yahoo [.] fr>
 * @date      01/08/17 08:04
 * @copyright 2017
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