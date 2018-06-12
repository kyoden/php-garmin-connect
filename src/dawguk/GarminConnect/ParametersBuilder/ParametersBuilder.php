<?php

namespace dawguk\GarminConnect\ParametersBuilder;

/**
 * @author    Gwenael HELLEUX <gwenael [.] helleux [@] yahoo [.] fr>
 * @date      01/08/17 08:04
 * @copyright 2017
 */
class ParametersBuilder
{
    const EQUAL = '=';
    const GREATER_THAN = '>';
    const GREATER_THAN_OR_EQUAL = '>=';
    const LESS_THAN = '<';
    const LESS_THAN_OR_EQUAL = '<=';

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