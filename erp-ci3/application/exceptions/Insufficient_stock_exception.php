<?php
class Insufficient_stock_exception extends Domain_exception
{
    protected $httpStatus = 422;
}
