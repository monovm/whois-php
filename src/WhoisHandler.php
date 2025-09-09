<?php

namespace MonoVM\WhoisPhp;

class WhoisHandler
{
    private string $sld;
    private string $tld;
    private bool $isAvailable = false;
    private bool $isValid = true;
    private string $whoisMessage;

    /**
     * Handler construct.
     */
    protected function __construct(?string $domain = null)
    {
        if ($domain) {
            $domainParts = explode('.', $domain, 2);
            $this->sld = $domainParts[0];
            $this->tld = '.' . $domainParts[1];

            $whois = new Whois();

            if ($whois->canLookup($this->tld)) {
                $result = $whois->lookup(['sld' => $this->sld, 'tld' => $this->tld]);
                if ($result['result'] == 'available' && !isset($result['whois'])) {
                    $this->whoisMessage = $domain . ' is available for registration.';
                    $this->isAvailable = true;
                } else {
                    $this->whoisMessage = $result['whois'];
                }
            } else {
                $this->whoisMessage =
                    'Unable to lookup whois information for ' . $domain;
                $this->isValid = false;
            }
        }
    }

    /**
     * Starts the whois operation.
     */
    public static function whois(string $domain): WhoisHandler
    {
        return new self($domain);
    }

    /**
     * Returns Top-Level Domain.
     */
    public function getTld(): string
    {
        return $this->tld;
    }

    /**
     * Returns Second-Level Domain.
     */
    public function getSld(): string
    {
        return $this->sld;
    }

    /**
     * Determines if the domain is available for registration using multiple detection methods.
     */
    public function isAvailable(): bool
    {
        // Use enhanced availability detection
        return $this->isDomainAvailableEnhanced();
    }

    /**
     * Determines if the domain can be looked up.
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Returns the whois server message.
     */
    public function getWhoisMessage(): string
    {
        return $this->whoisMessage;
    }

    /**
     * Enhanced domain availability detection using multiple methods
     */
    private function isDomainAvailableEnhanced(): bool
    {
        // Method 1: Use the original library's built-in check
        if ($this->isAvailable) {
            return true;
        }

        // Method 2: Check for availability keywords in the response
        if ($this->containsAvailabilityKeywords($this->whoisMessage)) {
            return true;
        }

        // Method 3: Check if the response is too short/empty (likely available)
        if ($this->isResponseTooShort($this->whoisMessage)) {
            return true;
        }

        // Method 4: Check for "No match" or similar patterns
        if ($this->containsNoMatchPatterns($this->whoisMessage)) {
            return true;
        }

        // Method 5: Check for TLD-specific availability patterns
        if ($this->checkTldSpecificPatterns($this->whoisMessage, $this->tld)) {
            return true;
        }

        // Method 6: Check for domain status indicators
        if ($this->checkDomainStatusIndicators($this->whoisMessage)) {
            return true;
        }

        return false;
    }

    /**
     * Check for keywords that indicate domain availability
     */
    private function containsAvailabilityKeywords(string $whoisMessage): bool
    {
        $availabilityKeywords = [
            'no match',
            'not found',
            'no data found',
            'no entries found',
            'available',
            'available for registration',
            'domain status: available',
            'no matching record',
            'not registered',
            'free',
            'no object found',
            'no such domain',
            'object does not exist',
            'nothing found',
            'no domain',
            'domain not found',
            'status: available',
            'registration status: available',
            'status:\tavailable',
            'status:\t\tavailable',
            'status: free',
            'is free',
            'domain name not known',
            'domain has not been registered',
            'domain name has not been registered',
            'is available for purchase',
            'no se encontro el objeto',
            'object_not_found',
            'el dominio no se encuentra registrado',
            'domain is available',
            'domain does not exist',
            'does not exist in database',
            'was not found',
            'not exist',
            'no está registrado',
            'is available for registration',
            'not registered',
            'no entries found',
            'not found...',
        ];

        $lowerMessage = strtolower($whoisMessage);

        foreach ($availabilityKeywords as $keyword) {
            if (strpos($lowerMessage, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if WHOIS response is too short to contain real registration data
     */
    private function isResponseTooShort(string $whoisMessage): bool
    {
        $trimmedMessage = trim($whoisMessage);

        // If message is very short (less than 100 characters), likely available
        if (strlen($trimmedMessage) < 100) {
            return true;
        }

        // Count meaningful lines (ignore empty lines and comments)
        $lines = explode("\n", $trimmedMessage);
        $meaningfulLines = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && 
                !str_starts_with($line, '%') && 
                !str_starts_with($line, '#') && 
                !str_starts_with($line, ';') &&
                !str_starts_with($line, '>>>') &&
                !str_starts_with($line, '---')) {
                $meaningfulLines++;
            }
        }

        // If less than 5 meaningful lines, likely available
        return $meaningfulLines < 5;
    }

    /**
     * Check for "No match" type patterns that indicate availability
     */
    private function containsNoMatchPatterns(string $whoisMessage): bool
    {
        $noMatchPatterns = [
            '/no\s+match/i',
            '/not\s+found/i',
            '/no\s+data\s+found/i',
            '/no\s+entries\s+found/i',
            '/no\s+matching\s+record/i',
            '/object\s+does\s+not\s+exist/i',
            '/no\s+such\s+domain/i',
            '/domain\s+not\s+found/i',
            '/status:\s*available/i',
            '/registration\s+status:\s*available/i',
            '/status:\s*free/i',
            '/\bis\s+free\b/i',
            '/domain\s+name\s+not\s+known/i',
            '/domain\s+has\s+not\s+been\s+registered/i',
            '/domain\s+name\s+has\s+not\s+been\s+registered/i',
            '/\bis\s+available\s+for/i',
            '/no\s+se\s+encontro\s+el\s+objeto/i',
            '/object_not_found/i',
            '/el\s+dominio\s+no\s+se\s+encuentra\s+registrado/i',
            '/domain\s+is\s+available/i',
            '/domain\s+does\s+not\s+exist/i',
            '/does\s+not\s+exist\s+in\s+database/i',
            '/was\s+not\s+found/i',
            '/not\s+exist/i',
            '/no\s+está\s+registrado/i',
            '/---available/i',
            '/---not\s+found/i',
            '/---domain\s+not\s+found/i',
            '/%error:103/i',
            '/404/i', // For RDAP servers
        ];

        foreach ($noMatchPatterns as $pattern) {
            if (preg_match($pattern, $whoisMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for TLD-specific availability patterns
     */
    private function checkTldSpecificPatterns(string $whoisMessage, string $tld): bool
    {
        $tldPatterns = [
            '.com' => ['/no\s+match\s+for/i'],
            '.net' => ['/no\s+match\s+for/i'],
            '.org' => ['/domain\s+not\s+found/i'],
            '.uk' => ['/no\s+match/i'],
            '.de' => ['/status:\s*free/i'],
            '.fr' => ['/not\s+found/i'],
            '.it' => ['/available/i'],
            '.au' => ['/---available/i'],
            '.be' => ['/status:\s*available/i'],
            '.ca' => ['/not\s+found/i'],
            '.ch' => ['/---1:/i'],
            '.eu' => ['/status:\s*available/i'],
            '.jp' => ['/no\s+match!!/i'],
            '.nl' => ['/\bis\s+free/i'],
            '.ru' => ['/no\s+entries\s+found/i'],
            '.br' => ['/no\s+match\s+for/i'],
            '.mx' => ['/no_se_encontro_el_objeto/i'],
            '.ar' => ['/el\s+dominio\s+no\s+se\s+encuentra\s+registrado/i'],
            '.gr' => ['/not\s+exist/i'],
            '.ph' => ['/domain\s+is\s+available/i'],
            '.my' => ['/does\s+not\s+exist\s+in\s+database/i'],
            '.tw' => ['/no\s+found/i'],
            '.hk' => ['/the\s+domain\s+has\s+not\s+been\s+registered/i'],
            '.im' => ['/was\s+not\s+found/i'],
            '.ec' => ['/404/i'], // RDAP server
        ];

        if (isset($tldPatterns[$tld])) {
            foreach ($tldPatterns[$tld] as $pattern) {
                if (preg_match($pattern, $whoisMessage)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for domain status indicators that suggest availability
     */
    private function checkDomainStatusIndicators(string $whoisMessage): bool
    {
        $lowerMessage = strtolower($whoisMessage);
        
        // Check for explicit availability status indicators
        $statusIndicators = [
            'status: available',
            'status:\tavailable',
            'status: free',
            'registration status: available',
            'domain status: available',
            'availability: available',
            'state: available',
            'status = available',
            'status=available',
        ];

        foreach ($statusIndicators as $indicator) {
            if (strpos($lowerMessage, $indicator) !== false) {
                return true;
            }
        }

        // Check for absence of typical registration fields
        $registrationFields = [
            'registrar:',
            'creation date:',
            'created:',
            'expiry date:',
            'expires:',
            'name server:',
            'nameserver:',
            'registrant:',
            'admin contact:',
            'technical contact:',
        ];

        $foundFields = 0;
        foreach ($registrationFields as $field) {
            if (strpos($lowerMessage, $field) !== false) {
                $foundFields++;
            }
        }

        // If we find very few registration fields, domain might be available
        return $foundFields < 2;
    }

    /**
     * Get detailed availability information for debugging
     */
    public function getAvailabilityDetails(): array
    {
        return [
            'original_library_result' => $this->isAvailable,
            'contains_availability_keywords' => $this->containsAvailabilityKeywords($this->whoisMessage),
            'is_response_too_short' => $this->isResponseTooShort($this->whoisMessage),
            'contains_no_match_patterns' => $this->containsNoMatchPatterns($this->whoisMessage),
            'tld_specific_patterns' => $this->checkTldSpecificPatterns($this->whoisMessage, $this->tld),
            'domain_status_indicators' => $this->checkDomainStatusIndicators($this->whoisMessage),
            'final_availability' => $this->isDomainAvailableEnhanced(),
            'whois_message_length' => strlen($this->whoisMessage),
            'whois_message_preview' => substr($this->whoisMessage, 0, 200) . (strlen($this->whoisMessage) > 200 ? '...' : ''),
        ];
    }
}
