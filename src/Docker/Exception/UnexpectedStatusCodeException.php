<?php

namespace Docker\Exception;

use Docker\Exception as BaseException;
use GuzzleHttp\Message\ResponseInterface;

/**
 * UnexpectedStatusCodeException
 */
class UnexpectedStatusCodeException extends BaseException
{
    /**
     * @param integer $statusCode
     * @param string  $message
     */
    public function __construct($statusCode, $message = null)
    {
        $message = $message ?: 'Status Code: '.$statusCode;
        parent::__construct($message, (integer) $statusCode);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return UnexpectedStatusCodeException
     */
    public static function fromResponse(ResponseInterface $response)
    {
        return new self($response->getStatusCode(), trim((string) $response->getBody()));
    }
}
