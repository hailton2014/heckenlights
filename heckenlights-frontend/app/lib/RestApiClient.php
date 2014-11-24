<?php

class RestApiClient
{
    private $baseUrl;
    private $sessionId;
    private $proxySettings;
    private $userAgent = "PHP-RestApiClient/22.0";
    private $compressionEnabled = true;
    private $includeSessionCookie = false;
    private $logs;
    private $loggingEnabled = false;

    public static function getMethods()
    {
        return array("GET", "POST", "PUT", "PATCH", "DELETE", "HEAD");
    }

    public static function getMethodsWithBodies()
    {
        return array("POST", "PUT", "PATCH");
    }

    public function __construct($baseUrl, $sessionId)
    {
        if (!extension_loaded('curl')) {
            throw new Exception('Missing required cURL extension.');
        }

        $this->sessionId = $sessionId;
        $this->baseUrl = $baseUrl;
    }

    public function setProxySettings($proxySettings)
    {
        $this->proxySettings = $proxySettings;
    }

    public function setIncludeSessionCookie($includeSessionCookie)
    {
        $this->includeSessionCookie = $includeSessionCookie;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function getCompressionEnabled()
    {
        return $this->compressionEnabled;
    }

    public function setCompressionEnabled($compressionEnabled)
    {
        $this->compressionEnabled = $compressionEnabled;
    }


    public function send($method, $path, $additionalHeaders, $data)
    {

        global $requestId;
        if (strpos($path, "/") !== 0) {
            throw new Exception("Path must start with /");
        }

        $this->log("INITIALIZING cURL \n" . print_r(curl_version(), true));

        $ch = curl_init();

        $httpHeaders = array(
            "User-Agent: " . $this->userAgent,
            "X-Tracking.SessionId: " . session_id(),
            "X-Tracking.RequestId: " . $requestId,
            "X-Tracking.UserId: " . $_SERVER['REMOTE_HOST']
        );

        if (isset($additionalHeaders) && is_array($additionalHeaders)) {
            $httpHeaders = array_merge($httpHeaders, $additionalHeaders);
        }

        // clean up malformed headers, which apparently is not handled by some cUrl libs @see Issue 583
        foreach ($httpHeaders as $k => $v) {
            $httpHeaders[$k] = trim($v);
            if (strpos($v, ":") === false) {
                unset($httpHeaders[$k]);
            }
        }

        switch ($method) {
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                break;
            case 'GET':
                // do nothing
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case 'PATCH':
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            default:
                throw new Exception($method . ' method not supported.');
        }

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if ($this->proxySettings != null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxySettings["proxy_host"]);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxySettings["proxy_port"]);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxySettings["proxy_username"] . ":" . $this->proxySettings["proxy_password"]);
        }

        if ($this->compressionEnabled) {
            curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        }

        $cookies = array();
        if ($this->includeSessionCookie) {
            $cookies[] = "sid=" . $this->sessionId;
        }

        if (count($cookies) > 0) {
            curl_setopt($ch, CURLOPT_COOKIE, implode("; ", $cookies));
        }

        $this->log("REQUEST \n METHOD: $method \n PATH: $path \n HTTP HEADERS: \n" . print_r($httpHeaders, true) . " DATA:\n " . htmlspecialchars($data));

        $chResponse = curl_exec($ch);
        $this->log("RESPONSE \n" . htmlspecialchars($chResponse));

        if (curl_error($ch) != null) {
            $this->log("ERROR \n" . htmlspecialchars(curl_error($ch)));
            throw new Exception(curl_error($ch));
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if ($this->proxySettings != null) {
            $proxyHeader = "HTTP/1.0 200 Connection established";
            if (strpos($chResponse, $proxyHeader) === 0) {
                $headerSize += strlen($proxyHeader);
            }
        }

        $httpResponse = new HttpResponse($chResponse, $headerSize);

        curl_close($ch);

        return $httpResponse;
    }

    //LOGGING FUNCTIONS

    public function isLoggingEnabled()
    {
        return $this->loggingEnabled;
    }

    public function setLoggingEnabled($loggingEnabled)
    {
        $this->loggingEnabled = $loggingEnabled;
    }

    protected function log($txt)
    {
        if ($this->loggingEnabled) {
            $this->logs .= $txt .= "\n\n";
        }
        return $txt;
    }

    public function setExternalLogReference(&$extLogs)
    {
        $this->logs = &$extLogs;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    public function clearLogs()
    {
        $this->logs = null;
    }
}

class HttpResponse
{
    public $header;
    public $body;

    public function __construct($curlResponse, $headerSize)
    {
        $this->header = substr($curlResponse, 0, $headerSize);
        $this->body = substr($curlResponse, $headerSize);
    }
}

?>