<?php

declare(strict_types=1);


namespace Acme\Shop;

use Symfony\Component\Routing\Attribute\Route;

final class Greeter
{
    #[Route('/greet')]
    public function greet(string $name): string
    {
        return 'Hello '.$name;
    }
}
