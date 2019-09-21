<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace GarminConnect\ParametersBuilder;

class ParametersBuilder
{
    public const EQUAL = '=';
    public const GREATER_THAN = '%3E';
    public const GREATER_THAN_OR_EQUAL = '%3E=';
    public const LESS_THAN = '%3C';
    public const LESS_THAN_OR_EQUAL = '%3C=';

    private $parameters = [];

    /**
     * @param string           $field
     * @param string           $operator
     * @param string|float|int $value
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function set(string $field, string $operator, $value): ParametersBuilder
    {
        switch ($operator) {
            case self::EQUAL:
            case self::GREATER_THAN:
            case self::GREATER_THAN_OR_EQUAL:
            case self::LESS_THAN:
            case self::LESS_THAN_OR_EQUAL:
                break;

            default:
                throw new \InvalidArgumentException('Unsupported operator');
        }

        if (!is_string($value) && !is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('$value must be an string or numeric, "%s" given', gettype($value)));
        }

        $this->parameters[$field] = [$operator, $value];

        return $this;
    }

    /**
     * @return string
     */
    public function build(): string
    {
        $build = '';
        foreach ($this->parameters as $name => $specs) {
            $build .= '&' . $name . $specs[0] . urlencode($specs[1]);
        }

        return ltrim($build, '&');
    }
}
