<?php
/**
 * Usage : php example.php "email@domain.com" "password"
 */

require_once __DIR__ . '/../vendor/autoload.php';

$arrCredentials = array(
    'username' => $argv[1],
    'password' => $argv[2],
);

try {
    $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);

    print "=> getUsername() : " . $GarminConnect->getUsername() . PHP_EOL;
    print "=> getActivityCount() : " . PHP_EOL;
    print_r($GarminConnect->getActivityCount());

    $filters = new \GarminConnect\ParametersBuilder\ActivityFilter();
    $filters->betweenDate(new \DateTime('2018-05-01'), new \DateTime('2018-05-31'));
    $filters->type(\GarminConnect\ActivityType::RUNNING);
    $filters->limit(1);

    print "=> getActivityList() :" . PHP_EOL;
    $results = $GarminConnect->getActivityList($filters);
    foreach ($results as $activity) {
        print " - {$activity->activityName} at {$activity->startTimeLocal} (type: {$activity->activityType->typeKey})" . PHP_EOL;
//        print_r($GarminConnect->getActivitySummary($activity->activityId));
//        print_r($GarminConnect->getActivityDetails($activity->activityId));
//        print_r($GarminConnect->getExtendedActivityDetails($activity->activityId));
//        print_r($GarminConnect->getActivityGear($activity->activityId));
    }


} catch (Exception $objException) {
    echo "Oops: " . $objException->getMessage();
}
