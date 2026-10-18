<?php
declare(strict_types=1);

namespace Tests;

use App\Repositories\PokemonRepository;
use App\Services\PokemonService;
use PHPUnit\Framework\TestCase;

final class PokemonServiceTest extends TestCase
{
    use AppAssertions;

    public function testListagemCalculaTotalDePaginas(): void
    {
        $repo = $this->createStub(PokemonRepository::class);
        $repo->method('paginate')->willReturn(['items' => [], 'total' => 25]);

        $r = (new PokemonService($repo))->list(['limit' => '10']);

        $this->assertSame(1, $r['page']);
        $this->assertSame(10, $r['limit']);
        $this->assertSame(25, $r['total']);
        $this->assertSame(3, $r['total_pages']);
    }

    public function testListagemRejeitaParametrosInvalidos(): void
    {
        $repo = $this->createStub(PokemonRepository::class);
        $service = new PokemonService($repo);

        $invalidos = [
            ['limit' => 'abc'],
            ['limit' => '500'],
            ['page' => '0'],
            ['page' => '-1'],
            ['tipo' => ['array']],
        ];
        foreach ($invalidos as $query) {
            $this->assertAppError(422, fn() => $service->list($query));
        }
    }

    public function testCriarRemoveTagsDoNome(): void
    {
        $repo = $this->createMock(PokemonRepository::class);
        $repo->expects($this->once())->method('create')
            ->with('Mewtwo', 'Psíquico', null, null, 1)
            ->willReturn(7);
        $repo->method('findById')->willReturn(['id' => 7, 'nome' => 'Mewtwo']);

        $r = (new PokemonService($repo))->create(['nome' => '<b>Mew</b>two', 'tipo' => 'Psíquico'], 1);

        $this->assertSame('Mewtwo', $r['nome']);
    }

    public function testCriarComDadosInvalidosGera422(): void
    {
        $repo = $this->createMock(PokemonRepository::class);
        $repo->expects($this->never())->method('create');
        $service = new PokemonService($repo);

        $invalidos = [
            ['nome' => 'X', 'tipo' => 'Fogo'],
            ['nome' => 'Charmander'],
            ['nome' => 'Ok', 'tipo' => 'Fogo', 'imagem_url' => 'javascript:alert(1)'],
            ['nome' => str_repeat('a', 61), 'tipo' => 'Fogo'],
        ];
        foreach ($invalidos as $dados) {
            $this->assertAppError(422, fn() => $service->create($dados, 1));
        }
    }

    public function testBuscarPokemonInexistenteGera404(): void
    {
        $repo = $this->createStub(PokemonRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->assertAppError(404, fn() => (new PokemonService($repo))->get(999));
    }

    public function testExcluirPokemonInexistenteNaoChamaExclusao(): void
    {
        $repo = $this->createMock(PokemonRepository::class);
        $repo->method('findById')->willReturn(null);
        $repo->expects($this->never())->method('delete');

        $this->assertAppError(404, fn() => (new PokemonService($repo))->delete(999, 1));
    }
}