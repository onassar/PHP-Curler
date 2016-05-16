<?php

    // dependecy check
    if (!in_array('curl', get_loaded_extensions())) {
        throw new Exception('cURL extension needs to be installed.');
    }

    /**
     * Curler
     * 
     * Makes cURL requests (GET, HEAD and POST) to a URI.
     *
     * @link    https://github.com/onassar/PHP-Curler 
     * @author  Oliver Nassar <onassar@gmail.com>
     */
    class Curler
    {
        /**
         * _acceptableMimeTypes
         * 
         * Array of acceptable mime types that ought to result in a successful
         * cURL request.
         * 
         * @var    array (default: array())
         * @access protected
         */
        protected $_acceptableMimeTypes = array();

        /**
         * _cookieStoragePath
         * 
         * Path to the cookie file that should be used for temporary storage of
         * cookies that are sent back by a curl. This is only used to ensure
         * servers that require cookie saving to properly respond to a request.
         * 
         * @var    false|string (default: false)
         * @access protected
         */
        protected $_cookieStoragePath = false;

        /**
         * _curlErrors
         * 
         * @var    array (default: array())
         * @access protected
         */
        protected $_curlErrors = array();

        /**
         * _dynamicResponse
         * 
         * Variable used to store content during a get request to ensure
         * filesize limits aren't reached.
         * 
         * @var    string (default: '')
         * @access protected
         */
        protected $_dynamicResponse = '';

        /**
         * _error
         * 
         * Array containing details of a possible error.
         * 
         * @var    false|array (default: false)
         * @access protected
         */
        protected $_error = false;

        /**
         * _headers
         * 
         * Array containing the request headers that will be sent with the curl.
         * 
         * @var    array (default: array())
         * @access protected
         */
        protected $_headers = array();

        /**
         * _headInfo
         * 
         * @var    array
         * @access protected
         */
        protected $_headInfo;

        /**
         * _info
         * 
         * Storage of the info that was returned by the GET and HEAD calls
         * (since a GET is always preceeded by a HEAD).
         * 
         * @var    array
         * @access protected
         */
        protected $_info;

        /**
         * _mimes
         * 
         * @var    array (default: array())
         * @access protected
         */
        protected $_mimes = array();

        /**
         * _options
         * 
         * @var    array
         * @access protected
         */
        protected $_options = array(
            'authCredentials' => array(),
            'maxFilesize' => 1048576,// 1 * 1024 * 1024
            'maxRedirects' => 10,
            'timeout' => 5000,
            'userAgent' =>  'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12',
            'validHTTPCodes' => array(200),
            'validMimeTypes' => 'webpages'
        );

        /**
         * _response
         * 
         * @var    String
         * @access protected
         */
        protected $_response;

        /**
         * _curlOptions
         *
         * Array of curl options
         *
         * @var    array
         * @access protected
         */
        protected $_curlOptions = array();

        /**
         * __construct
         * 
         * @access public
         * @param  array $options (default: array())
         * @return void
         */
        public function __construct($options = array())
        {
            // Cookie path
            $info = pathinfo(__DIR__);
            $parent = ($info['dirname']);
            $this->_cookieStoragePath = ($parent) . '/tmp/cookies.txt';

            // Extend options
            $this->_options = array_merge($this->_options, $options);
            $this->_loadMimeMap();
            $this->_loadCurlErrorMap();
            $this->setMime($this->_options['validMimeTypes']);
            $this->setHeaders(array(
                'Connection' => 'keep-alive',
                'Accept-Language' => 'en-us,en;q=0.5'
            ));
            // Set default curl options
            $this->setCurlOptions(array(
                'CURLOPT_SSL_VERIFYPEER' => false,
                'CURLOPT_SSL_VERIFYHOST' => 2,
                'CURLOPT_FOLLOWLOCATION' => true
                // 'CURLOPT_MAXREDIRS' => $this->_options['maxRedirects']
            ));
        }

        /**
         * _close
         * 
         * @access protected
         * @param  resource $resource
         * @return void
         */
        protected function _close($resource)
        {
            curl_close($resource);
        }

        /**
         * _getHeaders
         * 
         * Parses and returns the headers for the curl request.
         * 
         * @access protected
         * @return array headers formatted to be correctly formed for an HTTP request
         */
        protected function _getHeaders()
        {
            $formatted = array();
            foreach ($this->_headers as $name => $value) {
                array_push($formatted, ($name) . ': ' . ($value));
            }
            return $formatted;
        }

        /**
         * _getResource
         * 
         * Creates a curl resource, set's it up, and returns it's reference.
         * 
         * @access protected
         * @param  string $url
         * @param  boolean $head. (default: false) whether or not this is a HEAD
         *         request, in which case no response-body is returned
         * @return Resource curl resource reference
         */
        protected function _getResource($url, $head = false)
        {
            // ensure cookie is writable by attempting to open it up
            $this->_openCookie();

            // init call, headers, user agent
            $options = $this->_options;
            $resource = curl_init($url);
            curl_setopt($resource, CURLOPT_HTTPHEADER, $this->_getHeaders());
            curl_setopt($resource, CURLOPT_HEADER, false);
            curl_setopt($resource, CURLOPT_USERAGENT, $options['userAgent']);
            curl_setopt($resource, CURLOPT_ENCODING, 'gzip,deflate');


            /**
             * HTTP Authentication
             * 
             */
            if (empty($this->_options['authCredentials']) === false) {
                $username = $this->_options['authCredentials']['username'];
                $password = $this->_options['authCredentials']['password'];
                $token = ($username) . ':' . ($password);
                curl_setopt($resource, CURLOPT_USERPWD, $token);
            }

            /**
             * Cookie file / jar
             * 
             */
            $cookieStoragePath = $this->_cookieStoragePath;
            curl_setopt($resource, CURLOPT_COOKIEFILE, $cookieStoragePath);
            curl_setopt($resource, CURLOPT_COOKIEJAR, $cookieStoragePath);


            /**
             * Timeout
             * 
             */
            $timeout = $options['timeout'];
            curl_setopt($resource, CURLOPT_CONNECTTIMEOUT_MS, $timeout);
            curl_setopt($resource, CURLOPT_TIMEOUT_MS, $timeout);


            /**
             * SSL Security Setitngs
             * 
             */
            $verifyPeer = $this->_curlOptions['CURLOPT_SSL_VERIFYPEER'];
            $verifyHost = $this->_curlOptions['CURLOPT_SSL_VERIFYHOST'];
            curl_setopt($resource, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
            curl_setopt($resource, CURLOPT_SSL_VERIFYHOST, $verifyHost);
            curl_setopt($resource, CURLOPT_FRESH_CONNECT, true);


            /**
             * Other Security Setitngs
             * 
             */
            $followLocation = $this->_curlOptions['CURLOPT_FOLLOWLOCATION'];
            $maxRedirects = (int) $this->_options['maxRedirects'];
            curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($resource, CURLOPT_FOLLOWLOCATION, $followLocation);
            curl_setopt($resource, CURLOPT_MAXREDIRS, $maxRedirects);
            curl_setopt($resource, CURLOPT_NOBODY, $head === true);

            // Done
            return $resource;
        }   

        /**
         * _loadCurlErrorMap
         * 
         * @access protected
         * @return void
         */
        protected function _loadCurlErrorMap()
        {
            $info = pathinfo(__DIR__);
            $parent = ($info['dirname']);
            $path = ($parent) . '/maps/curlErrors.json';
            $content = file_get_contents($path);
            $this->_curlErrors = json_decode($content, true);
        }

        /**
         * _loadMimeMap
         * 
         * @access protected
         * @return void
         */
        protected function _loadMimeMap()
        {
            $info = pathinfo(__DIR__);
            $parent = ($info['dirname']);
            $path = ($parent) . '/maps/mimes.json';
            $content = file_get_contents($path);
            $this->_mimes = json_decode($content, true);
        }

        /**
         * _openCookie
         * 
         * Helper method to either ensure that the cookie file that exists can
         * be written to, or else that the directory that ought to contain the
         * cookie file is writable. If it's the second case, and it passes, then
         * a cookie file is written to the proper path.
         * 
         * @access protected
         * @return void
         */
        protected function _openCookie()
        {
            // ensure file is writable
            if (file_exists($this->_cookieStoragePath) === true) {
                if (posix_access($this->_cookieStoragePath, POSIX_W_OK) === false) {
                    throw new Exception(
                        'File *' . ($this->_cookieStoragePath) . '* must be ' .
                        'writable for cookie storage.'
                    );
                }
            }
            // ensure file directory is writable
            else {
                $dir = dirname($this->_cookieStoragePath);
                if (is_writable($dir) === false) {
                    throw new Exception(
                        'Path *' . ($dir) . '* must be writable for cookie ' .
                        'storage.'
                    );
                }

                // open file
                $resource = fopen($this->_cookieStoragePath, 'w');
                fclose($resource);
            }
        }

        /**
         * _valid
         * 
         * Ensures that a request is valid, based on the http code, mime type
         * and content length returned.
         * 
         * @access protected
         * @return Boolean whether or not the request is valid to be processed
         */
        protected function _valid()
        {
            /**
             * HTTP Status Code
             * 
             */
            $validHTTPCodes = $this->_options['validHTTPCodes'];
            if (in_array($this->_headInfo['http_code'], $validHTTPCodes) === false) {
                $this->_error = array(
                    'code' => 'CUSTOM_HTTPSTATUSCODE',
                    'message' => ($this->_headInfo['http_code']) .
                        ' status code received while trying to retrieve ' .
                        ($this->_headInfo['url'])
                );
                return false;
            }

            /**
             * Mime
             * 
             */
            $mimes = $this->getMimes();
            $pieces = explode(';', $this->_headInfo['content_type']);
            $mime = current($pieces);
            if (in_array($mime, $mimes) === false) {
                $mime = current(explode(';', $this->_headInfo['content_type']));
                $accepted = implode(', ', $this->getMimes());
                $this->_error = array(
                    'code' => 'CUSTOM_MIME',
                    'message' => 'Mime-type requirement not met. Resource is ' .
                        ($mime) . '. You were hoping for one of: ' .
                        ($accepted) . '.',
                );
                return false;
            }

            /**
             * Filesize
             * 
             */
            $maxFilesize = (int) $this->_options['maxFilesize'];
            $contentLength = (int) $this->_headInfo['download_content_length'];
            if($contentLength > $maxFilesize) {
                $this->_error = array(
                    'code' => 'CUSTOM_FILESIZE',
                    'message' => 'File size limit reached. Limit was set to ' .
                        ($maxFilesize) . '. Resource is ' . ($contentLength)
                );
                return false;
            }

            // Done
            return true;
        }

        /**
         * addMime
         * 
         * Adds a specific mime type to the acceptable range for a
         * return/response.
         * 
         * @access public
         * @param  string $mime
         * @return void
         */
        public function addMime($mime)
        {
            $this->_acceptableMimeTypes[] = $mime;
        }

        /**
         * addMimes
         * 
         * Adds passed in mime types to the array tracking which are acceptable
         * to be returned.
         * 
         * @access public
         * @return void
         */
        public function addMimes()
        {
            $args = func_get_args();
            foreach ($args as $mime) {
                $this->addMime($mime);
            }
        }

        /**
         * get
         * 
         * Returns the actual content (string), or else false if the request
         * failed.
         * 
         * @access public
         * @param  string $url
         * @return string|false
         */
        public function get($url)
        {
            // Invalid, based on a HEAD call
            if (is_null($this->_headInfo) === true) {
                $this->head($url);
            }
            if ($this->_error !== false) {
                return false;
            }
            if ($this->_valid() === false) {
                return false;
            }

            /**
             * Content Type Header
             * 
             */
            $mimes = implode(',', $this->getMimes());
            $this->setHeader('Accept', $mimes);

            /**
             * Resource and retrieval
             * 
             */
            $resource = $this->_getResource($url);
            $callback = array($this, 'writeCallback');
            curl_setopt($resource, CURLOPT_WRITEFUNCTION, $callback);

            /**
             * Execute and check for filesize limit
             * 
             */
            try {
                curl_exec($resource);
            } catch (Exception $exception) {
                $this->_error = array(
                    'code' => 'CUSTOM_FILESIZE',
                    'message' => $exception->getMessage()
                );
                return false;
            }
            $this->_response = $this->_dynamicResponse;
            $this->_info = curl_getinfo($resource);

            /**
             * Native cURL Error
             * 
             */
            if ((int) curl_errno($resource) !== 0) {
                $code = $this->_curlErrors[(int) curl_errno($resource)];
                $this->_error = array(
                    'code' => $code,
                    'message' => curl_error($resource)
                );
            }

            // Done
            $this->_close($resource);
            return $this->_response;
        }

        /**
         * getCharset
         * 
         * Requires $this->get to be called before being called.
         * 
         * @access public
         * @return string|false
         */
        public function getCharset()
        {
            $headerCharset = $this->getHeaderCharset();
            if ($headerCharset !== false) {
                return $headerCharset;
            }
            return $this->getContentCharset();
        }

        /**
         * getContentCharset
         * 
         * @note   The `url` value being used from the curler info is valid to
         *         use since it is the redirect url. For example, if a bit.ly
         *         link is specified, the `url` value being used below is not
         *         bit.ly, but rather whatever site it's being redirect to.
         * @access public
         * @return string|false
         */
        public function getContentCharset()
        {
            // dependency check
            if (class_exists('MetaParser') === false) {
                throw new Exception(
                    '*MetaParser* class required. Please see ' .
                    'https://github.com/onassar/PHP-MetaParser'
                );
            }

            // instantiate parser to get access to content's provided charset
            $info = $this->getInfo();
            $parser = new MetaParser($this->_response, $info['url']);
            return $parser->getCharset();
        }

        /**
         * getError
         * 
         * @access public
         * @return false|array
         */
        public function getError()
        {
            return $this->_error;
        }

        /**
         * getHeaderCharset
         * 
         * @access public
         * @return string|false
         */
        public function getHeaderCharset()
        {
            $info = $this->getInfo();
            $contentType = $info['content_type'];
            $pattern = '#charset=([a-zA-Z0-9-]+)#';
            preg_match($pattern, $contentType, $matches);
            if (isset($matches[1]) === true) {
                $charset = array_pop($matches);
                $charset = trim($charset);
                $charset = strtolower($charset);
                if ($charset === 'utf8') {
                    return 'utf-8';
                }
                return $charset;
            }
            return false;
        }

        /**
         * getInfo
         * 
         * Grabs the previously stored info for the curl call.
         * 
         * @access public
         * @return array
         */
        public function getInfo()
        {
            return $this->_info;
        }

        /**
         * getHeadInfo
         * 
         * @access public
         * @return array
         */
        public function getHeadInfo()
        {
            return $this->_headInfo;
        }

        /**
         * getMimes
         * 
         * Maps the mime types specified and returns them for the curl requests.
         * 
         * @access public
         * @return array mime
         */
        public function getMimes()
        {
            $mimes = array();
            foreach ($this->_mimes as $mime => $buckets) {
                $intersection = array_intersect(
                    $this->_acceptableMimeTypes,
                    $buckets
                );
                if (in_array($mime, $this->_acceptableMimeTypes) === true) {
                    array_push($mimes, $mime);
                } elseif (empty($intersection) === false) {
                    $mimes = array_merge($mimes, (array) $mime);
                }
            }
            return array_unique($mimes);
        }

        /**
         * head
         * 
         * Make a HEAD call to the passed in URI. Note that this call can fail
         * in two ways:
         * 1. Timeout is reached
         * 2. Max redirects is reached
         * 
         * @access public
         * @param  string $uri
         * @return void
         */
        public function head($uri)
        {
            $this->setHeader('Accept', '*/*');
            $resource = $this->_getResource($uri, true);
            curl_exec($resource);
            $this->_headInfo = curl_getinfo($resource);
            if ((int) curl_errno($resource) !== 0) {
                $code = $this->_curlErrors[(int) curl_errno($resource)];
                $this->_error = array(
                    'code' => $code,
                    'message' => curl_error($resource)
                );
            }
            $this->_close($resource);
        }

        /**
         * getResponse
         * 
         * Grabs the previously stored response.
         * 
         * @access public
         * @return string
         */
        public function getResponse()
        {
            return $this->_response;
        }

        /**
         * post
         * 
         * @access public
         * @param  string $url
         * @param  array $array
         * @param  boolean $buildQuery (default: true)
         * @return array|false
         */
        public function post($url, array $data = array(), $buildQuery = true)
        {
            // Invalid, based on a HEAD call
            $this->head($url);
            if ($this->_error !== false) {
                return false;
            }
            if ($this->_valid() === false) {
                return false;
            }

            /**
             * Content Type Header
             * 
             */
            $mimes = implode(',', $this->getMimes());
            $this->setHeader('Accept', $mimes);
            $resource = $this->_getResource($url);

            // Encoding and setting of data
            curl_setopt($resource, CURLOPT_POST, true);
            if ($buildQuery === true) {
                $data = http_build_query($data);
            }
            curl_setopt($resource, CURLOPT_POSTFIELDS, $data);

            // make the GET call, storing the response; store the info
            $this->_response = curl_exec($resource);
            $this->_info = curl_getinfo($resource);

            /**
             * Native cURL Error
             * 
             */
            if ((int) curl_errno($resource) !== 0) {
                $code = $this->_curlErrors[(int) curl_errno($resource)];
                $this->_error = array(
                    'code' => $code,
                    'message' => curl_error($resource)
                );
            }

            // Done
            $this->_close($resource);
            return $this->_response;
        }

        /**
         * setCurlOptions
         *
         * @access public
         * @param  array $options
         * @return void
         */
        public function setCurlOptions(array $options)
        {
            foreach ($options as $key => $value) {
                $this->_curlOptions[$key] = $value;
            }
        }

        /**
         * setHeader
         * 
         * Sets a header for the request being made.
         * 
         * @access public
         * @param  string $key
         * @param  string $value
         * @return void
         */
        public function setHeader($key, $value)
        {
            $this->_headers[$key] = $value;
        }

        /**
         * setHeaders
         * 
         * Sets a group of headers at once, for the request.
         * 
         * @access public
         * @param  array $headers
         * @return void
         */
        public function setHeaders(array $headers)
        {
            foreach ($headers as $key => $value) {
                $this->setHeader($key, $value);
            }
        }

        /**
         * setMime
         * 
         * Proxy for setMimes.
         * 
         * @access public
         * @param  string $mime
         * @return void
         */
        public function setMime($mime)
        {
            $this->setMimes($mime);
        }

        /**
         * setMimes
         * 
         * Stores which mime types can be accepted in the request.
         * 
         * @notes  if false specified (such as setMime(false) or
         *         setMimes(false)), then no mimes are set as being allowed (eg.
         *         good for clearing out any previously set acceptable
         *         mime-types)
         * @access public
         * @return void
         */
        public function setMimes()
        {
            $args = func_get_args();
            $this->_acceptableMimeTypes = array();
            if (in_array(false, $args) === false) {
                $this->_acceptableMimeTypes = $args;
            }
        }

        /**
         * setCookieStoragePath
         * 
         * Set path to file for curl to store cookies. File must be writable.
         * 
         * @param  string $path
         * @return void
         */
        public function setCookieStoragePath($path)
        {
            $this->_cookieStoragePath = $path;
        }

        /**
         * writeCallback
         * 
         * Helper method to ensure that the filesize limt is not reached. Needs
         * to return the number of bytes written, otherwise the transfer will
         * fail.
         * 
         * @access public
         * @param  Object $resource
         * @param  string $data
         * @return int
         */
        public function writeCallback($resource, $data)
        {
            $this->_dynamicResponse .= $data;
            $maxFilesize = $this->_options['maxFilesize'];
            if (strlen($this->_dynamicResponse) > $maxFilesize) {
                throw new Exception('Size exceeded', 1);
            }
            return strlen($data);
        }
    }
