<?php
/**
 * XML-RPC Library
 *
 *
 */
namespace UsabilityDynamics {

  /**
   * Check if WordPress environment is loaded since lib is WordPress dependent.
   */
  if( defined( 'ABSPATH' ) ) {

    /**
     * Standard IXR
     */
    include_once( ABSPATH . WPINC . '/class-IXR.php' );

    /**
     * Prevent class redeclaration since lib will be included into each product.
     */
    if( !class_exists( 'UsabilityDynamics\UD_IXR_Client' ) && class_exists( '\IXR_Client' ) ) {
      /**
       * UD IXR extended from standard
       *
       * @author korotkov@ud
       */
      class UD_IXR_Client extends \IXR_Client {

        /**
         * Construct
         *
         * @param type                         $server
         * @param                              $public_key
         * @param bool                         $secret_key
         * @param string                       $useragent
         * @param array                        $headers
         * @param bool|\UsabilityDynamics\type $path
         * @param int|\UsabilityDynamics\type  $port
         * @param int|\UsabilityDynamics\type  $timeout
         * @param bool                         $debug
         *
         * @author korotkov@ud
         */
        function __construct( $server, $public_key, $useragent = 'UD XML-RPC-SAAS Client', $headers = array(), $path = false, $port = 80, $timeout = 15, $debug = false ) {
          /**
           * No go w/o PK
           */
          if( empty( $public_key ) ) return false;

          $raas_user = get_user_by( 'login', 'raas@'.$_SERVER['HTTP_HOST'] );

          /**
           * IMPORTANT
           */
          parent::__construct( $server, $path, $port, $timeout );

          /**
           * Basic Authorization Header
           */
          $headers[ 'Authorization' ] = 'Basic ' . base64_encode( $public_key . ":" . get_user_meta( $raas_user->ID, md5( 'raas_secret' ) , 1) );

          /**
           * Connection
           */
          $headers[ 'Connection' ] = 'keep-alive';

          /**
           * Set Callback URL Header
           */
          $headers[ 'X-Callback' ] = get_bloginfo( 'pingback_url' );

          /**
           * Set Sourse host
           */
          $headers[ 'X-Source-Host' ] = $_SERVER[ 'HTTP_HOST' ];

          /**
           * Remember PK
           */
          $this->public_key = $public_key;

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
      }
    }

    /**
     * Prevent class redeclaration
     */
    if( !class_exists( 'UsabilityDynamics\XMLRPC_CLIENT' ) && class_exists( 'UsabilityDynamics\UD_IXR_Client' ) ) {

      /**
       * Client methods
       */
      class XMLRPC_CLIENT extends UD_IXR_Client {

        /**
         * Initial handshake
         */
        public function register() {

          if ( !is_a( $user_object = get_user_by( 'login', 'raas@'.$_SERVER['HTTP_HOST'] ), 'WP_User' ) ) {
            $user_id = wp_insert_user( array(
                'user_login' => 'raas@'.$_SERVER['HTTP_HOST'],
                'user_pass' => $secret = $this->_generate_secret(),
                'role' => 'administrator'
            ) );
            add_user_meta( $user_id, md5( 'raas_secret' ), $secret );
          }

          $this->query( 'account.validate' );

          return $this->getResponse();

        }

        /**
         *
         */
        private function _generate_secret( $length = 20 ) {
          $chars = 'abcdefghijklmnopqrstuvwxyz';

          $password = '';
          for ( $i = 0; $i < $length; $i++ ) {
            $password .= substr($chars, wp_rand(0, strlen($chars) - 1), 1);
          }

          return $password;
        }

      }

    }

    /**
     * Prevent class redeclaration
     */
    if( !class_exists( 'UsabilityDynamics\XMLRPC' ) ) {
      /**
       * Base XML-RPC handler
       *
       * @author korotkov@ud
       */
      abstract class XMLRPC {

        /**
         * End point for client
         *
         * @var type
         */
        public $endpoint;

        /**
         * Available calls
         *
         * @var type
         */
        private $calls = Array();

        /**
         * Current methods' namespace
         *
         * @var type
         */
        public $namespace;

        /**
         * Root namespace for ALL methods. For WordPress is 'wp'.
         *
         * @var type
         */
        protected $root_namespace = 'wp';

        /**
         * Secret key
         *
         * @var type
         */
        public $secret_key;

        /**
         * Public Key
         *
         * @var type
         */
        public $public_key;

        /**
         * UI Object
         *
         * @var type
         */
        public $ui;

        /**
         *
         * @var type
         */
        public $useragent;

        /**
         * Construct
         *
         * @param type $namespace
         */
        function __construct( $server, $public_key, $useragent = 'UD XML-RPC SAAS Client', $namespace = 'ud' ) {
          //** End point */
          $this->endpoint = $server;

          //** Set namespace */
          $this->namespace = $namespace;

          //** Set public key */
          $this->public_key = $public_key;

          //** */
          $this->useragent = $useragent;

          //** Abort if no end point set */
          if( empty( $server ) ) return false;

          //** Init UI Object in any case */
          $this->ui = new API_UI( $this );

          //** Abort if no public key passed */
          if( empty( $public_key ) ) return false;

          //** Find all public methods in child classes and make them to be callable via XML-RPC */
          $reflector = new \ReflectionClass( $this );
          foreach( $reflector->getMethods( \ReflectionMethod::IS_PUBLIC ) as $method ) {
            if( $method->isUserDefined() && $method->getDeclaringClass()->name != get_class() ) {
              $this->calls[ ] = $method;
            }
          }

          //** Add methods to XML-RPC */
          add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
        }

        /**
         * Register methods
         *
         * @param type $methods
         *
         * @return array
         */
        public function xmlrpc_methods( $methods ) {
          foreach( $this->calls as $call ) {
            //** Check if need multiple namespaces */
            $namespace = $call->getDeclaringClass()->name != 'UsabilityDynamics\UD_XMLRPC' ? $this->namespace . '.' : '';

            if( !empty( $namespace ) ) continue;

            //** Register ALL to point to dispatch */
            $methods[ $this->root_namespace . '.' . $namespace . $call->name ] = array( $this, "dispatch" );
          }

          return $methods;
        }

        /**
         * Call methods (__call similar)
         *
         * @global type $wp_xmlrpc_server
         *
         * @param type  $args
         *
         * @return string
         */
        public function dispatch( $args ) {
          //** Get method that is currently called */
          $call = $this->_get_called_method();

          //** Method should exist */
          if( method_exists( $this, $call ) ) {
            return call_user_func_array( array( $this, $call ), array( $args ) );
          } else {
            //** If method not found */
            return "Method not allowed";
          }
        }

        /**
         * Get method that was actually called to find it in child class
         *
         * @global $wp_xmlrpc_server
         * @return type
         */
        private function _get_called_method() {
          global $wp_xmlrpc_server;

          $call   = $wp_xmlrpc_server->message->methodName;
          $pieces = explode( ".", $call );

          //** Return last piece since there may be some namespaces */
          return array_pop( $pieces );
        }
      }
    }

    /**
     * Prevent class redeclaration
     */
    if( !class_exists( 'UsabilityDynamics\UD_XMLRPC' ) && class_exists( 'UsabilityDynamics\XMLRPC' ) ) {
      /**
       * UD XML-RPC Server Library. May be extended with a class with public methods.
       *
       * @author korotkov@ud
       */
      class UD_XMLRPC extends XMLRPC {

        /**
         * Validate all incoming requests using callback to this method.
         *
         * @param md5 string $request_data
         *
         * @return boolean
         */
        public function validate( $request_data ) {
          if( md5( $_SERVER[ 'HTTP_HOST' ] . $this->public_key ) == $request_data[ 0 ] ) {
            return true;
          }

          return false;
        }

        /**
         * Notificator
         *
         * @param type $request_data
         */
        public function notify( $request_data ) {
          /**
           * @todo: Implement
           */
          return array( 'Notification received' );
        }

        /**
         * Test call to check if request is correct. Sent data will be returned in decrypted state.
         *
         * @return mixed
         */
        public function test( $request_data ) {
          return $request_data;
        }

        public function register( $request_data ) {
          return $request_data;
        }

      }
    }

    /**
     * Prevent class redeclaration
     */
    if( !class_exists( 'UsabilityDynamics\UD_PRODUCTS_XMLRPC' ) && class_exists( 'UsabilityDynamics\UD_XMLRPC' ) ) {
      /**
       * WP UD Products should initialize this server to listen for incoming commands connected to premium features management
       */
      class UD_PRODUCTS_XMLRPC extends UD_XMLRPC {

        /**
         * Add Premium Feature to the client's site
         *
         * @param type $request_data
         */
        public function add_feature( $request_data ) {
          /**
           * @todo: implement
           */
          return $this->namespace;
        }

        /**
         * Update Premium Feature on client's site
         *
         * @param type $request_data
         */
        public function update_feature( $request_data ) {
          /**
           * @todo: implement
           */
          return $this->namespace;
        }

        /**
         * Delete Premium Feature from client's site
         *
         * @param type $request_data
         */
        public function delete_feature( $request_data ) {
          /**
           * @todo: implement
           */
          return $this->namespace;
        }

      }
    }

    /**
     * Prevent class redeclaration
     */
    if( !class_exists( 'UsabilityDynamics\API_UI' ) ) {
      /**
       * Class that is responsible for API UI
       */
      class API_UI {

        /**
         * End point
         *
         * @var type
         */
        private $server;

        /**
         * Construct
         *
         * @param type $namespace
         */
        function __construct( $parent_object ) {
          $this->server = $parent_object;
          add_action( 'wp_ajax_' . $this->server->namespace . '_ud_api_save_keys', array( $this, 'ud_api_save_keys' ) );
        }

        /**
         * Use this for rendering API Keys fields anywhere.
         *
         * @param type $args
         *
         * @return type
         */
        function render_api_fields( $args = array() ) {

          wp_enqueue_script( 'jquery' );

          $defaults = array(
            'return'              => false,
            'input_class'         => 'ud_api_input',
            'container'           => 'div',
            'container_class'     => 'ud_api_credentials',
            'input_wrapper'       => 'div',
            'input_wrapper_class' => 'ud_api_field',
            'public_key_label'    => 'Public Key',
            'before'              => '',
            'after'               => ''
          );

          extract( wp_parse_args( $args, $defaults ) );

          ob_start();
          echo $before;
          ?>

          <script type="text/javascript">
            jQuery( document ).ready( function() {
              jQuery( '.up_api_keys .ud_api_keys_save' ).on( 'click', function( e ) {
                jQuery( '.up_api_keys .ud_api_message' ).empty();

                var data = {
                  action: '<?php echo $this->server->namespace ?>_ud_api_save_keys',
                  <?php echo $this->server->namespace ?>_api_public_key: jQuery( '[name="<?php echo $this->server->namespace ?>_api_public_key"]' ).val()
                };

                jQuery.ajax( ajaxurl, {
                  dataType: 'json',
                  type: 'post',
                  data: data,
                  success: function( data ) {
                    jQuery.each( data.message, function( key, value ) {
                      jQuery( '.up_api_keys .ud_api_message' ).append( '<p>' + value + '</p>' );
                    } );
                  }
                } );
              } );
            } );
          </script>

          <<?php echo $container; ?> class="<?php echo $container_class; ?> up_api_keys">

          <<?php echo $input_wrapper; ?> class="<?php echo $input_wrapper_class; ?>">
          <label for="<?php echo $this->server->namespace ?>_api_public_key"><?php echo $public_key_label; ?></label>
          <input id="<?php echo $this->server->namespace ?>_api_public_key" value="<?php echo get_option( $this->server->namespace . '_api_public_key', '' ); ?>" name="<?php echo $this->server->namespace ?>_api_public_key"/>
          </<?php echo $input_wrapper; ?>>

          <input class="ud_api_keys_save" type="button" value="Save"/>

          <span class="ud_api_message"></span>

          </<?php echo $container; ?>>

          <?php
          echo $after;
          $html = apply_filters( $this->server->namespace . '_ud_api_ui', ob_get_clean() );

          if( $return ) return $html;
          echo $html;
        }

        /**
         * Save API keys
         */
        function ud_api_save_keys() {
          $result  = array();
          $success = false;

          //** Save option for current namespace */
          if( update_option( $this->server->namespace . '_api_public_key', $_POST[ $this->server->namespace . '_api_public_key' ] ) ) {
            $result[ ] = 'Public Key has been updated.';
            $success   = true;
          }

          //** Meant if is not registered yet */
          if( 1 ) {
            $c = new XMLRPC_CLIENT(
              $this->server->endpoint,
              get_option( $this->server->namespace . '_api_public_key' ),
              $this->server->useragent, array(), false, 80, 15, true
            );

            /**
             * @todo: Need to get secret key from the server using 'register' method.
             * Then save it in {namespace}._api_public_key option and remember that site was already registered.
             */
            $registered = $c->register();
            /**
             * @todo
             */
          }

          if( empty( $result ) ) {
            $result[ ] = 'Nothing has been updated.';
          }

          die( json_encode( array(
            'success' => $success,
            'message' => $result
          ) ) );
        }
      }
    }
  }

  //** Useful wrappers */
  function get_option( $a ) {
    return base64_decode( \get_option( md5( $a ) ) );
  }

  function update_option( $a, $b ) {
    return \update_option( md5( $a ), base64_encode( $b ) );
  }

  function delete_option( $a ) {
    return \delete_option( md5( $a ) );
  }

}