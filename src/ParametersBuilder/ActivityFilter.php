<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace GarminConnect\ParametersBuilder;

class ActivityFilter extends ParametersBuilder
{
    /**
     * @param int $start
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function start(int $start): ActivityFilter
    {
        if ($start < 0) {
            throw new \InvalidArgumentException('ActivityFilter start must greater than or equal zero');
        }

        return $this->set('start', self::EQUAL, $start);
    }

    /**
     * @param int $limit
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function limit(int $limit): ActivityFilter
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('ActivityFilter limit must greater than zero');
        }

        return $this->set('limit', self::EQUAL, $limit);
    }

    /**
     * @param string $type
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function type(string $type): ActivityFilter
    {
        return $this->set('activityType', self::EQUAL, $type);
    }

    /**
     * @param string $type
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function eventType(string $type): ActivityFilter
    {
        return $this->set('eventType', self::EQUAL, $type);
    }

    /**
     * @param \Datetime $date
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function startDate(\Datetime $date): ActivityFilter
    {
        return $this->set('startDate', self::EQUAL, $date->format('Y-m-d'));
    }

    /**
     * @param \Datetime $date
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function endDate(\Datetime $date): ActivityFilter
    {
        return $this->set('endDate', self::EQUAL, $date->format('Y-m-d'));
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function betweenDate(\DateTime $from, \DateTime $to): ActivityFilter
    {
        $this->startDate($from);
        $this->endDate($to);

        return $this;
    }

    /**
     * @param int $metersDistance
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function maxDistance(int $metersDistance): ActivityFilter
    {
        if ($metersDistance <= 0) {
            throw new \InvalidArgumentException('ActivityFilter maxDistance must greater than zero');
        }

        return $this->set('maxDistance', self::EQUAL, $metersDistance);
    }

    /**
     * @param int $metersDistance
     *
     * @return ActivityFilter
     *
     * @throws \InvalidArgumentException
     */
    public function minDistance(int $metersDistance): ActivityFilter
    {
        if ($metersDistance <= 0) {
            throw new \InvalidArgumentException('ActivityFilter minDistance must greater than zero');
        }

        return $this->set('minDistance', self::EQUAL, $metersDistance);
    }

    /**
     * @param int $minMeters
     * @param int $maxMeters
     *
     * @return ActivityFilter
     */
    public function betweenDistance(int $minMeters, int $maxMeters): ActivityFilter
    {
        $this->minDistance($minMeters);
        $this->maxDistance($maxMeters);

        return $this;
    }
}
