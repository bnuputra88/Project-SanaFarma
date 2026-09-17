<?php
class Validation_exception extends Domain_exception
{
    protected $httpStatus = 422;

    public function __construct(array $errors, string $message = 'Validasi gagal')
    {
        parent::__construct($message, $errors);
    }
}
