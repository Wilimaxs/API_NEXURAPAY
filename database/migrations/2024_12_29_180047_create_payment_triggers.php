<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop triggers if they exist
        DB::unprepared('DROP TRIGGER IF EXISTS after_callback_midtrans_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS after_topups_update');

        // Create trigger for callback_midtrans to topups
        DB::unprepared('
            CREATE TRIGGER after_callback_midtrans_insert 
            AFTER INSERT ON callback_midtrans
            FOR EACH ROW
            BEGIN
                UPDATE topups 
                SET status = 
                    CASE 
                        WHEN NEW.transaction_status = "settlement" THEN 1
                        WHEN NEW.transaction_status = "expire" THEN 2
                        WHEN NEW.transaction_status = "pending" THEN 0
                        ELSE topups.status
                    END
                WHERE topups.order_id = NEW.order_id;
            END
        ');

        // Create trigger for topups to balances
        DB::unprepared('
            CREATE TRIGGER after_topups_update
            AFTER UPDATE ON topups
            FOR EACH ROW 
            BEGIN
                IF NEW.status = 1  THEN
                    UPDATE balances 
                    INNER JOIN users ON users.id = balances.user_id
                    SET balances.amount = balances.amount + NEW.amount
                    WHERE users.hp = NEW.hp;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS after_callback_midtrans_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS after_topups_update');
    }
};
