<?php

/**
 *
 */

namespace UsabilityDynamics {

  /**
   *
   */
  abstract class XMLRPC {

    /**
     *
     * @var type
     */
    protected $calls = Array();

    /**
     *
     * @var type
     */
    protected $namespace;

    /**
     *
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
     *
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
     *
     * @global type $wp_xmlrpc_server
     * @param type $args
     * @return string
     */
    public function dispatch( $args ) {

      $call = $this->get_called_method();

      if ( method_exists( $this, $call ) ) {
        $status = call_user_func_array( array( $this, $call ), $args );
        return $status;
      } else {
        return "Method not allowed";
      }
    }

    /**
     *
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
    public function say_hello( $first_name, $last_name ) {
      return 'Hello '. $first_name . ' ' . $last_name;
    }

  }

}