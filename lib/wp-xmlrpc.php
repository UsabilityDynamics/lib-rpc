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
    protected $namespace = "ud";

    /**
     *
     * @param type $namespace
     */
    function __construct($namespace) {

      $this->namespace = $namespace;

      $reflector = new ReflectionClass($this);

      foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
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

    public function _ping() {
      return $_SERVER;
    }

    public function _validate_domain() {}

    public function _validate_ip() {}

    public function _generate_api_key() {}

  }

}