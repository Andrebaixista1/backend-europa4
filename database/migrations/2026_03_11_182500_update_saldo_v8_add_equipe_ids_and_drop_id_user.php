<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            IF COL_LENGTH('consultas_api.dbo.saldo_v8', 'id_user') IS NOT NULL
            BEGIN
                ALTER TABLE consultas_api.dbo.saldo_v8
                DROP COLUMN id_user;
            END
        ");

        DB::statement("
            IF COL_LENGTH('consultas_api.dbo.saldo_v8', 'equipe_id') IS NULL
            BEGIN
                ALTER TABLE consultas_api.dbo.saldo_v8
                ADD equipe_id NVARCHAR(255) NULL;
            END
        ");

        DB::statement("
            ALTER TABLE consultas_api.dbo.saldo_v8
            ALTER COLUMN equipe_id NVARCHAR(255) NULL;
        ");

        DB::statement("
            UPDATE consultas_api.dbo.saldo_v8
            SET equipe_id = '{1,2}';
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE consultas_api.dbo.saldo_v8
            SET equipe_id = '1'
            WHERE equipe_id IS NULL OR LTRIM(RTRIM(equipe_id)) = '';
        ");

        DB::statement("
            UPDATE consultas_api.dbo.saldo_v8
            SET equipe_id = '1'
            WHERE TRY_CAST(REPLACE(REPLACE(REPLACE(REPLACE(equipe_id, '{', ''), '}', ''), '[', ''), ']', '') AS INT) IS NULL;
        ");

        DB::statement("
            ALTER TABLE consultas_api.dbo.saldo_v8
            ALTER COLUMN equipe_id INT NULL;
        ");

        DB::statement("
            IF COL_LENGTH('consultas_api.dbo.saldo_v8', 'id_user') IS NULL
            BEGIN
                ALTER TABLE consultas_api.dbo.saldo_v8
                ADD id_user INT NULL;
            END
        ");
    }
};

