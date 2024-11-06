<?php 

namespace App\Exception;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    protected $message = 'Insuficient funds for withdrawal';
}