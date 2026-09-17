<?php
class Invalid_transition_exception extends Domain_exception
{
    protected $httpStatus = 409;
}
