XML-RPC Library that allows user sites to communicate with UD Services.

### Draft

## Initialization

    require_once PATH_TO_LIB.'/lib/wp-xmlrpc.php';
    use UsabilityDynamics\UD_XMLRPC;
    new UD_XMLRPC();

## Sending Requests

    require_once PATH_TO_LIB.'/lib/wp-xmlrpc.php';
    use UsabilityDynamics\UD_IXR_Client;
    $client = new UD_IXR_Client( 'http://domain.name/xmlrpc.php' );
    $client->query( 'ud.ping', array() );
    $result = $client->getResponse();

## Currently available methods

* ud.ping
* ud.say_hello(first_name, last_name)