<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace kyoden\GarminConnect\ParametersBuilder;

class ParametersBuilder
{
    const EQUAL = '=';
    const GREATER_THAN = '%3E';
    const GREATER_THAN_OR_EQUAL = '%3E=';
    const LESS_THAN = '%3C';
    const LESS_THAN_OR_EQUAL = '%3C=';

    private $parameters = [];

    /**
     * @param $field
     * @param $operator
     * @param $value
     * @return $this
     * @throws \Exception
     */
    public function set($field, $operator, $value)
    {
        switch ($operator) {
            case self::EQUAL:
            case self::GREATER_THAN:
            case self::GREATER_THAN_OR_EQUAL:
            case self::LESS_THAN:
            case self::LESS_THAN_OR_EQUAL:
                break;

            default:
                throw new \InvalidArgumentException("Unsupported operator");
        }

        $this->parameters[$field] = [$operator, $value];
        return $this;
    }

    /**
     * @return string
     */
    public function build()
    {
        $build = '';
        foreach ($this->parameters as $name => $specs) {
            $build .= '&' . $name . $specs[0] . urlencode($specs[1]);
        }
        return ltrim($build, '&');
    }
}