<?php

namespace Tests\Unit;

use App\Modules\CRM\Support\ClientFaq;
use App\Modules\CRM\Support\Playbook;
use PHPUnit\Framework\TestCase;

/** The account manager FAQ carries the sales rules the team agreed: units first, units in use, vacant discount, white label last. */
class ClientFaqTest extends TestCase
{
    private function text(): string
    {
        return collect(ClientFaq::sections())->flatMap(fn ($s) => collect($s['items'])->flatMap(fn ($i) => array_merge([$i['q']], $i['a'])))->join("\n");
    }

    public function test_sections_have_unique_anchors_and_answers(): void
    {
        $sections = ClientFaq::sections();
        $ids = array_column($sections, 'id');
        $this->assertSame($ids, array_unique($ids));
        foreach ($sections as $s) {
            $this->assertNotEmpty($s['items']);
            foreach ($s['items'] as $item) {
                $this->assertNotSame('', trim($item['q']));
                $this->assertNotEmpty($item['a']);
            }
        }
    }

    public function test_it_carries_the_agreed_sales_rules(): void
    {
        $text = $this->text();
        $this->assertStringContainsString(Playbook::price(), $text);
        $this->assertStringContainsString('How many units do they have in total?', $text);
        $this->assertStringContainsString('**First 6 months: no discount** on vacant units. **After 6 months: 25% off** vacant units. **After the first year: 50% off** vacant units.', $text);
        $this->assertStringNotContainsString('or free', $text);
        $this->assertStringContainsString('We give them everything in their branding.', $text);
        $this->assertStringContainsString('**Last.**', $text);
        $this->assertStringNotContainsStringIgnoringCase('cloud9', $text);
    }
}
