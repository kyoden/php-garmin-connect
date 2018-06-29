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
 * @author    Cyril Laury & Dave Wilcock & Gwenael Helleux
 * @copyright David Wilcock &copy; 2014 Cyril Laury &copy; 2018 Gwenal Helleux &copy; 2018
 * @package
 */

namespace kyoden;

use Carbon\Carbon;
use kyoden\GarminConnect\Connector;
use kyoden\GarminConnect\exceptions\AuthenticationException;
use kyoden\GarminConnect\exceptions\UnexpectedResponseCodeException;
use kyoden\GarminConnect\ParametersBuilder\ActivityFilter;
use kyoden\GarminConnect\ParametersBuilder\AuthParameters;
use kyoden\GarminConnect\ParametersBuilder\ParametersBuilder;

class GarminConnect
{
    const BASE_URL_CG = 'https://connect.garmin.com';

    const DATA_TYPE_TCX = 'tcx';
    const DATA_TYPE_GPX = 'gpx';
    const DATA_TYPE_GOOGLE_EARTH = 'kml';

    /**
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @var GarminConnect\Connector|null
     */
    private $connector = null;

    /**
     * Performs some essential setup
     *
     * @param array $credentials
     * @param bool  $resetSession (default: false)
     *
     * @throws \Exception
     */
    public function __construct(array $credentials = [], bool $resetSession = false)
    {
        if (!isset($credentials['username'])) {
            throw new \Exception('Username credential missing');
        }

        $this->username = $credentials['username'];
        unset($credentials['username']);

        $this->connector = new Connector(md5($this->username));

        if ($resetSession) {
            $this->connector->cleanupSession();
        } elseif ($this->checkCookieAuth()) {
            return;
        }

        if (!isset($credentials['password'])) {
            throw new \Exception('Password credential missing');
        }

        $this->password = $credentials['password'];
        unset($credentials['password']);

        $this->authorize($this->username, $this->password);
    }

    /**
     * Try to read the username from the API - if successful, it means we have a valid cookie, and we don't need to auth
     *
     * @return bool
     */
    private function checkCookieAuth(): bool
    {
        try {
            if (strlen(trim($this->getUsername())) == 0) {
                $this->connector->cleanupSession();
                $this->connector->refreshSession();

                return false;
            }

            return true;
        } catch (UnexpectedResponseCodeException $e) {
            return false;
        }
    }

    /**
     * Because there doesn't appear to be a nice "API" way to authenticate with Garmin Connect, we have to effectively spoof
     * a browser session using some pretty high-level scraping techniques. The connector object does all of the HTTP
     * work, and is effectively a wrapper for CURL-based session handler (via CURLs in-built cookie storage).
     *
     * @param string $username
     * @param string $password
     *
     * @throws AuthenticationException
     * @throws UnexpectedResponseCodeException
     */
    private function authorize(string $username, string $password): void
    {
        $params = new ParametersBuilder();
        $params->set('service', ParametersBuilder::EQUAL, 'https://connect.garmin.com/modern/');
        $params->set('clientId', ParametersBuilder::EQUAL, 'GarminConnect');
        $params->set('gauthHost', ParametersBuilder::EQUAL, 'https://connect.garmin.com/post-auth/login');
        $params->set('consumeServiceTicket', ParametersBuilder::EQUAL, 'false');

        $response = $this->connector->get("https://sso.garmin.com/sso/login", $params);
        if ($this->connector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "SSO prestart error (code: %d, message: %s)",
                $this->connector->getLastResponseCode(),
                $response
            ));
        }

        $authParameters = new AuthParameters();
        $authParameters->username($username);
        $authParameters->password($password);

        $response = $this->connector->post("https://sso.garmin.com/sso/login", $params, $authParameters, false);
        preg_match("/ticket=([^\"]+)\"/", $response, $matches);

        if (!isset($matches[1])) {
            $message = "Authentication failed - please check your credentials";

            preg_match("/locked/", $response, $locked);

            if (isset($locked[0])) {
                $message = "Authentication failed, and it looks like your account has been locked. Please access https://connect.garmin.com to unlock";
            }

            $this->connector->cleanupSession();
            throw new AuthenticationException($message);
        }

        $ticket = $matches[0];

        $params = new ParametersBuilder();
        $params->set('ticket', ParametersBuilder::EQUAL, $ticket);

        $this->connector->post(self::BASE_URL_CG . '/modern/', $params, null, false);
        if ($this->connector->getLastResponseCode() != 302) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        // should only exist if the above response WAS a 302 ;)
        $curlInfo = $this->connector->getCurlInfo();
        $redirectUrl = $curlInfo['redirect_url'];

        $this->connector->get($redirectUrl, null, true);
        if (!in_array($this->connector->getLastResponseCode(), array(200, 302))) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        // Fires up a fresh CuRL instance, because of our reliance on Cookies requiring "a new page load" as it were ...
        $this->connector->refreshSession();
    }

    /**
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityTypes(): ?\stdClass
    {
        $response = $this->connector->get(
            self::BASE_URL_CG . '/proxy/activity-service/activity/activityTypes',
            null,
            false
        );
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }

    /**
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    public function getUserGearList(): ?\stdClass
    {
        $response = $this->connector->get(
            self::BASE_URL_CG . '/proxy/userstats-service/gears/all',
            null,
            false
        );
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }

    /**
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    public function getUserGear(string $uuid): ?\stdClass
    {
        $response = $this->connector->get(
            self::BASE_URL_CG . '/proxy/gear-service/gear/' . $uuid,
            null,
            false
        );
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }

    /**
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityGear(int $activityId): ?\stdClass
    {
        $response = $this->connector->get(
            self::BASE_URL_CG . '/proxy/gear-service/gear/filterGear',
            ['activityId' => $activityId],
            true
        );
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }

    /**
     * Get count of activities for the given user
     *
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityCount(): ?\stdClass
    {
        $response = $this->connector->get(
            self::BASE_URL_CG . '/proxy/activitylist-service/activities/count',
            null,
            false
        );
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }

    /**
     * Gets a list of activities
     *
     * @param ActivityFilter $filter
     *
     * @throws UnexpectedResponseCodeException
     * @return array
     */
    public function getActivityList(ActivityFilter $filter = null): ?array
    {
        $response = $this->connector->get(
            self::BASE_URL_CG . '/proxy/activitylist-service/activities/search/activities',
            $filter,
            true
        );
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }

    /**
     * Gets the summary information for the activity
     *
     * @param integer $activityID
     *
     * @return mixed
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getActivitySummary(int $activityID): ?\stdClass
    {
        return $this->get(self::BASE_URL_CG . '/proxy/activity-service/activity/' . $activityID);
    }

    /**
     * Gets the detailed information for the activity
     *
     * @param integer $activityID
     *
     * @return \stdClass
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getActivityDetails(int $activityID): ?\stdClass
    {
        return $this->get(self::BASE_URL_CG . '/proxy/activity-service/activity/' . $activityID . '/details?maxChartSize=100&maxPolylineSize=100');
    }

    /**
     * Gets the extended details for the activity
     *
     * @param $activityID
     *
     * @return \stdClass
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getExtendedActivityDetails(int $activityID): ?\stdClass
    {
        return $this->get(self::BASE_URL_CG . '/proxy/activity-service/activity/' . $activityID . '/details');
    }

    /**
     * Retrieves the data file for the activity
     *
     * @param string $type
     * @param int    $activityID
     *
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     * @throws \Exception
     * @return \stdClass
     */
    public function getDataFile(string $type, int $activityID): ?\stdClass
    {
        switch ($type) {
            case self::DATA_TYPE_GPX:
            case self::DATA_TYPE_TCX:
            case self::DATA_TYPE_GOOGLE_EARTH:
                break;

            default:
                throw new \Exception('Unsupported data type');
        }

        return $this->get(self::BASE_URL_CG . '/proxy/download-service/export/' . $type . '/activity/' . $activityID);
    }

    /**
     * @return string
     * @throws UnexpectedResponseCodeException
     */
    public function getUsername(): ?string
    {
        $response = $this->get(self::BASE_URL_CG . '/modern/currentuser-service/user/info');

        return is_object($response) ? $response->username : null;
    }

    /**
     * Get Wellness daily summary for the given user
     *
     * @param  Carbon $summaryDate
     *
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    public function getWellnessDailySummary(Carbon $summaryDate = null): ?\stdClass
    {
        if ($summaryDate === null) {
            $summaryDate = Carbon::now();
        }

        return $this->get(self::BASE_URL_CG . '/proxy/wellness-service/wellness/dailySummary/' . $summaryDate->toDateString() . '/' . $this->getUsername());
    }

    /**
     * @param string $url
     *
     * @return \stdClass
     * @throws UnexpectedResponseCodeException
     */
    private function get(string $url): ?\stdClass
    {
        $response = $this->connector->get($url);
        if ($this->connector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return json_decode($response);
    }
}
