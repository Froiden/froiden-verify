<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

class UpdateSettingsAddSupportKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $setting = config('froiden_envato.setting');
        $settingTable = (new $setting)->getTable();

        Schema::table($settingTable, function (Blueprint $table) use ($settingTable) {
            if (!Schema::hasColumn($settingTable, 'supported_until')) {
                $table->timestamp('supported_until')->nullable();
            }
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
