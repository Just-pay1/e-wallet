<?php

namespace App\Enums;

/**
 * Enum representing the types of transactions.
 */
enum TransactionType: string
{
    case ELECTRIC_BILL = 'electric_bill';
    case REFERENCE_BILL = 'reference_bill';
    case SEND = 'send';
    case WATER_BILL = 'water_bill';
    case MOBILE_BILL = 'mobile_bill';
    case INTERNET_BILL = 'internet_bill';
    case PAY = 'pay';
    case FEE = 'fee';
    case CHARGE = 'charge';
} 