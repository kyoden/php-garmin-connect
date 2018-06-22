<?php
require_once __DIR__ .'/../vendor/autoload.php';

$arrCredentials = array(
   'username' => 'xxx',
   'password' => 'xxx'
);

try {
   $objGarminConnect = new \kyoden\GarminConnect($arrCredentials);

   $fitlers = new \kyoden\GarminConnect\ParametersBuilder\ActivityFilter();
   $fitlers->betweenDate(new \DateTime('2018-05-01'), new \DateTime('2018-05-31'));
   $fitlers->type(\kyoden\GarminConnect\ActivityType::RUNNING);
   $fitlers->limit(15);
   
   $objResults = $objGarminConnect->getActivityList($fitlers);
   foreach ($objResults as $activity) {
       print "\n - {$activity->activityName} at {$activity->startTimeLocal} (type: {$activity->activityType->typeKey})";
   }

} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage() ;
}
