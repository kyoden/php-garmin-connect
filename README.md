PHP Garmin Connect
==================

This is a fork from dawguk code mainly for updating to latest Garmin Connect features
A PHP adapter for interrogating the Garmin Connect "API"

Fork : dawguk/php-garmin-connect and merged with klorie/php-garmin-connect


Preamble
========

Garmin doesn't really have a proper API for their Connect tool. Well, they sort of do, but it's half-baked; they appear to
have either abandoned it or let it go stale, the documentation is very thin on the ground and there appears to be
no "proper" way of authenticating the user.

So thanks to Collin @tapiriik and his wonderful public python repo (https://github.com/cpfair/tapiriik), this project
was born for those of us that prefer elephants to snakes ;)

The code is pretty well documented, and it has to be because some things we have to do is pretty gnarly. Once authentication
is done though, it's pretty much good old RESTFUL API stuff. Oh, but we're using the CURL cookie handling to maintain
session state. Ugh.

Full Example
============

We simply connect using our Garmin Connect credentials.

```php
<?php
$arrCredentials = array(
   'username' => 'xxx',
   'password' => 'xxx',
);

try {
  $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);

  $filters = new \GarminConnect\ParametersBuilder\ActivityFilter();
  $filters->betweenDate(new \DateTime('2018-05-01'), new \DateTime('2018-05-31'));
  $filters->type(\GarminConnect\ActivityType::RUNNING);
  $filters->limit(1);

  $results = $GarminConnect->getActivityList($filters);
  foreach ($results as $activity) {print " - {$activity->activityName} at {$activity->startTimeLocal} (type: {$activity->activityType->typeKey})" . PHP_EOL;
   }

} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage();
}
```

API Functions
=============

The library implements a few basic API functions that you can use to retrieve useful information. The method signatures are as follows:

| Method                  | Parameters           | Returns                     |
| ----------------------- | -------------------- | --------------------------- |
| getActivityTypes()      | -                 | stdClass                    |
| getActivityList()       | integer $intStart, integer $intLimit | stdClass    |
| getActivitySummary()    | integer $intActivityID | stdClass                  |
| getActivityDetails()    | integer $intActivityID | stdClass |
| getDataFile             | string $strType, integer $intActivityID | string |
| getUsername             | - | string |

### getActivityTypes()

Returns a stdClass object, which contains an array called dictionary, that contains stdClass objects that represent an activity type.

#### Example

```php
try {
   $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);
   $obj_results = $GarminConnect->getActivityTypes();
   foreach ($obj_results->dictionary as $item) {
      print_r($item);
   }

   } catch (Exception $objException) {
      echo "Oops: " . $objException->getMessage();
   }
```

#### Response

    stdClass Object
    (
    [key] => road_biking
    [display] => Road Cycling
    [parent] => stdClass Object
        (
            [key] => cycling
            [display] => Cycling
        )

    [type] => stdClass Object
        (
            [key] => cycling
            [display] => Cycling
        )

    )
 
### getActivityList(ActivityFilter $filters)

Returns a stdClass object, which contains an array called results, that contains stdClass objects that represents an activity. It accepts the parameter ActivityFilter, an object for facility the filter of activities.

#### Example

```php
   try {
      $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);
      $fitlers = new \GarminConnect\GarminConnect\ParametersBuilder\ActivityFilter();
      $fitlers->betweenDate(new \DateTime('2018-06-01'), new \DateTime('2018-06-05'));
      $fitlers->type(\GarminConnect\GarminConnect\ActivityType::RUNNING);
      
      $obj_results = $GarminConnect->getActivityList($fitlers);
      print_r($obj_results);
   } catch (Exception $objException) {
      echo "Oops: " . $objException->getMessage();
   }
```


### getActivitySummary(integer $intActvityID)

Returns a stdClass object, that contains a stdClass object called activity, which contains a, and I quote, BUTT LOAD of data representative of the activity ID that you have passed in as the parameter. This activity ID can be taken from the getActivityList() response (e.g. $objResponse->results->activities[0]->activity->activityId).

#### Example

```php
try {
   $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);
   $obj_results = $GarminConnect->getActivitySummary(593520370);
   print_r($obj_results);
} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage();
}
```

#### Response

I'm afraid that response is far too large to put here - you'll just have to execute the above and check it out for yourself!

### getActivityDetails(integer $intActivityID)

If you think the previous function returned a lot of data, you had better sit down - this is a big one!

As usual, this returns a stdClass object that contains a stdClass object called "com.garmin.activity.details.json.ActivityDetails" (yep!) that contains a bunch of members and objects that represents the RAW data of your activity. As such, all of the raw exercise data is also returned (metrics). You might consider this a textual representation of GPX data, for example.

It contains an array (measurements) of stdClass objects, which are the indexes for the metric data. For example, this information shows you that metric index 3 represents the data for Temperature, and index 1 is Bike Cadence, etc.

The metric data is found in the metric array, which is a bunch of stdClass objects that contain arrays called metrics, which indexes can be found in the measurements data.

It makes sense when you see it.

Note: This method may take a while to return any data, as it can be vast.

#### Example

```php
try {
   $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);
   $obj_results = $GarminConnect->getActivityDetails(593520370);
   print_r($obj_results);
} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage();
}
```

#### Response

No chance!

### getDataFile(string $strType, integer $intActivityID)

Returns a string representation of requested data type, for the given activity ID. The first parameter is one of:

|Type | Returns |
|---- | ------- |
|\GarminConnect\GarminConnect::DATA_TYPE_CSV | CSV string |
|\GarminConnect\GarminConnect::DATA_TYPE_GPX | GPX as XML string |
|\GarminConnect\GarminConnect::DATA_TYPE_TCX | TCX as XML string |
|\GarminConnect\GarminConnect::DATA_TYPE_GOOGLE_EARTH | Google Earth as XML string |

#### Example

```php
   try {
      $GarminConnect = new \GarminConnect\GarminConnect($arrCredentials);
      $obj_results = $GarminConnect->getDataFile(\GarminConnect\GarminConnect::DATA_TYPE_GPX, 593520370);
      print_r($obj_results);
   } catch (Exception $objException) {
      echo "Oops: " . $objException->getMessage();
   }

```
#### Response (Not exhaustive)

    <?xml version="1.0" encoding="UTF-8"?>
    <gpx version="1.1" creator="Garmin Connect" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd http://www.garmin.com/xmlschemas/GpxExtensions/v3 http://www.garmin.com/xmlschemas/GpxExtensionsv3.xsd http://www.garmin.com/xmlschemas/TrackPointExtension/v1 http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1" xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
       <metadata>
          <link href="connect.garmin.com">
             <text>Garmin Connect</text>
          </link>
          <time>2014-09-18T17:22:50.000Z</time>
       </metadata>
       <trk>
          <name>Untitled</name>
          <trkseg>
             <trkpt lon="-2.246061209589243" lat="53.48290401510894">
                <ele>54.599998474121094</ele>
                <time>2014-09-18T17:22:50.000Z</time>
                <extensions>
                   <gpxtpx:TrackPointExtension>
                      <gpxtpx:atemp>22.0</gpxtpx:atemp>
                      <gpxtpx:cad>0</gpxtpx:cad>
                   </gpxtpx:TrackPointExtension>
                </extensions>

