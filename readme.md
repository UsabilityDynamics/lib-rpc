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
$client = new UD_IXR_Client( 'http://domain.name/xmlrpc.php', 'encription key string' );
$client->query( 'ud.test', array('arg1', 'arg2', 'argX') );
$result = $client->getResponse();
```

## Currently available methods

* ud.ping()
* ud.test(...)
