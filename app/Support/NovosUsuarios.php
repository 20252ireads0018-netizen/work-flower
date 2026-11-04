<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de contas novas no painel da administração. Guarda, por admin, o id da última conta já vista
 * (tabela configuracoes, chave admin.novos_visto.<id do admin>). Sem nada guardado, mostra os últimos 7 dias.
 */
class NovosUsuarios
{
    public const MOSTRAR = 30;

    private static function chave(User $admin): string
    {
        return 'admin.novos_visto.' . $admin->id;
    }

    public static function vistoAte(User $admin): int
    {
        if (Schema::hasTable('configuracoes')) {
            $v = DB::table('configuracoes')->where('chave', self::chave($admin))->value('valor');
            if ($v !== null) return (int) $v;
        }

        return (int) User::where('created_at', '<', now()->subDays(7))->max('id');
    }

    /** @return array{total: int, lista: Collection<int, User>} */
    public static function para(User $admin): array
    {
        $q = User::where('id', '>', self::vistoAte($admin))
            ->whereNotIn(DB::raw('lower(email)'), User::ADMINS);

        return [
            'total' => (clone $q)->count(),
            'lista' => $q->orderByDesc('id')->take(self::MOSTRAR)->get(['id', 'name', 'email', 'created_at', 'plano_slug', 'plano_expira_em']),
        ];
    }

    public static function marcarVisto(User $admin): void
    {
        if (! Schema::hasTable('configuracoes')) return;
        DB::table('configuracoes')->updateOrInsert(['chave' => self::chave($admin)], [
            'valor' => (string) (int) User::max('id'),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }
}
