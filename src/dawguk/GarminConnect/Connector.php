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
 * @author David Wilcock <dave.wilcock@gmail.com>
 * @copyright David Wilcock &copy; 2014
 * @package
 */

namespace dawguk\GarminConnect;

use dawguk\GarminConnect\ParametersBuilder\ParametersBuilder;

class Connector
{
   /**
    * @var null|resource
    */
    private $objCurl = null;
    private $arrCurlInfo = array();
    private $strCookieDirectory = '';

   /**
    * @var array
    */
    private $arrCurlOptions = array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_COOKIESESSION => false,
      CURLOPT_AUTOREFERER => true,
      CURLOPT_VERBOSE => false,
      CURLOPT_FRESH_CONNECT => true
    );

   /**
    * @var int
    */
    private $intLastResponseCode = -1;

   /**
    * @var string
    */
    private $strCookieFile = '';

   /**
    * @param string $strUniqueIdentifier
    * @throws \Exception
    */
    public function __construct($strUniqueIdentifier)
    {
        $this->strCookieDirectory = sys_get_temp_dir();
        if (strlen(trim($strUniqueIdentifier)) == 0) {
            throw new \Exception("Identifier isn't valid");
        }
        $this->strCookieFile = $this->strCookieDirectory . DIRECTORY_SEPARATOR . "GarminCookie_" . $strUniqueIdentifier;
        $this->refreshSession();
    }

   /**
    * Create a new curl instance
    */
    public function refreshSession()
    {
        $this->objCurl = curl_init();
        $this->arrCurlOptions[CURLOPT_COOKIEJAR] = $this->strCookieFile;
        $this->arrCurlOptions[CURLOPT_COOKIEFILE] = $this->strCookieFile;
        curl_setopt_array($this->objCurl, $this->arrCurlOptions);
    }

   /**
    * @param string $strUrl
    * @param ParametersBuilder $params
    * @param bool $bolAllowRedirects
    * @return mixed
    */
    public function get($strUrl, ParametersBuilder $params = null, $bolAllowRedirects = true)
    {
        if ($params) {
            $strUrl .= '?' . $params->build();
        }

        curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);
        curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, (bool)$bolAllowRedirects);
        curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, 'GET');

        $strResponse = curl_exec($this->objCurl);
        $arrCurlInfo = curl_getinfo($this->objCurl);
        $this->intLastResponseCode = $arrCurlInfo['http_code'];
        return $strResponse;
    }

   /**
    * @param string $strUrl
    * @param ParametersBuilder $params
    * @param array $arrData
    * @param bool $bolAllowRedirects
    * @return mixed
    */
    public function post($strUrl, ParametersBuilder $params = null, ParametersBuilder $data = null, $bolAllowRedirects = true)
    {
        curl_setopt($this->objCurl, CURLOPT_HEADER, true);
        curl_setopt($this->objCurl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, (bool)$bolAllowRedirects);
        curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->objCurl, CURLOPT_VERBOSE, false);
        if ($data) {
            curl_setopt($this->objCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($this->objCurl, CURLOPT_POSTFIELDS, $data->build());
        }
        if ($params) {
            $strUrl .= '?' . $params->build();
        }
        curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);

        $strResponse = curl_exec($this->objCurl);
        $this->arrCurlInfo = curl_getinfo($this->objCurl);
        $this->intLastResponseCode = (int)$this->arrCurlInfo['http_code'];
        return $strResponse;
    }

   /**
    * @return array
    */
    public function getCurlInfo()
    {
        return $this->arrCurlInfo;
    }

    /**
    * @return int
    */
    public function getLastResponseCode()
    {
        return $this->intLastResponseCode;
    }

    /**
     * Removes the cookie
     */
    public function clearCookie()
    {
        if (file_exists($this->strCookieFile)) {
            unlink($this->strCookieFile);
        }
    }

   /**
    * Closes curl and then clears the cookie.
    */
    public function cleanupSession()
    {
        curl_close($this->objCurl);
        $this->clearCookie();
    }
}
