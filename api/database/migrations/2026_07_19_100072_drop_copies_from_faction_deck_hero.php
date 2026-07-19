<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reversión de la 100071 (que llegó a aplicarse en alguna BBDD antes de
 * decidir que no hacía falta): decisión de producto, un héroe asignado a un
 * mazo es SIEMPRE 1 copia — no se controla cantidad, a diferencia de las
 * cartas (pivot card_faction_deck, que sí conserva `copies`). En BBDD
 * frescas la 100071 ya no existe (se borró) y esta columna nunca se crea; en
 * la de quien ya migró, se suelta aquí con guarda hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('faction_deck_hero', 'copies')) {
            Schema::table('faction_deck_hero', function (Blueprint $table) {
                $table->dropColumn('copies');
            });
        }
    }

    public function down(): void
    {
        // Nada útil que restaurar: los héroes de un mazo no llevan copias.
        if (! Schema::hasColumn('faction_deck_hero', 'copies')) {
            Schema::table('faction_deck_hero', function (Blueprint $table) {
                $table->integer('copies')->default(1);
            });
        }
    }
};
