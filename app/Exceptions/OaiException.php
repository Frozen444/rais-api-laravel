<?php

namespace App\Exceptions;

use Exception;

class OaiException extends Exception
{
    protected string $oaiCode;

    public function __construct(string $oaiCode, string $message = '')
    {
        $this->oaiCode = $oaiCode;
        parent::__construct($message ?: self::getDefaultMessage($oaiCode));
    }

    public function getOaiCode(): string
    {
        return $this->oaiCode;
    }

    public static function getDefaultMessage(string $oaiCode): string
    {
        return match ($oaiCode) {
            'badVerb' => 'The value of the verb argument is not a legal OAI-PMH verb',
            'badArgument' => 'The request includes illegal arguments or is missing required arguments',
            'cannotDisseminateFormat' => 'The metadata format is not supported by this repository',
            'idDoesNotExist' => 'The identifier does not exist in this repository',
            'noRecordsMatch' => 'No records match the request criteria',
            'badResumptionToken' => 'The resumption token is invalid or has expired',
            'noSetHierarchy' => 'The repository does not support sets or the set does not exist',
            default => 'Unknown OAI-PMH error',
        };
    }

    public static function badVerb(?string $message = null): self
    {
        return new self('badVerb', $message);
    }

    public static function badArgument(?string $message = null): self
    {
        return new self('badArgument', $message);
    }

    public static function cannotDisseminateFormat(?string $message = null): self
    {
        return new self('cannotDisseminateFormat', $message);
    }

    public static function idDoesNotExist(?string $message = null): self
    {
        return new self('idDoesNotExist', $message);
    }

    public static function noRecordsMatch(?string $message = null): self
    {
        return new self('noRecordsMatch', $message);
    }

    public static function badResumptionToken(?string $message = null): self
    {
        return new self('badResumptionToken', $message);
    }

    public static function noSetHierarchy(?string $message = null): self
    {
        return new self('noSetHierarchy', $message);
    }
}
