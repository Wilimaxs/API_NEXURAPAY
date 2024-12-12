<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Trigger saat transaksi dibuat
        DB::unprepared('
            CREATE TRIGGER after_transaction_created 
            AFTER INSERT ON transactions
            FOR EACH ROW
            BEGIN

                IF NEW.status = "0" THEN
                UPDATE balances
                SET amount = amount - NEW.hjual
                WHERE user_id = (SELECT id FROM users WHERE hp = NEW.no_hp);
                        
                INSERT INTO transaction_reports
                (reff, debet, kredit, saldo, created_at, updated_at)
                VALUES 
                (NEW.reff, (NEW.hjual + NEW.adm), "0", NEW.last_balancejual, NOW(), NOW());

                END IF;
            END
        ');

        // Trigger saat callback receive
        DB::update('
        CREATE TRIGGER after_callbacks_insert
        AFTER INSERT ON callbacks
        FOR EACH ROW
        BEGIN
            UPDATE transactions SET transactions.status = NEW.status, transactions.hbeli = NEW.harga + NEW.jumlah_tagihan + NEW.admin, transactions.fr_balancebeli = NEW.saldo_before_trx, transactions.last_balancebeli = NEW.saldo_after_trx, transactions.updated_at = NEW.updated_at
            WHERE transactions.reff = NEW.api_trxid;
        END
        ');

        // Trigger saat before transactions Update = 2
        DB::unprepared('
            CREATE TRIGGER before_transaction_update
            BEFORE UPDATE ON transactions
            FOR EACH ROW
            BEGIN

                IF NEW.status = "2" THEN
                UPDATE balances
                SET amount = amount + NEW.hjual
                WHERE user_id = (SELECT id FROM users WHERE hp = NEW.no_hp);
                END IF;

            END
        ');

        // Trigger saat after transactions Update = 2
        DB::unprepared('
            CREATE TRIGGER after_transaction_update
            AFTER UPDATE ON transactions
            FOR EACH ROW
        BEGIN

            IF NEW.status = "2" THEN
            INSERT INTO transaction_reports (reff, debet, kredit, saldo, created_at, updated_at)
            VALUES 
            (NEW.reff, 
             0, 
             (NEW.hjual + NEW.adm), 
             (SELECT amount FROM balances WHERE user_id = (SELECT id FROM users WHERE hp = NEW.no_hp) LIMIT 1), 
             NOW(), 
             NOW()
            );
            END IF ;
        END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS after_transaction_created');
        DB::unprepared('DROP TRIGGER IF EXISTS after_callbacks_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS before_transaction_update');
        DB::unprepared('DROP TRIGGER IF EXISTS after_transaction_update');
    }
};
