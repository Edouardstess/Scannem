<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\TestCase;

/** Small display helpers the public pages rely on. */
final class DisplayHelpersTest extends TestCase
{
    public function testPricesReadTheFrenchWay(): void
    {
        $nnbsp = "\u{202F}";

        $this->assertSame('2' . $nnbsp . '400' . $nnbsp . '€', format_price(2400, 'EUR'));
        $this->assertSame('380' . $nnbsp . '€', format_price('380.00', 'EUR'));
        $this->assertSame('89,90' . $nnbsp . '€', format_price(89.9, 'EUR'));
        $this->assertSame('15' . $nnbsp . '000' . $nnbsp . 'HTG', format_price(15000, 'HTG'));
        $this->assertSame('250' . $nnbsp . '$', format_price(250, 'usd'));
        $this->assertSame('', format_price(null));
    }

    public function testFirstFilledSkipsEmptiedSettings(): void
    {
        // An admin can empty a field; the page must not render an empty title.
        $this->assertSame('Tagline', first_filled('', '   ', 'Tagline', 'Studio'));
        $this->assertSame('Titre', first_filled(' Titre ', 'Autre'));
        $this->assertSame('', first_filled('', null, []));
    }
}
