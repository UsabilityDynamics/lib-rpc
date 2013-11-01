<?php

/**
 * UD XML-RPC Library
 */
namespace UsabilityDynamics {

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
    function __construct($server, $path = false, $port = 80, $timeout = 15) {
      parent::__construct( $server, $path, $port, $timeout );

      /**
       * Custom useragent
       */
      $this->useragent = 'UD XML-RPC Client';

      /**
       * Custom headers
       */
      $this->headers = array();

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
     * Construct
     * @param type $namespace
     */
    function __construct($namespace = 'ud') {

      $this->namespace = $namespace;

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

      $call = $this->get_called_method();

      if (method_exists($this, $call)) {
        $status = call_user_func_array(array($this, $call), $args);

        return $status;
      } else {
        return "Method not allowed";
      }
    }

    /**
     * Get method that was actually called to find it in child class
     * @global $wp_xmlrpc_server
     * @return type
     */
    private function get_called_method() {
      global $wp_xmlrpc_server;

      $call = $wp_xmlrpc_server->message->methodName;
      $pieces = explode(".", $call);

      return $pieces[1];
    }

  }

  /**
   * UD XML-RPC Server Library
   * @author korotkov@ud
   */
  class UD_XMLRPC extends XMLRPC {

    /**
     *
     *
     * Example call:
     *
     * <?php
     *
     * include_once( ABSPATH . WPINC . '/class-IXR.php' );
     * include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );

     * $client = new WP_HTTP_IXR_CLIENT( 'http://domain.name/xmlrpc.php' );

     * $client->query( 'ud.ping', array() );

     * echo '<pre>';
     * print_r( $client->getResponse() );
     * echo '</pre>';
     *
     * ?>
     *
     *
     * @return type
     */
    public function ping() {
      return $_SERVER;
    }

    /**
     *
     *
     * Example call:
     *
     * <?php
     *
     * include_once( ABSPATH . WPINC . '/class-IXR.php' );
     * include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );

     * $client = new WP_HTTP_IXR_CLIENT( 'http://domain.name/xmlrpc.php' );

     * $client->query( 'ud.say_hello', array('John', 'Smith') );

     * echo '<pre>';
     * print_r( $client->getResponse() );
     * echo '</pre>';
     *
     * ?>
     *
     * @param type $first_name
     * @param type $last_name
     * @return type
     */
    public function say_hello($first_name, $last_name) {
      return 'Hello ' . $first_name . ' ' . $last_name;
    }

  }

}