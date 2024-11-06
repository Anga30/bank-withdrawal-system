<?php 

namespace App\Exception;

use RuntimeException;

class WithdrawalFailedException extends RuntimeException
{
    protected $message = 'Withdrawal failed due to system error';
}