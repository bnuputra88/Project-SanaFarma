<?php
class Rate_limit_exception extends Domain_exception
{
    protected $httpStatus = 429;

    public function __construct(string $message = 'Terlalu banyak permintaan, coba lagi nanti')
    {
        parent::__construct($message);
    }
}
