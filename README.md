# :zap: Simple and Fast Domain Lookup in PHP :zap:

This PHP package enables developers to retrieve domain registration information and check domain availability via socket
protocol. It's a useful tool for web developers and domain name registrars.

based on WHMCS Whois class.

## :scroll: Installation

You can install the package via composer:

```bash
composer require monovm/whois-php
```

## :arrow_forward: Checker class

You can use this class to check one or multiple domains availability.

## :mortar_board: Usage/Examples

```PHP
use MonoVM\WhoisPhp\Checker;

// Single Domain whois
$result1 = Checker::whois('monovm.com');

// Single Domain whois without specifying TLD
$result2 = Checker::whois('monovm');

// Multiple domains whois
$result3 = Checker::whois(['monovm','google.com','bing']);
```

- ### Response

An associative array with domains as keys and status as values.

```code
$result1: ['monovm.com'=>'unavailable']
$result2: ['monovm.com'=>'unavailable','monovm.net'=>'unavailable','monovm.org'=>'unavailable','monovm.info'=>'unavailable']
$result3: [
            'monovm.com'=>'unavailable',
            'monovm.net'=>'unavailable',
            'monovm.org'=>'unavailable',
            'monovm.info'=>'unavailable',
            'google.com'=>'unavailable',
            'bing.com'=>'unavailable',
            'bing.net'=>'unavailable',
            'bing.org'=>'unavailable',
            'bing.info'=>'unavailable'
           ]
```

### :fire: popularTLDs Configuration

When TLD is not specified in the domain string (eg:monovm instead of monovm.com), Checker class will automatically
lookup a list of popular TLDs for the entered name.

You can customize this list by passing an options array as second parameter of whois method.

```PHP
use MonoVM\WhoisPhp\Checker;

$result = Checker::whois('monovm', ['popularTLDs' => ['.com', '.net', '.org', '.info']]);
```



## :arrow_forward: WhoisHandler class

## :mortar_board: Usage/Examples

```PHP
use MonoVM\WhoisPhp\WhoisHandler;

$whoisHandler = WhoisHandler::whois('monovm.com');
```

#### Available methods:

**After initiating the handler method you will have access to the following methods:**

| Method        | Description                                                                                                      |
|---------------|------------------------------------------------------------------------------------------------------------------|
| `isAvailable` | Returns true if the domain is available for registration                                                         |
| `isValid`     | Returns true if the domain can be looked up                                                                      |
| `getWhoisMessage` | Returns the whois server message as a string including availability, validation or the domain whois information  |
| `getTld`      | Returns the top level domain of the entered domain as a string                                                   |
| `getSld`      | Returns the second level domain of the entered domain as a string                                                |

:green_circle: `isAvailable()` method takes a single parameter, the domain name, as a string value. If the domain is
available for registration, the method returns a boolean true value. If the domain is already registered, the method
returns a boolean false value.

```PHP
$available = $whoisHandler->isAvailable();
```

:green_circle: `isValid()` method checks whether the entered domain name is valid and can be looked up or not.

```PHP
$valid = $whoisHandler->isValid();
```

:green_circle: The `getWhoisMessage()` method is used to retrieve the WHOIS information of a domain. This method returns
a string that includes the WHOIS server message, which may contain information about the availability and validation of
the domain, as well as its WHOIS information.

```PHP
$message = $whoisHandler->getWhoisMessage();
```

:green_circle: `getTld()` method is used to extract the top level domain (TLD) of a given domain. For example, if the
domain name passed to the handler is "monovm.com", the method will return "com" as the TLD. Similarly, if the domain
name is "monovm.co.uk", the method will return "co.uk" as the TLD.

```PHP
$tld = $whoisHandler->getTld();
```

:green_circle: `getSld()` method returns the second level domain of the entered domain as a string. The second level
domain is the part of the domain name that comes before the top level domain, and it is typically the main part of the
domain that identifies the website or organization. For example, in the domain name "monovm.com", the second level
domain is "monovm".

```PHP
$sld = $whoisHandler->getSld();
```

## :globe_with_meridians: Whois Server List

| TLD                                                                                                                                                                                                                                                                                                                                                                               | WHOIS Server                     |
|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------|
| .com, .net, .es, .com.es, .nom.es, .gob.es, .edu.es                                                                                                                                                                                                                                                                                                                               | whois.crsnic.net                 |
| .org, .ngo, .ong                                                                                                                                                                                                                                                                                                                                                                  | whois.publicinterestregistry.net |
| .uk, .co.uk, .net.uk, .org.uk, .ltd.uk, .plc.uk, .me.uk                                                                                                                                                                                                                                                                                                                           | whois.nic.uk                     |
| .edu, .mil                                                                                                                                                                                                                                                                                                                                                                        | whois.internic.net               |
| .br.com, .cn.com, .eu.com, .no.com, .qc.com, .sa.com, .se.com, .se.net, .us.com, .uy.com, .za.com, .uk.com, .uk.net, .gb.com, .gb.net, .online,.site                                                                                                                                                                                                                              | whois.centralnic.com             |
| .ink                                                                                                                                                                                                                                                                                                                                                                              | whois.nic.ink                    |
| .com.de                                                                                                                                                                                                                                                                                                                                                                           | whois.centralnic.com             |
| .ac, .co.ac                                                                                                                                                                                                                                                                                                                                                                       | whois.nic.ac                     |
| .af                                                                                                                                                                                                                                                                                                                                                                               | whois.nic.af                     |
| .am                                                                                                                                                                                                                                                                                                                                                                               | whois.amnic.net                  |
| .as                                                                                                                                                                                                                                                                                                                                                                               | whois.nic.as                     |
| .at, .ac.at, .co.at, .gv.at, .or.at                                                                                                                                                                                                                                                                                                                                               | whois.nic.at                     |
| .asn.au, .com.au, .edu.au, .org.au, .net.au, .id.au                                                                                                                                                                                                                                                                                                                               | domaincheck.auda.org.au          |
| .be, .ac.be                                                                                                                                                                                                                                                                                                                                                                       | whois.dns.be                     |
| .br, .adm.br, .adv.br, .am.br, .arq.br, .art.br, .bio.br, .cng.br, .cnt.br, .com.br, .ecn.br, .eng.br, .esp.br, .etc.br, .eti.br, .fm.br, .fot.br, .fst.br, .g12.br, .gov.br, .ind.br, .inf.br, .jor.br, .lel.br, .med.br, .mil.br, .net.br, .nom.br, .ntr.br, .odo.br, .org.br, .ppg.br, .pro.br, .psc.br, .psi.br, .rec.br, .slg.br, .tmp.br, .tur.br, .tv.br, .vet.br, .zlg.br | whois.nic.br                     |
| .ca                                                                                                                                                                                                                                                                                                                                                                               | whois.cira.ca                    |
| .cc                                                                                                                                                                                                                                                                                                                                                                               | whois.nic.cc                     |

## :balance_scale: License

[MIT](https://choosealicense.com/licenses/mit/)

## :computer: Support

For support, email dev@monovm.com.

[MonoVM.com](https://monovm.com)

![Logo](https://monovm.com/site-assets/images/logo-monovm.svg)

