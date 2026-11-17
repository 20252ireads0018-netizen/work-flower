<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SitesWebsight;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/sites-websight (só a administração): para cada pessoa do Work Flower, se ela também tem cadastro nos
 * outros sites da Websight (Praga Vermelha, Worldbuilder, Trabalhos e os SaaS do GED). Só leitura.
 */
class SitesWebsightController extends Controller
{
    public function index(Request $request): View
    {
        $busca = trim((string) $request->query('q', ''));
        $filtro = (string) $request->query('site', '');
        $usuarios = User::query()
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', '%' . addcslashes($busca, '%_\\') . '%')
                ->orWhere('name', 'like', '%' . addcslashes($busca, '%_\\') . '%')))
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        $r = SitesWebsight::cadastros($usuarios->pluck('email')->all());
        $sites = $r['sites'];
        if ($filtro !== '' && ! isset($sites[$filtro])) $filtro = '';

        // resumo de toda a base (não só da página): quantos do Work Flower estão em cada site
        $resumo = [];
        if (User::count() <= 5000) {
            $todos = SitesWebsight::cadastros(User::pluck('email')->all());
            foreach ($todos['achados'] as $id => $l) $resumo[$id] = count($l);
            foreach ($todos['sites'] as $id => $s) if ($s['estado'] === 'erro') $sites[$id] = $s;
        }

        return view('admin.sites-websight', [
            'usuarios' => $usuarios,
            'sites' => $sites,
            'achados' => $r['achados'],
            'resumo' => $resumo,
            'totais' => SitesWebsight::totais(),
            'busca' => $busca,
            'filtro' => $filtro,
        ]);
    }
}
