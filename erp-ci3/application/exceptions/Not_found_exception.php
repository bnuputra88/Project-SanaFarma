<?php
class Not_found_exception extends Domain_exception
{
    protected $httpStatus = 404;

    public function __construct(string $message = 'Data tidak ditemukan')
    {
        parent::__construct($message);
    }
}
