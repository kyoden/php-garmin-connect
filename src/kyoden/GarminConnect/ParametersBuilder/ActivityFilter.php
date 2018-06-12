<?php
/**
 * @author    Gwenael HELLEUX
 * @copyright Gwenael HELLEUX &copy; 2018
 * @copyright 2018
 */

namespace kyoden\GarminConnect\ParametersBuilder;

class ActivityFilter extends ParametersBuilder
{
    private $betweenFrom;
    private $betweenTo;

    public function start($start)
    {
        $this->set('start', self::EQUAL, (int)$start);
        return $this;
    }

    public function limit($limit)
    {
        $this->set('limit', self::EQUAL, (int)$limit);
        return $this;
    }

    public function type($type)
    {
        $this->set('activityType', self::EQUAL, $type);
        return $this;
    }

    public function eventType($type)
    {
        $this->set('eventType', self::EQUAL, $type);
        return $this;
    }

    public function beginTimestamp($operator, \Datetime $date)
    {
        $this->set('beginTimestamp', $operator, $date->format('Y-m-d\TH:i:s,000\Z'));
        return $this;
    }

    public function between(\DateTime $from, \DateTime $to)
    {
        $this->betweenFrom = $from;
        $this->betweenTo = $to;
        return $this;
    }

    public function build()
    {
        $build = parent::build();
        if ($this->betweenFrom) {
            $build .= '&beginTimestamp>=' . $this->betweenFrom->format('Y-m-d\TH:i:s,000\Z');
            $build .= '&endTimestamp<=' . $this->betweenTo->format('Y-m-d\TH:i:s,000\Z');
        }
        return ltrim($build, '&');
    }
}