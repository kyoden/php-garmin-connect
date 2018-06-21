<?php
/**
 * GarminConnect.php
 *
 * LICENSE: THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author Cyril Laury & Dave Wilcock & Gwenael Helleux
 * @copyright David Wilcock &copy; 2014 Cyril Laury &copy; 2018 Gwenal Helleux &copy; 2018  
 * @package
 */

namespace kyoden;

use kyoden\GarminConnect\Connector;
use kyoden\GarminConnect\ParametersBuilder\ActivityFilter;
use kyoden\GarminConnect\exceptions\AuthenticationException;
use kyoden\GarminConnect\exceptions\UnexpectedResponseCodeException;
use kyoden\GarminConnect\ParametersBuilder\AuthParameters;
use kyoden\GarminConnect\ParametersBuilder\ParametersBuilder;
use Carbon\Carbon;

class GarminConnect
{
    const DATA_TYPE_TCX = 'tcx';
    const DATA_TYPE_GPX = 'gpx';
    const DATA_TYPE_GOOGLE_EARTH = 'kml';

    /**
     * @var string
     */
    private $strUsername = '';

    /**
     * @var string
     */
    private $strPassword = '';

    /**
     * @var GarminConnect\Connector|null
     */
    private $objConnector = null;

    /**
     * Performs some essential setup
     *
     * @param array $arrCredentials
     * @throws \Exception
     */
    public function __construct(array $arrCredentials = array())
    {
        if (!isset($arrCredentials['username'])) {
            throw new \Exception("Username credential missing");
        }

        $this->strUsername = $arrCredentials['username'];
        unset($arrCredentials['username']);

        $intIdentifier = md5($this->strUsername);

        $this->objConnector = new Connector($intIdentifier);

        // If we can validate the cached auth, we don't need to do anything else
        if ($this->checkCookieAuth()) {
            return;
        }

        if (!isset($arrCredentials['password'])) {
            throw new \Exception("Password credential missing");
        }

        $this->strPassword = $arrCredentials['password'];
        unset($arrCredentials['password']);

        $this->authorize($this->strUsername, $this->strPassword);
    }

    /**
     * Try to read the username from the API - if successful, it means we have a valid cookie, and we don't need to auth
     *
     * @return bool
     */
    private function checkCookieAuth()
    {
        if (strlen(trim($this->getUsername())) == 0) {
            $this->objConnector->cleanupSession();
            $this->objConnector->refreshSession();
            return false;
        }
        return true;
    }

    /**
     * Because there doesn't appear to be a nice "API" way to authenticate with Garmin Connect, we have to effectively spoof
     * a browser session using some pretty high-level scraping techniques. The connector object does all of the HTTP
     * work, and is effectively a wrapper for CURL-based session handler (via CURLs in-built cookie storage).
     *
     * @param string $strUsername
     * @param string $strPassword
     * @throws AuthenticationException
     * @throws UnexpectedResponseCodeException
     */
    private function authorize($strUsername, $strPassword)
    {
        $params = new ParametersBuilder();
        $params->set('service', ParametersBuilder::EQUAL, 'https://connect.garmin.com/modern/');
        $params->set('clientId', ParametersBuilder::EQUAL, 'GarminConnect');
        $params->set('gauthHost', ParametersBuilder::EQUAL, 'https://connect.garmin.com/post-auth/login');
        $params->set('consumeServiceTicket', ParametersBuilder::EQUAL, 'false');

        $strResponse = $this->objConnector->get("https://sso.garmin.com/sso/login", $params);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "SSO prestart error (code: %d, message: %s)",
                $this->objConnector->getLastResponseCode(),
                $strResponse
            ));
        }

        $authParameters = new AuthParameters();
        $authParameters->username($strUsername);
        $authParameters->password($strPassword);

        $strResponse = $this->objConnector->post("https://sso.garmin.com/sso/login", $params, $authParameters, false);
        preg_match("/ticket=([^\"]+)\"/", $strResponse, $arrMatches);

        if (!isset($arrMatches[1])) {
            $strMessage = "Authentication failed - please check your credentials";

            preg_match("/locked/", $strResponse, $arrLocked);

            if (isset($arrLocked[0])) {
                $strMessage = "Authentication failed, and it looks like your account has been locked. Please access https://connect.garmin.com to unlock";
            }

            $this->objConnector->cleanupSession();
            throw new AuthenticationException($strMessage);
        }

        $strTicket = $arrMatches[0];
        

        $params = new ParametersBuilder();
        $params->set('ticket', ParametersBuilder::EQUAL, $strTicket);

        $this->objConnector->post('https://connect.garmin.com/modern/', $params, null, false);
        if ($this->objConnector->getLastResponseCode() != 302) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }

        // should only exist if the above response WAS a 302 ;)
        $arrCurlInfo = $this->objConnector->getCurlInfo();
        $strRedirectUrl = $arrCurlInfo['redirect_url'];

        $this->objConnector->get($strRedirectUrl, null, true);
        if (!in_array($this->objConnector->getLastResponseCode(), array(200, 302))) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }

        // Fires up a fresh CuRL instance, because of our reliance on Cookies requiring "a new page load" as it were ...
        $this->objConnector->refreshSession();
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityTypes()
    {
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/proxy/activity-service/activity/activityTypes',
            null,
            false
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUserGearList()
    {
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/proxy/userstats-service/gears/all',
            null,
            false
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUserGear($uuid)
    {
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/proxy/gear-service/gear/'.$uuid,
            null,
            false
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityGear($activity_id)
    {
        $arrParams = array(
            'activityId' => $activity_id
        );
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/proxy/gear-service/gear/filterGear',
            $arrParams,
            true
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }


    /**
      * Get count of activities for the given user
      * @return mixed
      * @throws UnexpectedResponseCodeException
      */
    public function getActivityCount()
    {
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/proxy/activitylist-service/activities/count',
            null,
            false
        ); 
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets a list of activities
     *
     * @param ActivityFilter $filter
     * @throws UnexpectedResponseCodeException
     * @return mixed
     */
    public function getActivityList(ActivityFilter $filter = null)
    {
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/proxy/activitylist-service/activities/search/activities',
            $filter,
            true
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets the summary information for the activity
     *
     * @param integer $intActivityID
     * @return mixed
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getActivitySummary($intActivityID)
    {
        $strResponse = $this->objConnector->get("https://connect.garmin.com/proxy/activity-service/activity/" . $intActivityID);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets the detailed information for the activity
     *
     * @param integer $intActivityID
     * @return mixed
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getActivityDetails($intActivityID)
    {
        $strResponse = $this->objConnector->get("https://connect.garmin.com/proxy/activity-service/activity/" . $intActivityID . "/details?maxChartSize=100&maxPolylineSize=100");
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets the extended details for the activity
     *
     * @param $intActivityID
     * @return mixed
     */
    public function getExtendedActivityDetails($intActivityID)
    {
        $strResponse = $this->objConnector->get("https://connect.garmin.com/proxy/activity-service/activity/" . $intActivityID . "/details");
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        return json_decode($strResponse);
    }

    /**
     * Retrieves the data file for the activity
     *
     * @param string $strType
     * @param $intActivityID
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     * @throws \Exception
     * @return mixed
     */
    public function getDataFile($strType, $intActivityID)
    {
        switch ($strType) {
            case self::DATA_TYPE_GPX:
            case self::DATA_TYPE_TCX:
            case self::DATA_TYPE_GOOGLE_EARTH:
                break;

            default:
                throw new \Exception("Unsupported data type");
        }

        $strUrl = "https://connect.garmin.com/proxy/download-service/export/" . $strType . "/activity/" . $intActivityID;

        $strResponse = $this->objConnector->get($strUrl);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        return $strResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUsername()
    {
        $strResponse = $this->objConnector->get('https://connect.garmin.com/modern/currentuser-service/user/info');
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return is_object($objResponse) ? $objResponse->username : null;
    }

    /**
     * Get Wellness daily summary for the given user
     * @param  Carbon $summaryDate
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getWellnessDailySummary(Carbon $summaryDate = null)
    {
        $garminUsername = $this->getUsername();
        if ($summaryDate === null) {
            $summaryDate = Carbon::now();
        }
        $strResponse = $this->objConnector->get('https://connect.garmin.com/proxy/wellness-service/wellness/dailySummary/'.$summaryDate->toDateString().'/'.$garminUsername);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

}
