<?php

namespace App\Contracts;

interface MerchantDataProvider
{
    public function provider(): string;

    public function supports(string $dataType): bool;
}
