<?php
/** Optimistic-lock / duplicate / concurrent modification conflicts. */
class Conflict_exception extends Domain_exception
{
    protected $httpStatus = 409;

    public function __construct(string $message = 'Data telah diubah oleh proses lain, muat ulang dan coba lagi')
    {
        parent::__construct($message);
    }
}
