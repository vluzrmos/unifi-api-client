<?php

namespace JVDS\UnifiApiClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Unifi controller API client.
 *
 * Example usage:
 * <code>
 * use JVDS\UnifiApiClient\Client;
 * use GuzzleHttp\Client as HttpClient;
 *
 * $apiClient = new Client(new HttpClient(['base_uri' => 'https://127.0.0.1:8443']));
 * $apiClient->login('your_username', 'your_password');
 * $apiClient->statistics('default'); // fetch statistics for default site
 * </code>
 *
 * Unifi controllers come with a self signed certificate by default.
 * To verify that you're connecting to your own unifi controller,
 * you should download your controller's certificate.
 * And the verify option should be set to the path of the downloaded certificate:
 *
 * <code>
 * use JVDS\UnifiApiClient\Client;
 * use GuzzleHttp\Client as HttpClient;
 *
 * $apiClient = new Client(
 *     new HttpClient(['base_uri' => 'https://127.0.0.1:8443']),
 *     ['verify' => '/your/unifi/cert.pem']
 * );
 * $apiClient->login('your_username', 'your_password');
 * </code>
 *
 * It is also possible to use your own certificate in the unifi controller.
 * More information about this is available at: http://wiki.ubnt.com/UniFi_FAQ#Custom_SSL_certificate
 *
 * @author Joris van de Sande
 */
class Client
{
    /**
     * @var ClientInterface|null
     */
    protected $client;

    /**
     * @var array
     */
    protected $requestOptions;

    /**
     * @var array
     */
    protected $loginData;

    /**
     * @param ClientInterface $client
     * @param array $requestOptions Guzzle request options that will be sent with every request
     *
     * @link http://docs.guzzlephp.org/en/latest/request-options.html
     */
    public function __construct(ClientInterface $client, array $requestOptions = [])
    {
        $this->client = $client;
        $this->requestOptions = $this->getRequestOptions($requestOptions);
    }

    /**
     * @return ClientInterface|null
     */
    public function httpClient()
    {
        return $this->client;
    }

    /**
     * @param array $defaultRequestOptions
     * @return mixed
     */
    protected function getRequestOptions(array $defaultRequestOptions)
    {
        return array_merge(
            [
                'cookies' => new CookieJar(),
                'verify' => false,
            ],
            $defaultRequestOptions
        );
    }

    /**
     * Authorize a guest by mac address.
     *
     * @param string $site
     * @param string $mac the mac address of the guest to authorize.
     * @param int $minutes number of minutes to authorize guest.
     * @param array $data associative array with extra data, i.e. up (kbps), down (kbps), bytes (MB)
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function authorizeGuest($site, $mac, $minutes, array $data = [])
    {
        return $this->post(
            '/api/s/'.$site.'/cmd/stamgr',
            [
                'cmd' => 'authorize-guest',
                'mac' => $mac,
                'minutes' => $minutes,
            ] + $data
        );
    }

    /**
     * @param string $site
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function deviceStatistics($site)
    {
        return $this->get('/api/s/'.$site.'/stat/device');
    }

    /**
     * @throws GuzzleException in case of a failure.
     */
    public function logout()
    {
        $this->httpClient()->request('get', '/logout', ['allow_redirects' => false] + $this->requestOptions);
    }

    /**
     * @return ResponseInterface
     */
    public function sites()
    {
        return $this->get('/api/self/sites');
    }

    /**
     * @param string $site
     * @param string $mac
     * @return ResponseInterface
     */
    public function reconnectClient($site, $mac)
    {
        return $this->post(
            '/api/s/'.$site.'/cmd/stamgr',
            [
                'cmd' => 'kick-sta',
                'mac' => $mac,
            ]
        );
    }

    /**
     * Login to the Unifi controller.
     * You need to login before you can make other api requests.
     *
     * @param string $username username
     * @param string $password password
     *
     * @return ResponseInterface
     * @throws GuzzleException in case of a login failure.
     */
    public function relogin($username = null, $password = null)
    {
        if (!$username) {
            $username = isset($this->loginData['username']) ? $this->loginData['username'] : null;
        }

        if (!$password) {
            $password = isset($this->loginData['password']) ? $this->loginData['password'] : null;
        }

        return $this->login($username, $password);
    }

    /**
     * Login to the Unifi controller.
     * You need to login before you can make other api requests.
     *
     * @param string $username username
     * @param string $password password
     *
     * @return ResponseInterface
     * @throws GuzzleException in case of a login failure.
     */
    public function login($username, $password)
    {
        $this->loginData = ['username' => $username, 'password' => $password];

        return $this->post(
            '/api/login',
            $this->loginData
        );
    }

    /**
     * @param array $loginData
     */
    public function setLoginData(array $loginData)
    {
        $this->loginData = $loginData;
    }

    /**
     * @param $url
     * @param array $data
     * @return ResponseInterface
     */
    public function put($url, array $data = [])
    {
        return $this->httpClient()->request('put', $url, ['json' => $data] + $this->requestOptions);
    }
    
    /**
     * @param string $url (relative) url to the api endpoint
     * @param array $data data to be sent with the request.
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post($url, array $data = [])
    {
        return $this->httpClient()->request(
            'post',
            $url,
            ['json' => $data] + $this->requestOptions
        );
    }

    /**
     * @param string $site
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function statistics($site)
    {
        return $this->get('/api/s/'.$site.'/stat/sta');
    }

    /**
     * @param string $url
     * @param array $data
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function get($url, array $data = [])
    {
        $requestOptions = $this->requestOptions;

        if ($data) {
            $requestOptions['query'] = $data;
        }

        return $this->httpClient()->request('get', $url, $requestOptions);
    }

    /**
     * Unauthorize a guest by mac address.
     *
     * @param string $site
     * @param string $mac
     *
     * @return ResponseInterface
     */
    public function unauthorizeGuest($site, $mac)
    {
        return $this->post(
            '/api/s/'.$site.'/cmd/stamgr',
            [
                'cmd' => 'unauthorize-guest',
                'mac' => $mac,
            ]
        );
    }
}
