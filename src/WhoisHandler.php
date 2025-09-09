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
        return AvailabilityDetector::isAvailable($this->whoisMessage, $this->tld, $this->isAvailable);
    }

    /**
     * Get detailed availability information for debugging
     */
    public function getAvailabilityDetails(): array
    {
        return AvailabilityDetector::getAvailabilityDetails($this->whoisMessage, $this->tld, $this->isAvailable);
    }
}
