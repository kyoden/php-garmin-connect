<?php
require_once __DIR__ .'/../vendor/autoload.php';

$arrCredentials = array(
   'username' => 'my_email@mail.com',
   'password' => 'my_secret_password'
);

try {
   $objGarminConnect = new \GarminConnect\GarminConnect($arrCredentials);

   $fitlers = new \GarminConnect\ParametersBuilder\ActivityFilter();
   $fitlers->betweenDate(new \DateTime('2018-05-01'), new \DateTime('2018-05-31'));
   $fitlers->type(\GarminConnect\ActivityType::RUNNING);
   $fitlers->limit(1);
   
   $objResults = $objGarminConnect->getActivityList($fitlers);
   foreach ($objResults as $activity) {
       print "\n - {$activity->activityName} at {$activity->startTimeLocal} (type: {$activity->activityType->typeKey})";
   }

} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage() ;
}
