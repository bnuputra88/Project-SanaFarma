<?php
class Authentication_exception extends Domain_exception
{
    protected $httpStatus = 401;

    public function __construct(string $message = 'Autentikasi diperlukan')
    {
        parent::__construct($message);
    }
}
