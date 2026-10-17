<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{AppException, Logger, Validator};
use App\Repositories\PokemonRepository;

/**
 * Regras de negocio do catalogo de Pokemon.
 */
final class PokemonService
{
    public function __construct(private PokemonRepository $pokemons)
    {
    }

    public function list(array $query): array
    {
        $page  = $this->intParam($query['page'] ?? 1, 1, 1000000, 'page');
        $limit = $this->intParam($query['limit'] ?? 10, 1, 50, 'limit');
        $tipo  = $this->textParam($query['tipo'] ?? null, 30, 'tipo');
        $busca = $this->textParam($query['busca'] ?? null, 60, 'busca');

        $r = $this->pokemons->paginate($page, $limit, $tipo, $busca);

        return [
            'items'       => $r['items'],
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $r['total'],
            'total_pages' => (int) ceil($r['total'] / $limit),
        ];
    }

    public function get(int $id): array
    {
        $p = $this->pokemons->findById($id);
        if ($p === null) {
            throw new AppException('Pokémon não encontrado', 404);
        }
        return $p;
    }

    public function create(array $input, int $userId): array
    {
        $dados = $this->validar($input);
        $id = $this->pokemons->create(
            $dados['nome'], $dados['tipo'], $dados['descricao'], $dados['imagem_url'], $userId
        );
        Logger::info('Pokémon criado', ['id' => $id, 'nome' => $dados['nome'], 'por' => $userId]);
        return $this->get($id);
    }

    public function update(int $id, array $input, int $userId): array
    {
        $this->get($id); // 404 se nao existir
        $dados = $this->validar($input);
        $this->pokemons->update($id, $dados['nome'], $dados['tipo'], $dados['descricao'], $dados['imagem_url']);
        Logger::info('Pokémon atualizado', ['id' => $id, 'por' => $userId]);
        return $this->get($id);
    }

    public function delete(int $id, int $userId): void
    {
        $this->get($id); // 404 se nao existir
        $this->pokemons->delete($id);
        Logger::info('Pokémon excluído', ['id' => $id, 'por' => $userId]);
    }

    /** Valida e sanitiza os campos de um Pokemon. */
    private function validar(array $input): array
    {
        (new Validator($input))
            ->text('nome', 2, 60, 'Nome')
            ->text('tipo', 2, 30, 'Tipo')
            ->optionalText('descricao', 500, 'Descrição')
            ->optionalUrl('imagem_url')
            ->validate();

        $descricao = Validator::cleanText($input['descricao'] ?? '');
        $imagem    = trim((string) ($input['imagem_url'] ?? ''));

        return [
            'nome'       => Validator::cleanText($input['nome']),
            'tipo'       => Validator::cleanText($input['tipo']),
            'descricao'  => $descricao === '' ? null : $descricao,
            'imagem_url' => $imagem === '' ? null : $imagem,
        ];
    }

    private function intParam(mixed $v, int $min, int $max, string $nome): int
    {
        if (is_string($v) && ctype_digit($v)) {
            $v = (int) $v;
        }
        if (!is_int($v) || $v < $min || $v > $max) {
            throw new AppException('Parâmetro inválido', 422, [$nome => "Deve ser um inteiro entre $min e $max"]);
        }
        return $v;
    }

    private function textParam(mixed $v, int $max, string $nome): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_string($v)) {
            throw new AppException('Parâmetro inválido', 422, [$nome => 'Deve ser texto']);
        }
        $limpo = Validator::cleanText($v);
        if (mb_strlen($limpo) > $max) {
            throw new AppException('Parâmetro inválido', 422, [$nome => "Máximo de $max caracteres"]);
        }
        return $limpo === '' ? null : $limpo;
    }
}