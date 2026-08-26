<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\SMS\Models\SmsTemplate;
use Carbon\Carbon;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Water Bill with Unpaid Invoices (Detailed)',
                'content' => '{{estate_name}} {{month}} Water Bill - ({{water_consumption}} units (Last: {{prev_read}}-New: {{curr_read}}))

Paybill: 7263733
Acc: {{unit}}
Amount: KES {{water_bill}}
Due: {{due_date}}
Status: {{payment_status}}

{{unpaid_list}}

Total Unpaid: KES {{unpaid_total}}
Total Due: KES {{total_due}}

For queries: 0701262902',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Water Bill (Compact)',
                'content' => '{{estate_name}} - {{month}} Water Bill

Reading: {{prev_read}}→{{curr_read}} ({{water_consumption}}u)
Amount: KES {{water_bill}}
Due: {{due_date}}

Unpaid:
{{unpaid_list}}
Total Unpaid: KES {{unpaid_total}}

Total Due: KES {{total_due}}

Paybill: 7263733 Acc {{unit}}',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Water Bill (Simple)',
                'content' => '{{estate_name}} - {{month}}

Bill: KES {{water_bill}}
Due: {{due_date}}
Reading: {{prev_read}}→{{curr_read}} ({{water_consumption}}u)

Unpaid:
{{unpaid_list}}
Total: KES {{unpaid_total}}

Total Due: KES {{total_due}}

Paybill 7263733 Acc {{unit}}',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Rent Reminder',
                'content' => 'Dear {{name}},

This is a reminder that your rent for {{estate_name}} is due.

Unit: {{unit}}
Rent Amount: KES {{water_bill}}
Due Date: {{due_date}}

Please make payment to Paybill: 7263733
Account: {{unit}}

Thank you.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'General Broadcast',
                'content' => 'Hello {{name}},

{{message}}

Thank you.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Insert only if templates don't already exist
        foreach ($templates as $template) {
            $exists = SmsTemplate::where('name', $template['name'])->exists();
            if (!$exists) {
                SmsTemplate::create($template);
                $this->command->info('✅ Template created: ' . $template['name']);
            } else {
                $this->command->info('⏭️ Template already exists: ' . $template['name']);
            }
        }

        $this->command->info('🎉 SMS templates seeding completed!');
    }
}