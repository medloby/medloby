<?php

namespace App\Rules;

final class PriceAmount
{
    /** @var list<string> Rules for amounts stored in decimal(12, 2) columns. */
    public const array RULES = ['numeric', 'min:0', 'max:9999999999.99'];
}
