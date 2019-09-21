<?php
/**
 * Connector.php.
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
 */

namespace GarminConnect;

use GarminConnect\ParametersBuilder\ParametersBuilder;

class Connector
{
    /**
     * @var resource|null
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
        CURLOPT_COOKIESESSION => false,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_VERBOSE => false,
        CURLOPT_FRESH_CONNECT => true,
    ];

    /**
     * @var string
     */
    private $cookieFile = '';

    /**
     * @param string $uniqueIdentifier
     * @param string $cookieDirectory
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $uniqueIdentifier, ?string $cookieDirectory = null)
    {
        if (0 == strlen(trim($uniqueIdentifier))) {
            throw new \InvalidArgumentException('Identifier isn\'t valid');
        }

        $this->cookieDirectory = $cookieDirectory ?? sys_get_temp_dir();
        $this->cookieFile = $this->cookieDirectory . DIRECTORY_SEPARATOR . 'GarminCookie_' . $uniqueIdentifier;
        $this->refreshSession();
    }

    /**
     * Create a new curl instance.
     */
    public function refreshSession(): void
    {
        $this->curl = curl_init();
        $this->curlOptions[CURLOPT_COOKIEJAR] = $this->cookieFile;
        $this->curlOptions[CURLOPT_COOKIEFILE] = $this->cookieFile;
        curl_setopt_array($this->curl, $this->curlOptions);
    }

    /**
     * @param string                 $url
     * @param ParametersBuilder|null $params
     * @param bool                   $allowRedirects
     *
     * @return string
     */
    public function get(string $url, ?ParametersBuilder $params = null, bool $allowRedirects = true): string
    {
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');

        return $this->call($url, $params, $allowRedirects);
    }

    /**
     * @param string                 $url
     * @param ParametersBuilder|null $params
     * @param ParametersBuilder|null $data
     * @param bool                   $allowRedirects
     * @param string|null            $referer
     *
     * @return mixed
     */
    public function post(string $url, ?ParametersBuilder $params = null, ?ParametersBuilder $data = null, bool $allowRedirects = true, ?string $referer = null)
    {
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->curl, CURLOPT_VERBOSE, false);
        if ($data) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data->build());
        }
        if (null !== $referer) {
            curl_setopt($this->curl, CURLOPT_REFERER, $referer);
        }

        return $this->call($url, $params, $allowRedirects);
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
        return $this->curlInfo ? (int) $this->curlInfo['http_code'] : -1;
    }

    /**
     * Removes the cookie.
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

    /**
     * @param string                 $url
     * @param ParametersBuilder|null $params
     * @param bool                   $allowRedirects
     *
     * @return string
     */
    private function call(string $url, ?ParametersBuilder $params = null, bool $allowRedirects = true): string
    {
        if ($params) {
            $url .= '?' . $params->build();
        }

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, (bool) $allowRedirects);

        $response = curl_exec($this->curl);
        $this->curlInfo = curl_getinfo($this->curl);

        return $response;
    }
}
