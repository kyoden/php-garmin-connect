<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace GarminConnect\ParametersBuilder;

class ActivityFilter extends ParametersBuilder
{
    public function start(int $start)
    {
        if ($start < 0) {
            throw new \InvalidArgumentExcpetion('ActivityFilter start must greater than or equal zero');
        }
        $this->set('start', self::EQUAL, $start);
        return $this;
    }

    public function limit(int $limit)
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentExcpetion('ActivityFilter limit must greater than zero');
        }
        $this->set('limit', self::EQUAL, $limit);
        return $this;
    }

    public function type(string $type)
    {
        $this->set('activityType', self::EQUAL, $type);
        return $this;
    }

    public function eventType(string $type)
    {
        $this->set('eventType', self::EQUAL, $type);
        return $this;
    }

    public function startDate(\Datetime $date)
    {
        $this->set('startDate', self::EQUAL, $date->format('Y-m-d'));
    }

    public function endDate(\Datetime $date)
    {
        $this->set('endDate', self::EQUAL, $date->format('Y-m-d'));
    }

    public function betweenDate(\DateTime $from, \DateTime $to)
    {
        $this->startDate($from);
        $this->endDate($to);
        return $this;
    }

    public function maxDistance(int $metersDistance)
    {
        if ($metersDistance <= 0) {
            throw new \InvalidArgumentExcpetion('ActivityFilter maxDistance must greater than zero');
        }
        $this->set('maxDistance', self::EQUAL, $metersDistance);
    }

    public function minDistance(int $metersDistance)
    {
        if ($metersDistance <= 0) {
            throw new \InvalidArgumentExcpetion('ActivityFilter minDistance must greater than zero');
        }
        $this->set('minDistance', self::EQUAL, $metersDistance);
    }

    public function betweenDistance(int $minMeters, int $maxMeters)
    {
        $this->minDistance($minMeters);
        $this->maxDistance($maxMeters);
        return $this;
    }
}