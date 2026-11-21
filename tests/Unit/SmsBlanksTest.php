<?php

namespace Tests\Unit;

use App\Modules\SMS\Services\KenyaSMS;
use PHPUnit\Framework\TestCase;

/** No SMS goes out with {{placeholders}} still in it (263 did in June 2026). */
class SmsBlanksTest extends TestCase
{
    public function test_blanks_are_found_in_any_spacing(): void
    {
        $this->assertSame(['estate_name', 'water_consumption'], KenyaSMS::blanks('{{estate_name }} bill ({{ water_consumption }} units)'));
        $this->assertSame([], KenyaSMS::blanks('Rent of KES 15,000 is due on 5 Dec.'));
    }

    public function test_a_recipient_missing_a_field_is_found(): void
    {
        $t = 'Hi {{name}}, {{estate_name}} water bill is KES {{amount}}';
        $this->assertSame(['estate_name'], KenyaSMS::missingFields($t, ['name' => 'Amina', 'estate_name' => '', 'amount' => 1200]));
        $this->assertSame([], KenyaSMS::missingFields($t, ['name' => 'Amina', 'estate_name' => 'Simba', 'amount' => 0]));
    }
}
