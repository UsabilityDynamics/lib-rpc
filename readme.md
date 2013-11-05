XML-RPC Library that allows user sites to communicate with UD Services.

### Draft

## Initialization

```php
require_once PATH_TO_LIB.'/lib/wp-xmlrpc.php';
use UsabilityDynamics\UD_XMLRPC;
new UD_XMLRPC('encription key string');
```

## Sending Requests

```php
require_once PATH_TO_LIB.'/lib/wp-xmlrpc.php';
use UsabilityDynamics\UD_IXR_Client;
$client = new UD_IXR_Client( 'http://wpi.loc/xmlrpc.php', 'secret key', 'public key', 'WordPress 3.7.1; WP-Invoice 3.09.1;' );
$client->query( 'ud.test', array('arg1', 'arg2', 'argX') );
$result = $client->getResponse();
```

## Currently available methods

* ud.validate( string ) - Validate requests using this function as a callback. Argument is md5 string of host+public_key+secret_key.
* ud.test(...)
