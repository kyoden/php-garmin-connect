<?php
/**
 * GarminConnect.php.
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
 */

namespace GarminConnect;

use Carbon\Carbon;
use GarminConnect\Exception\AuthenticationException;
use GarminConnect\Exception\UnexpectedResponseCodeException;
use GarminConnect\ParametersBuilder\ActivityFilter;
use GarminConnect\ParametersBuilder\AuthParameters;
use GarminConnect\ParametersBuilder\ParametersBuilder;

class GarminConnect
{
    private const HTTP_OK = 200;
    private const HTTP_FOUND = 302;

    private const URL_GARMIN_CONNECT = 'https://connect.garmin.com';
    private const URL_GARMIN_CONNECT_SSO = 'https://sso.garmin.com/sso/login';

    public const DATA_TYPE_CSV = 'csv';
    public const DATA_TYPE_TCX = 'tcx';
    public const DATA_TYPE_GPX = 'gpx';
    public const DATA_TYPE_GOOGLE_EARTH = 'kml';

    /**
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @var Connector|null
     */
    private $connector = null;

    /**
     * Performs some essential setup.
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
            $this->connector->clearCookie();
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
     * Try to read the username from the API - if successful, it means we have a valid cookie, and we don't need to auth.
     *
     * @return bool
     */
    private function checkCookieAuth(): bool
    {
        try {
            if (0 == strlen(trim($this->getUsername()))) {
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
        $params = (new ParametersBuilder())
            ->set('service', ParametersBuilder::EQUAL, 'https://connect.garmin.com/modern/')
            ->set('webhost', ParametersBuilder::EQUAL, 'https://connect.garmin.com')
            ->set('source', ParametersBuilder::EQUAL, 'https://connect.garmin.com/en-US/signin')
            ->set('clientId', ParametersBuilder::EQUAL, 'GarminConnect')
            ->set('gauthHost', ParametersBuilder::EQUAL, 'https://sso.garmin.com/sso')
            ->set('consumeServiceTicket', ParametersBuilder::EQUAL, 'false');

        $response = $this->connector->get(self::URL_GARMIN_CONNECT_SSO, $params);
        if ($this->connector->getLastResponseCode() != static::HTTP_OK) {
            throw new AuthenticationException(sprintf(
                'SSO prestart error (code: %d, message: %s)',
                $this->connector->getLastResponseCode(),
                $response
            ));
        }
        preg_match('/name="_csrf" value="(.*)"/', $response, $csrfMatches);

        if (!isset($csrfMatches[1])) {
            throw new AuthenticationException('Unable to find CSRF input in login form');
        }

        $authParameters = (new AuthParameters())
            ->username($username)
            ->password($password)
            ->csrf($csrfMatches[1]);

        $response = $this->connector->post(self::URL_GARMIN_CONNECT_SSO, $params, $authParameters, false, 'https://sso.garmin.com/sso/login?' . $params->build());
        preg_match('/ticket=([^"]+)"/', $response, $matches);

        if (!isset($matches[1])) {
            $message = 'Authentication failed - please check your credentials';

            preg_match('/locked/', $response, $locked);
            if (isset($locked[0])) {
                $message = 'Authentication failed, and it looks like your account has been locked. Please access https://connect.garmin.com to unlock';
            }

            $this->connector->cleanupSession();
            throw new AuthenticationException($message);
        }

        $ticket = rtrim($matches[0], '"');

        $params = new ParametersBuilder();
        $params->set('ticket', ParametersBuilder::EQUAL, $ticket);

        $this->connector->post(self::URL_GARMIN_CONNECT . '/modern/', $params, null, false);
        if ($this->connector->getLastResponseCode() !== static::HTTP_FOUND) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        // should only exist if the above response WAS a 302 ;)
        $curlInfo = $this->connector->getCurlInfo();
        $redirectUrl = $curlInfo['redirect_url'];

        $this->connector->get($redirectUrl, null, true);
        if (!in_array($this->connector->getLastResponseCode(), [static::HTTP_OK, static::HTTP_FOUND])) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        // Fires up a fresh CuRL instance, because of our reliance on Cookies requiring "a new page load" as it were ...
        $this->connector->refreshSession();
    }

    /**
     * @return \stdClass
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityTypes(): ?\stdClass
    {
        return $this->get(
            self::URL_GARMIN_CONNECT . '/proxy/activity-service/activity/activityTypes',
            null,
            false
        );
    }

    /**
     * @return \stdClass
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getUserGearList(): ?\stdClass
    {
        return $this->get(
            self::URL_GARMIN_CONNECT . '/proxy/userstats-service/gears/all',
            null,
            false
        );
    }

    /**
     * @param string $uuid
     *
     * @return \stdClass|null
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getUserGear(string $uuid): ?\stdClass
    {
        return $this->get(
            self::URL_GARMIN_CONNECT . '/proxy/gear-service/gear/' . $uuid,
            null,
            false
        );
    }

    /**
     * @param int $activityId
     *
     * @return \stdClass|null
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityGear(int $activityId): ?array
    {
        return $this->get(
            self::URL_GARMIN_CONNECT . '/proxy/gear-service/gear/filterGear',
            (new ParametersBuilder())->set('activityId', ParametersBuilder::EQUAL, $activityId),
            true
        );
    }

    /**
     * Get count of activities for the given user.
     *
     * @return \stdClass
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityCount(): ?\stdClass
    {
        return $this->get(
            self::URL_GARMIN_CONNECT . '/proxy/activitylist-service/activities/count',
            null,
            false
        );
    }

    /**
     * @param ActivityFilter|null $filter
     *
     * @return array|null
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityList(ActivityFilter $filter = null): ?array
    {
        return $this->get(
            self::URL_GARMIN_CONNECT . '/proxy/activitylist-service/activities/search/activities',
            $filter,
            true
        );
    }

    /**
     * Gets all activities.
     *
     * @param ActivityFilter $filter
     *
     * @throws UnexpectedResponseCodeException
     *
     * @return array
     */
    public function getAllActivityList(ActivityFilter $filter): ?array
    {
        $page = 0;
        $limit = 100;
        $data = [];

        $filter->limit($limit);
        do {
            $filter->start($page * $limit);
            $found = $this->getActivityList($filter);
            $data = array_merge($data, $found);
            ++$page;
        } while (count($found) == $limit);

        return $data;
    }

    /**
     * Gets the summary information for the activity.
     *
     * @param int $activityID
     *
     * @return mixed
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getActivitySummary(int $activityID): ?\stdClass
    {
        return $this->get(self::URL_GARMIN_CONNECT . '/proxy/activity-service/activity/' . $activityID);
    }

    /**
     * Gets the detailed information for the activity.
     *
     * @param int $activityID
     *
     * @return \stdClass
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityDetails(int $activityID): ?\stdClass
    {
        return $this->get(self::URL_GARMIN_CONNECT . '/proxy/activity-service/activity/' . $activityID . '/details?maxChartSize=100&maxPolylineSize=100');
    }

    /**
     * Gets the extended details for the activity.
     *
     * @param $activityID
     *
     * @return \stdClass
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getExtendedActivityDetails(int $activityID): ?\stdClass
    {
        return $this->get(self::URL_GARMIN_CONNECT . '/proxy/activity-service/activity/' . $activityID . '/details');
    }

    /**
     * Retrieves the data file for the activity.
     *
     * @param string $type
     * @param int    $activityID
     *
     * @throws UnexpectedResponseCodeException
     * @throws \Exception
     *
     * @return string
     */
    public function getDataFile(string $type, int $activityID): ?string
    {
        switch ($type) {
            case self::DATA_TYPE_CSV:
            case self::DATA_TYPE_GPX:
            case self::DATA_TYPE_TCX:
            case self::DATA_TYPE_GOOGLE_EARTH:
                break;

            default:
                throw new \Exception('Unsupported data type');
        }

        return $this->getRaw(self::URL_GARMIN_CONNECT . '/proxy/download-service/export/' . $type . '/activity/' . $activityID);
    }

    /**
     * @return string
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getUsername(): ?string
    {
        $response = $this->get(self::URL_GARMIN_CONNECT . '/modern/currentuser-service/user/info');

        return is_object($response) ? $response->username : null;
    }

    /**
     * Get Wellness daily summary for the given user.
     *
     * @param Carbon $summaryDate
     *
     * @return \stdClass
     *
     * @throws UnexpectedResponseCodeException
     */
    public function getWellnessDailySummary(Carbon $summaryDate = null): ?\stdClass
    {
        if (null === $summaryDate) {
            $summaryDate = Carbon::now();
        }

        return $this->get(self::URL_GARMIN_CONNECT . '/proxy/wellness-service/wellness/dailySummary/' . $summaryDate->toDateString() . '/' . $this->getUsername());
    }

    /**
     * @param string                 $url
     * @param ParametersBuilder|null $params
     * @param bool                   $allowRedirects
     *
     * @return \stdClass|array|null
     *
     * @throws UnexpectedResponseCodeException
     */
    private function get(string $url, ?ParametersBuilder $params = null, bool $allowRedirects = true)
    {
        return json_decode($this->getRaw($url, $params, $allowRedirects));
    }

    /**
     * @param string                 $url
     * @param ParametersBuilder|null $params
     * @param bool                   $allowRedirects
     *
     * @return string|null
     *
     * @throws UnexpectedResponseCodeException
     */
    private function getRaw(string $url, ?ParametersBuilder $params = null, bool $allowRedirects = true): ?string
    {
        $response = $this->connector->get($url, $params, $allowRedirects);
        if ($this->connector->getLastResponseCode() !== static::HTTP_OK) {
            throw new UnexpectedResponseCodeException($this->connector->getLastResponseCode());
        }

        return $response;
    }
}
