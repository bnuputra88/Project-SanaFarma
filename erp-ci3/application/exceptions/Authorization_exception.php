<?php
class Authorization_exception extends Domain_exception
{
    protected $httpStatus = 403;

    public function __construct(string $message = 'Anda tidak memiliki izin untuk aksi ini')
    {
        parent::__construct($message);
    }
}
