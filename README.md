
# :zap: Simple and Fast Domain Lookup in PHP :zap:

This PHP package enables developers to retrieve domain registration information and check domain availability via socket protocol. It's a useful tool for web developers and domain name registrars.




## :scroll: Installation

You can install the package via composer:

```bash
composer require monovm/whois-php
```

## :mortar_board: Usage/Examples

```PHP
use MonoVM\WhoisPhp\WhoisHandler;

$whoisHandler = WhoisHandler::whois('monovm.com')
```

#### Available methods:
**After initiating the handler method you will have access to the following methods:**

`isAvailable()` method takes a single parameter, the domain name, as a string value. If the domain is available for registration, the method returns a boolean true value. If the domain is already registered, the method returns a boolean false value.
```PHP
$available = $whoisHandler->isAvailable();
```


`isValid()` method checks whether the entered domain name is valid and can be looked up or not.
```PHP
$valid = $whoisHandler->isValid();
```

The `getWhoisMessage()` method is used to retrieve the WHOIS information of a domain. This method returns a string that includes the WHOIS server message, which may contain information about the availability and validation of the domain, as well as its WHOIS information.
```PHP
$message = $whoisHandler->getWhoisMessage();
```

`getTld()` method is used to extract the top level domain (TLD) of a given domain.
For example, if the domain name passed to the handler is "monovm.com", the method will return "com" as the TLD. Similarly, if the domain name is "monovm.co.uk", the method will return "co.uk" as the TLD.
```PHP
$tld = $whoisHandler->getTld();
```

`getSld()` method returns the second level domain of the entered domain as a string. The second level domain is the part of the domain name that comes before the top level domain, and it is typically the main part of the domain that identifies the website or organization. For example, in the domain name "monovm.com", the second level domain is "monovm".
```PHP
$sld = $whoisHandler->getSld();
```
## :balance_scale: License

[MIT](https://choosealicense.com/licenses/mit/)


## :computer: Support

For support, email dev@monovm.com.

