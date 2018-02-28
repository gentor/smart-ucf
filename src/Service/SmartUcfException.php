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
     * @var string|null
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
        if (!is_null($details)) {
            $message .= "\n\n" . json_encode($details, JSON_UNESCAPED_UNICODE);
        }

        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    /**
     * @return string|null
     */
    public function getDetails()
    {
        return $this->details;
    }
}