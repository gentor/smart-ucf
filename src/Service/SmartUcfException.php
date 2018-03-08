<?php

namespace Gentor\SmartUcf\Service;

use Exception;

/**
 * Class SmartUcfException
 *
 * @package Gentor\SmartUcf\Service
 */
class SmartUcfException extends Exception
{
    /**
     * @var \stdClass|null
     */
    protected $details;

    /**
     * BnpError constructor.
     *
     * @param string $message
     * @param int $code
     * @param \stdClass|null $details
     * @param \Exception|null $previous
     */
    public function __construct($message = "", $code = 0, $details = null, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    /**
     * @return \stdClass|null
     */
    public function getDetails()
    {
        return $this->details;
    }
}