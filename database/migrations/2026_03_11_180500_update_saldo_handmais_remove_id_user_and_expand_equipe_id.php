<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            IF COL_LENGTH('consultas_api.dbo.saldo_handmais', 'id_user') IS NOT NULL
            BEGIN
                ALTER TABLE consultas_api.dbo.saldo_handmais
                DROP COLUMN id_user;
            END
        ");

        DB::statement("
            IF COL_LENGTH('consultas_api.dbo.saldo_handmais', 'equipe_id') IS NULL
            BEGIN
                ALTER TABLE consultas_api.dbo.saldo_handmais
                ADD equipe_id NVARCHAR(255) NULL;
            END
        ");

        DB::statement("
            ALTER TABLE consultas_api.dbo.saldo_handmais
            ALTER COLUMN equipe_id NVARCHAR(255) NULL;
        ");

        DB::statement("
            UPDATE consultas_api.dbo.saldo_handmais
            SET equipe_id = '1'
            WHERE equipe_id IS NULL OR LTRIM(RTRIM(equipe_id)) = '';
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE consultas_api.dbo.saldo_handmais
            SET equipe_id = '1'
            WHERE TRY_CAST(equipe_id AS INT) IS NULL;
        ");

        DB::statement("
            ALTER TABLE consultas_api.dbo.saldo_handmais
            ALTER COLUMN equipe_id INT NULL;
        ");

        DB::statement("
            IF COL_LENGTH('consultas_api.dbo.saldo_handmais', 'id_user') IS NULL
            BEGIN
                ALTER TABLE consultas_api.dbo.saldo_handmais
                ADD id_user INT NULL;
            END
        ");
    }
};

