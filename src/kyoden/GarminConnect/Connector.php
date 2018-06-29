<?php
/**
 * Connector.php
 *
 * LICENSE: THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    David Wilcock <dave.wilcock@gmail.com>
 * @author    Gwenael Helleux
 * @copyright David Wilcock &copy; 2014
 * @package
 */

namespace kyoden\GarminConnect;

use kyoden\GarminConnect\ParametersBuilder\ParametersBuilder;

class Connector
{
    /**
     * @var null|resource
     */
    private $curl = null;
    private $curlInfo = [];
    private $cookieDirectory = '';

    /**
     * @var array
     */
    private $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIESESSION  => false,
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_VERBOSE        => false,
        CURLOPT_FRESH_CONNECT  => true,
    ];

    /**
     * @var int
     */
    private $lastResponseCode = -1;

    /**
     * @var string
     */
    private $cookieFile = '';

    /**
     * @param string $uniqueIdentifier
     *
     * @throws \Exception
     */
    public function __construct(string $uniqueIdentifier)
    {
        $this->cookieDirectory = sys_get_temp_dir();
        if (strlen(trim($uniqueIdentifier)) == 0) {
            throw new \Exception("Identifier isn't valid");
        }
        $this->cookieFile = $this->cookieDirectory . DIRECTORY_SEPARATOR . "GarminCookie_" . $uniqueIdentifier;
        $this->refreshSession();
    }

    /**
     * Create a new curl instance
     */
    public function refreshSession(): void
    {
        $this->curl = curl_init();
        $this->curlOptions[CURLOPT_COOKIEJAR] = $this->cookieFile;
        $this->curlOptions[CURLOPT_COOKIEFILE] = $this->cookieFile;
        curl_setopt_array($this->curl, $this->curlOptions);
    }

    /**
     * @param string            $url
     * @param ParametersBuilder $params
     * @param bool              $allowRedirects
     *
     * @return string
     */
    public function get($url, ParametersBuilder $params = null, bool $allowRedirects = true): string
    {
        if ($params) {
            $url .= '?' . $params->build();
        }

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, (bool)$allowRedirects);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->curl);
        $curlInfo = curl_getinfo($this->curl);
        $this->lastResponseCode = $curlInfo['http_code'];

        return $response;
    }

    /**
     * @param string            $url
     * @param ParametersBuilder $params
     * @param ParametersBuilder $data
     * @param bool              $allowRedirects
     *
     * @return mixed
     */
    public function post(string $url, ParametersBuilder $params = null, ParametersBuilder $data = null, bool $allowRedirects = true)
    {
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, (bool)$allowRedirects);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->curl, CURLOPT_VERBOSE, false);
        if ($data) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data->build());
        }
        if ($params) {
            $url .= '?' . $params->build();
        }
        curl_setopt($this->curl, CURLOPT_URL, $url);

        $response = curl_exec($this->curl);
        $this->curlInfo = curl_getinfo($this->curl);
        $this->lastResponseCode = (int)$this->curlInfo['http_code'];

        return $response;
    }

    /**
     * @return array
     */
    public function getCurlInfo(): array
    {
        return $this->curlInfo;
    }

    /**
     * @return int
     */
    public function getLastResponseCode(): int
    {
        return $this->lastResponseCode;
    }

    /**
     * Removes the cookie
     */
    public function clearCookie(): void
    {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    /**
     * Closes curl and then clears the cookie.
     */
    public function cleanupSession(): void
    {
        curl_close($this->curl);
        $this->clearCookie();
    }
}
