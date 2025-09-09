# :zap: Simple and Fast Domain Whois Lookup in PHP :zap:

Simple and Fast Domain Whois Lookup in PHP

This PHP whois package enables developers to retrieve domain registration information and check domain availability via socket
protocol. It's a useful tool for web developers and domain name registrars.

based on WHMCS domain Whois class.

## :scroll: Installation

You can install the whois package via composer:

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
Current statuses: available, unavailable, premium

```code
$result1: ['monovm.com'=>'unavailable']
$result2: ['monovm.com'=>'unavailable','monovm.net'=>'unavailable','monovm.org'=>'unavailable','monovm.info'=>'unavailable']
$result3: [
            'monovm.com'=>'premium',
            'monovm.net'=>'unavailable',
            'monovm.org'=>'unavailable',
            'monovm.info'=>'unavailable',
            'google.com'=>'premium',
            'bing.com'=>'unavailable',
            'bing.net'=>'unavailable',
            'bing.org'=>'available',
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
| `isAvailable` | Returns true if the domain is available for registration (uses enhanced detection)                               |
| `isValid`     | Returns true if the domain can be looked up                                                                      |
| `getWhoisMessage` | Returns the whois server message as a string including availability, validation or the domain whois information  |
| `getTld`      | Returns the top level domain of the entered domain as a string                                                   |
| `getSld`      | Returns the second level domain of the entered domain as a string                                                |
| `getAvailabilityDetails` | Returns detailed information about how availability was determined (debug method)                        |

:green_circle: `isAvailable()` method uses enhanced detection with multiple methods to accurately determine domain availability. It combines the original library's built-in check with additional detection methods including:

- **Availability keywords detection**: Searches for phrases like "no match", "not found", "available", etc.
- **Response length analysis**: Short responses often indicate availability
- **Pattern matching**: Uses regex patterns to identify availability indicators
- **TLD-specific patterns**: Different TLDs use different availability messages
- **Domain status indicators**: Checks for explicit status fields
- **Registration field analysis**: Absence of registration details may indicate availability

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

:green_circle: `getAvailabilityDetails()` method provides detailed debugging information about how the domain availability was determined. This method returns an array containing the results of each detection method:

```PHP
$details = $whoisHandler->getAvailabilityDetails();

// Example output:
// Array
// (
//     [original_library_result] => false
//     [contains_availability_keywords] => true
//     [is_response_too_short] => false
//     [contains_no_match_patterns] => true
//     [tld_specific_patterns] => false
//     [domain_status_indicators] => false
//     [final_availability] => true
//     [whois_message_length] => 1234
//     [whois_message_preview] => "No match for domain example123.com..."
// )
```

## :sparkles: Enhanced Availability Detection

The enhanced availability detection system uses 6 different methods to ensure accurate results:

1. **Original Library Check**: Uses the built-in whois server response analysis
2. **Keyword Detection**: Searches for over 40 availability-related keywords in multiple languages
3. **Response Length Analysis**: Analyzes response size and meaningful content lines
4. **Pattern Matching**: Uses regex patterns to identify availability indicators
5. **TLD-Specific Patterns**: Applies specialized patterns for different TLDs (.com, .uk, .de, etc.)
6. **Status Indicators**: Checks for explicit domain status fields and registration data presence

This multi-layered approach significantly improves accuracy across different WHOIS servers and TLD registries.

## :globe_with_meridians: Whois Server List
### Almost all TLDs are supported.

## :globe_with_meridians: Contributing
If you want to add support for new TLD, extend functionality or correct a bug, feel free to create a new pull request at Github's repository

## :balance_scale: License

[MIT](https://choosealicense.com/licenses/mit/)

## :computer: Support

For support, email dev@monovm.com.

[MonoVM.com](https://monovm.com)

![Logo](https://monovm.com/site-assets/images/logo-monovm.svg)

