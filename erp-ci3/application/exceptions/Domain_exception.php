<?php
/** Base exception for all business/domain errors. HTTP status hints at API mapping. */
class Domain_exception extends RuntimeException
{
    protected $httpStatus = 422;
    protected $errors = [];

    public function __construct(string $message = 'Business rule violation', array $errors = [], int $code = 0)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
