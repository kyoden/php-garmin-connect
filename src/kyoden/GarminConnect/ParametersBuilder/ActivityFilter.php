<?php
/**
 * @author    Gwenael HELLEUX <gwenael [.] helleux [@] yahoo [.] fr>
 * @date      01/08/17 08:04
 * @copyright 2017
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