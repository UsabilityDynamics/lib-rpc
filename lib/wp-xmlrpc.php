<?php

/**
 * UD XML-RPC Library
 */
namespace UsabilityDynamics {

  /**
   * Check if WordPress environment is loaded since lib is WordPress dependent.
   */
  if ( defined('ABSPATH') ) {

    /**
     * Standard IXR
     */
    include_once( ABSPATH . WPINC . '/class-IXR.php' );

    /**
     * UD IXR extended from standard
     * @author korotkov@ud
     */
    class UD_IXR_Client extends \IXR_Client {

      /**
       * Construct
       * @param type $server
       * @param type $path
       * @param type $port
       * @param type $timeout
       * @author korotkov@ud
       */
      function __construct($server, $secret_key, $public_key, $useragent = 'UD XML-RPC Client', $headers = array(), $path = false, $port = 80, $timeout = 15, $debug = false) {
        parent::__construct( $server, $path, $port, $timeout );

        /**
         * Basic Authorization Header
         */
        $headers['Authorization'] = 'Basic '.base64_encode($public_key);

        /**
         * Set Callback URL Header
         */
        $headers['Callback-URL'] = get_bloginfo( 'pingback_url' );

        /**
         * Encryption key
         */
        $this->ecryption_key = $secret_key;

        /**
         * Custom useragent
         */
        $this->useragent = $useragent;

        /**
         * Custom headers
         */
        $this->headers = $headers;

        /**
         * Enable debug
         */
        $this->debug = $debug;

      }

      /**
       * Send a query to server
       * @return boolean
       */
      function query() {

        /**
         * All request args
         */
        $args = func_get_args();

        /**
         * Get method stored on first place
         */
        $method = array_shift($args);

        /**
         * Hash request data
         */
        $secure_args = array(
          array(
            $this->_hash_args($args[0])
          )
        );

        /**
         * Build request
         */
        $request = new \IXR_Request($method, $secure_args);
        $length = $request->getLength();
        $xml = $request->getXml();
        $r = "\r\n";
        $request = "POST {$this->path} HTTP/1.0$r";
        $this->headers['Host'] = $this->server;
        $this->headers['Content-Type'] = 'text/xml';
        $this->headers['User-Agent'] = $this->useragent;
        $this->headers['Content-Length'] = $length;
        foreach ($this->headers as $header => $value) {
          $request .= "{$header}: {$value}{$r}";
        }
        $request .= $r;
        $request .= $xml;

        /**
         * Debug step
         */
        if ($this->debug) {
          echo '<pre class="ixr_request">' . htmlspecialchars($request) . "\n</pre>\n\n";
        }

        /**
         * Send
         */
        if ($this->timeout) {
          $fp = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
        } else {
          $fp = @fsockopen($this->server, $this->port, $errno, $errstr);
        }
        if (!$fp) {
          $this->error = new \IXR_Error(-32300, 'transport error - could not open socket');
          return false;
        }
        fputs($fp, $request);
        $contents = '';
        $debugContents = '';
        $gotFirstLine = false;
        $gettingHeaders = true;
        while (!feof($fp)) {
          $line = fgets($fp, 4096);
          if (!$gotFirstLine) {
            if (strstr($line, '200') === false) {
              $this->error = new \IXR_Error(-32300, 'transport error - HTTP status code was not 200');
              return false;
            }
            $gotFirstLine = true;
          }
          if (trim($line) == '') {
            $gettingHeaders = false;
          }
          if (!$gettingHeaders) {
            $contents .= $line;
          }
          if ($this->debug) {
            $debugContents .= $line;
          }
        }
        if ($this->debug) {
          echo '<pre class="ixr_response">' . htmlspecialchars($debugContents) . "\n</pre>\n\n";
        }

        /**
         * Now parse what we've got back
         */
        $this->message = new \IXR_Message($contents);
        if ( !$this->message->parse() ) {
          /**
           * XML is bad
           */
          $this->error = new \IXR_Error(-32700, 'parse error. not well formed');
          return false;
        }

        /**
         * Is the message a fault?
         */
        if ($this->message->messageType == 'fault') {
          $this->error = new \IXR_Error($this->message->faultCode, $this->message->faultString);
          return false;
        }

        return true;
      }

      /**
       * Secure arguments
       * @param type $args
       * @return type
       */
      private function _hash_args( $args ) {
        return trim(
          base64_encode(
            mcrypt_encrypt(
              MCRYPT_RIJNDAEL_256,
              md5( $this->ecryption_key ),
              json_encode( $args ),
              MCRYPT_MODE_ECB,
              mcrypt_create_iv(
                mcrypt_get_iv_size(
                  MCRYPT_RIJNDAEL_256,
                  MCRYPT_MODE_ECB
                ),
                MCRYPT_RAND
              )
            )
          )
        );
      }

    }

    /**
     * Base XML-RPC handler
     * @author korotkov@ud
     */
    abstract class XMLRPC {

      /**
       * Available calls
       * @var type
       */
      protected $calls = Array();

      /**
       * Current methods' namespace
       * @var type
       */
      protected $namespace;

      /**
       * Secret key
       * @var type
       */
      public $secret_key;

      /**
       * Public Key
       * @var type
       */
      public $public_key;

      /**
       * Construct
       * @param type $namespace
       */
      function __construct( $secret_key, $public_key, $namespace = 'ud' ) {

        $this->namespace  = $namespace;
        $this->secret_key = $secret_key;
        $this->public_key = $public_key;

        $reflector = new \ReflectionClass($this);

        foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
          if ($method->isUserDefined() && $method->getDeclaringClass()->name != get_class()) {
            $this->calls[] = $method->name;
          }
        }

        add_filter('xmlrpc_methods', array($this, 'xmlrpc_methods'));
      }

      /**
       * Register methods
       * @param type $methods
       * @return array
       */
      public function xmlrpc_methods($methods) {
        foreach ($this->calls as $call) {
          $methods[$this->namespace . "." . $call] = array($this, "dispatch");
        }
        return $methods;
      }

      /**
       * Call methods (__call similar)
       * @global type $wp_xmlrpc_server
       * @param type $args
       * @return string
       */
      public function dispatch($args) {

        $call = $this->_get_called_method();

        if (method_exists($this, $call)) {
          if ( $this->_read_args( $args ) ) {
            $status = call_user_func_array(array($this, $call), array( $args ));
            return $status;
          } else {
            return "Unauthorized";
          }
        } else {
          return "Method not allowed";
        }
      }

      /**
       * Get method that was actually called to find it in child class
       * @global $wp_xmlrpc_server
       * @return type
       */
      private function _get_called_method() {
        global $wp_xmlrpc_server;

        $call = $wp_xmlrpc_server->message->methodName;
        $pieces = explode(".", $call);

        return $pieces[1];
      }

      /**
       * Decrypt args
       * @param type $args
       */
      private function _read_args( &$args ) {
        $args = json_decode(
          trim(
            mcrypt_decrypt(
              MCRYPT_RIJNDAEL_256,
              md5( $this->secret_key ),
              base64_decode($args[0]),
              MCRYPT_MODE_ECB,
              mcrypt_create_iv(
                mcrypt_get_iv_size(
                  MCRYPT_RIJNDAEL_256,
                  MCRYPT_MODE_ECB
                ),
                MCRYPT_RAND
              )
            )
          )
        );
        if ( is_array( $args ) && !empty( $args ) ) {
          return true;
        }
        return false;
      }
    }

    /**
     * UD XML-RPC Server Library. May be extended with a class with public methods.
     * @author korotkov@ud
     */
    class UD_XMLRPC extends XMLRPC {

      /**
       * Validate all incoming requests using callback to this method.
       *
       * @param md5 string $request_data
       * @return boolean
       */
      public function validate( $request_data ) {
        if ( md5( $_SERVER['HTTP_HOST'].$this->public_key.$this->secret_key ) == $request_data[0] ) { return true; }
        return false;
      }

      /**
       * Test call to check if request is correct. Sent data will be returned in decrypted state.
       * @return mixed
       */
      public function test( $request_data ) {
        return $request_data;
      }

    }

  }

}