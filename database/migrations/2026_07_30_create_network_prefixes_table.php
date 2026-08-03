<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNetworkPrefixesTable extends Migration
{
    public function up()
    {
        Schema::create('network_prefixes', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 3)->unique();
            $table->string('network');
            $table->string('network_code', 10);
            $table->timestamps();
        });

        // Insert Kenyan network prefixes
        DB::table('network_prefixes')->insert([
            // Safaricom
            ['prefix' => '700', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '701', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '702', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '703', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '704', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '705', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '706', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '707', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '708', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '709', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '710', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '711', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '712', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '713', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '714', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '715', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '716', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '717', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '718', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '719', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '720', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '721', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '722', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '723', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '724', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '725', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '726', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '727', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '728', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '729', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '740', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '741', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '742', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '743', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '744', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '745', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '746', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '747', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '748', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '749', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '750', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '751', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '752', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '753', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '754', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '755', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '756', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '757', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '758', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            ['prefix' => '759', 'network' => 'Safaricom', 'network_code' => 'Safaricom'],
            
            // Airtel
            ['prefix' => '730', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '731', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '732', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '733', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '734', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '735', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '736', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '737', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '738', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '739', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '760', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '761', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '762', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '763', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '764', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '765', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '766', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '767', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '768', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '769', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '770', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '771', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '772', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '773', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '774', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '775', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '776', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '777', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '778', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '779', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '780', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '781', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '782', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '783', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '784', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '785', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '786', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '787', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '788', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            ['prefix' => '789', 'network' => 'Airtel', 'network_code' => 'Airtel'],
            
            // Telkom
            ['prefix' => '790', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '791', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '792', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '793', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '794', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '795', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '796', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '797', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '798', 'network' => 'Telkom', 'network_code' => 'Telkom'],
            ['prefix' => '799', 'network' => 'Telkom', 'network_code' => 'Telkom'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('network_prefixes');
    }
}