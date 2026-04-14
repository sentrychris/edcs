<?php

namespace App\Services\Eddn;

abstract class EddnService
{
    /**
     * Software
     * 
     * @var array
     */
    protected array $software = [
        'whitelist' => [],
        'blacklist' => [],
    ];

    /**
     * Valid message schemas
     * 
     * @var array
     */
    protected $schemas = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->software = config('elite.eddn.software');
        $this->schemas = config('elite.eddn.schemas');
    }

    /**
     * Validate the message schema reference.
     * 
     * @param string $schemaRef
     * @return bool
     */
    public function validateSchemaRef(string $schemaRef) {
        return isset($this->schemas['valid'][$schemaRef]);
    }
    
    /**
     * Check if the software that sent the message is allowed.
     * 
     * @param string $softwareName
     * @param string $softwareVersion
     * @param array $messageHeaders
     * @return bool
     */
    public function isSoftwareAllowed(array $messageHeader): bool
    {
        $softwareName = $messageHeader["softwareName"];
        $softwareVersion = $messageHeader["softwareVersion"];

        // This should never happen considering messages are validated on the EDDN gateway...
        // But just in case...
        if (!$softwareName || !$softwareVersion) {
            return false;
        }

        if (array_key_exists($softwareName, $this->software["blacklist"])) {
            return false;
        }

        if (array_key_exists($softwareName, $this->software["whitelist"])) {
            $version = $this->software["whitelist"][$softwareName];

            if ($version === "*") {
                return true;
            }

            if (version_compare($softwareVersion, $version, ">=")) {
                return true;
            }
        }

        return false;
    }
}