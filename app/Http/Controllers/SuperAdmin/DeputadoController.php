<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Deputado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class DeputadoController extends Controller
{
    /**
     * Busca deputado por nome/nome urna (para autocomplete)
     */
    public function searchByName(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3',
        ]);

        $query = $request->input('query');

        // Último ano de candidatura por título de eleitor
        $ultimasCandidaturas = DB::table('candidaturas')
            ->select('titulo_eleitoral', DB::raw('MAX(ano) as ano'))
            ->groupBy('titulo_eleitoral');

        $deputados = DB::table('candidaturas as c')
            ->joinSub($ultimasCandidaturas, 'u', function ($join) {
                $join->on('c.titulo_eleitoral', '=', 'u.titulo_eleitoral')
                    ->on('c.ano', '=', 'u.ano');
            })
            ->select([
                'c.titulo_eleitoral',
                'c.nome',
                'c.nome_urna',
                'c.sigla_partido',
                'c.cargo',
            ])
            ->where(function ($q) use ($query) {
                $q->where('c.nome', 'like', "%{$query}%")
                    ->orWhere('c.nome_urna', 'like', "%{$query}%");
            })
            ->whereIn('c.cargo', ['deputado estadual', 'deputado federal'])
            ->limit(10)
            ->get();

        // Busca todos os títulos já cadastrados em uma única consulta
        $titulos = $deputados->pluck('titulo_eleitoral');

        $cadastrados = Deputado::whereIn('titulo_eleitoral', $titulos)
            ->pluck('titulo_eleitoral')
            ->flip();

        $deputados->transform(function ($dep) use ($cadastrados) {
            return [
                'titulo_eleitoral' => $dep->titulo_eleitoral,
                'nome' => $dep->nome,
                'nome_urna' => $dep->nome_urna,
                'partido' => $dep->sigla_partido,
                'cargo' => $dep->cargo,
                'ja_cadastrado' => isset($cadastrados[$dep->titulo_eleitoral]),
            ];
        });

        return response()->json($deputados);
    }

    /**
     * Busca deputado por título eleitoral
     */
    public function searchByTitulo(Request $request)
    {
        $request->validate([
            'titulo_eleitoral' => 'required|string|size:12', // Título tem 12 dígitos
        ], [
            'titulo_eleitoral.required' => 'O título eleitoral é obrigatório.',
            'titulo_eleitoral.size' => 'O título eleitoral deve ter 12 dígitos.',
        ]);

        $titulo = $request->titulo_eleitoral;

        // ⬇️ Busca na tabela votacao
        $deputado = DB::table('candidaturas')
            ->select([
                'titulo_eleitoral',
                'nome',
                'nome_urna',
                'sigla_partido',
                'cargo',
            ])
            ->where('titulo_eleitoral', $titulo)
            ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
            ->orderByDesc('ano')
            ->first();

        if (! $deputado) {
            return response()->json([
                'message' => 'Nenhum deputado encontrado com este título eleitoral.',
            ], 404);
        }

        $jaCadastrado = Deputado::where('titulo_eleitoral', $titulo)->exists();

        return response()->json([
            'titulo_eleitoral' => $deputado->titulo_eleitoral,
            'nome' => $deputado->nome,
            'nome_urna' => $deputado->nome_urna,
            'partido' => $deputado->sigla_partido,
            'cargo' => $deputado->cargo,
            'ja_cadastrado' => $jaCadastrado,
        ]);
    }

    /**
     * Lista todos deputados cadastrados
     */
    public function index(Request $request)
    {
        $query = Deputado::withCount('users');

        // Filtro por status
        if ($request->has('ativo')) {
            $query->where('ativo', $request->boolean('ativo'));
        }

        // Busca textual
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('nome_urna', 'like', "%{$search}%")
                    ->orWhere('titulo_eleitoral', 'like', "%{$search}%")
                    ->orWhere('partido', 'like', "%{$search}%");
            });
        }

        $deputados = $query->orderBy('nome')->paginate(20);

        $deputados->getCollection()->transform(fn ($dep) => [
            'id' => $dep->id,
            'titulo_eleitoral' => $dep->titulo_eleitoral,
            'nome' => $dep->nome,
            'nome_urna' => $dep->nome_urna,
            'partido' => $dep->partido,
            'cargo' => $dep->cargo,
            'ativo' => $dep->ativo,
            'total_usuarios' => $dep->users_count,
            'created_at' => $dep->created_at->format('d/m/Y H:i'),
        ]);

        return response()->json($deputados);
    }

    /**
     * Exibe deputado com usuários vinculados
     */
    public function show(int $id)
    {
        $deputado = Deputado::with('users.roles:name')->findOrFail($id);

        return response()->json([
            'id' => $deputado->id,
            'titulo_eleitoral' => $deputado->titulo_eleitoral,
            'nome' => $deputado->nome,
            'nome_urna' => $deputado->nome_urna,
            'partido' => $deputado->partido,
            'cargo' => $deputado->cargo,
            'ativo' => $deputado->ativo,
            'created_at' => $deputado->created_at->format('d/m/Y H:i'),
            'usuarios' => $deputado->users->map(fn ($user) => [
                'id' => $user->id,
                'nome' => $user->name,
                'email' => $user->email,
                'ativo' => $user->ativo,
                'role' => $user->roles->pluck('name')->first(),
            ])->values(),
        ]);
    }

    /**
     * Cadastra deputado (só com título eleitoral)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo_eleitoral' => 'required|string|size:12|unique:deputados,titulo_eleitoral',
        ], [
            'titulo_eleitoral.required' => 'O título eleitoral é obrigatório.',
            'titulo_eleitoral.size' => 'O título eleitoral deve ter 12 dígitos.',
            'titulo_eleitoral.unique' => 'Este deputado já está cadastrado.',
        ]);

        // ⬇️ Busca dados do deputado na tabela candidaturas
        $deputadoData = DB::table('candidaturas')
            ->select([
                'titulo_eleitoral',
                'nome',
                'nome_urna',
                'sigla_partido',
                'cargo',
            ])
            ->where('titulo_eleitoral', $validated['titulo_eleitoral'])
            ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
            ->orderByDesc('ano')
            ->first();

        if (! $deputadoData) {
            return response()->json([
                'message' => 'Título eleitoral não corresponde a um deputado válido.',
            ], 422);
        }

        // ⬇️ Cadastra
        $deputado = Deputado::create([
            'titulo_eleitoral' => $deputadoData->titulo_eleitoral,
            'nome' => $deputadoData->nome,
            'nome_urna' => $deputadoData->nome_urna,
            'partido' => $deputadoData->sigla_partido,
            'cargo' => $deputadoData->cargo,
            'ativo' => true,
        ]);

        return response()->json([
            'message' => 'Deputado cadastrado com sucesso',
            'deputado' => $deputado,
        ], 201);
    }

    /**
     * Atualiza deputado (só ativo/inativo)
     */
    public function update(Request $request, int $id)
    {
        $deputado = Deputado::findOrFail($id);

        $validated = $request->validate([
            'ativo' => 'required|boolean',
        ]);

        $deputado->update($validated);

        return response()->json([
            'message' => 'Deputado atualizado com sucesso',
            'deputado' => $deputado,
        ]);
    }

    /**
     * Ativa/Desativa deputado
     */
    public function toggleStatus(int $id)
    {
        $deputado = Deputado::findOrFail($id);

        $deputado->ativo = ! $deputado->ativo;
        $deputado->save();

        return response()->json([
            'message' => $deputado->ativo ? 'Deputado ativado' : 'Deputado desativado',
            'ativo' => $deputado->ativo,
        ]);
    }

    /**
     * Cria usuário admin para um deputado
     */
    public function createAdminUser(Request $request, int $deputadoId)
    {
        $deputado = Deputado::findOrFail($deputadoId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.required' => 'A senha é obrigatória.',
        ]);

        $user = User::create([
            'deputado_id' => $deputado->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'ativo' => true,
        ]);

        $user->assignRole('admin');

        return response()->json([
            'message' => 'Usuário administrador criado com sucesso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'deputado' => [
                    'id' => $deputado->id,
                    'nome' => $deputado->nome,
                ],
            ],
        ], 201);
    }
}
