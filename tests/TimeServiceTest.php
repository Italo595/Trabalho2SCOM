<?php
declare(strict_types=1);

namespace Tests;

use App\Repositories\{PokemonRepository, TimeRepository};
use App\Services\TimeService;
use PHPUnit\Framework\TestCase;

final class TimeServiceTest extends TestCase
{
    use AppAssertions;

    private const DONO = 7;

    private function time(int $donoId = self::DONO): array
    {
        return ['id' => 1, 'nome' => 'Time A', 'usuario_id' => $donoId, 'criado_em' => '2026-10-18 10:00:00'];
    }

    public function testAdicionarPokemonDentroDoLimite(): void
    {
        $times = $this->createMock(TimeRepository::class);
        $times->method('findById')->willReturn($this->time());
        $times->method('hasPokemon')->willReturn(false);
        $times->method('countPokemons')->willReturn(5);
        $times->method('pokemonsDoTime')->willReturn([['id' => 25, 'nome' => 'Pikachu']]);
        $times->expects($this->once())->method('addPokemon')->with(1, 25);

        $pokemons = $this->createStub(PokemonRepository::class);
        $pokemons->method('findById')->willReturn(['id' => 25, 'nome' => 'Pikachu']);

        $r = (new TimeService($times, $pokemons))->addPokemon(1, self::DONO, ['pokemon_id' => 25]);

        $this->assertCount(1, $r['pokemons']);
    }

    public function testSetimoPokemonGera422(): void
    {
        $times = $this->createMock(TimeRepository::class);
        $times->method('findById')->willReturn($this->time());
        $times->method('hasPokemon')->willReturn(false);
        $times->method('countPokemons')->willReturn(TimeService::MAX_POKEMON);
        $times->expects($this->never())->method('addPokemon');

        $pokemons = $this->createStub(PokemonRepository::class);
        $pokemons->method('findById')->willReturn(['id' => 25]);

        $this->assertAppError(422, fn() => (new TimeService($times, $pokemons))
            ->addPokemon(1, self::DONO, ['pokemon_id' => 25]));
    }

    public function testPokemonRepetidoNoMesmoTimeGera409(): void
    {
        $times = $this->createMock(TimeRepository::class);
        $times->method('findById')->willReturn($this->time());
        $times->method('hasPokemon')->willReturn(true);
        $times->expects($this->never())->method('addPokemon');

        $pokemons = $this->createStub(PokemonRepository::class);
        $pokemons->method('findById')->willReturn(['id' => 25]);

        $this->assertAppError(409, fn() => (new TimeService($times, $pokemons))
            ->addPokemon(1, self::DONO, ['pokemon_id' => 25]));
    }

    public function testPokemonInexistenteGera404(): void
    {
        $times = $this->createStub(TimeRepository::class);
        $times->method('findById')->willReturn($this->time());

        $pokemons = $this->createStub(PokemonRepository::class);
        $pokemons->method('findById')->willReturn(null);

        $this->assertAppError(404, fn() => (new TimeService($times, $pokemons))
            ->addPokemon(1, self::DONO, ['pokemon_id' => 999]));
    }

    public function testTimeDeOutroUsuarioGera403(): void
    {
        $times = $this->createMock(TimeRepository::class);
        $times->method('findById')->willReturn($this->time(99)); // dono e o usuario 99
        $times->expects($this->never())->method('addPokemon');

        $pokemons = $this->createStub(PokemonRepository::class);

        $this->assertAppError(403, fn() => (new TimeService($times, $pokemons))
            ->addPokemon(1, self::DONO, ['pokemon_id' => 25]));
    }

    public function testTimeInexistenteGera404(): void
    {
        $times = $this->createStub(TimeRepository::class);
        $times->method('findById')->willReturn(null);
        $pokemons = $this->createStub(PokemonRepository::class);

        $this->assertAppError(404, fn() => (new TimeService($times, $pokemons))->get(123, self::DONO));
    }

    public function testPokemonIdInvalidoGera422(): void
    {
        $times = $this->createStub(TimeRepository::class);
        $times->method('findById')->willReturn($this->time());
        $pokemons = $this->createStub(PokemonRepository::class);
        $service = new TimeService($times, $pokemons);

        foreach (['abc', 0, -1, null, 1.5, '1; DROP TABLE times'] as $valor) {
            $this->assertAppError(422, fn() => $service->addPokemon(1, self::DONO, ['pokemon_id' => $valor]));
        }
    }

    public function testRemoverPokemonQueNaoEstaNoTimeGera404(): void
    {
        $times = $this->createStub(TimeRepository::class);
        $times->method('findById')->willReturn($this->time());
        $times->method('removePokemon')->willReturn(0);
        $pokemons = $this->createStub(PokemonRepository::class);

        $this->assertAppError(404, fn() => (new TimeService($times, $pokemons))
            ->removePokemon(1, self::DONO, 25));
    }

    public function testNomeDeTimeInvalidoGera422(): void
    {
        $times = $this->createMock(TimeRepository::class);
        $times->expects($this->never())->method('create');
        $pokemons = $this->createStub(PokemonRepository::class);

        $this->assertAppError(422, fn() => (new TimeService($times, $pokemons))
            ->create(self::DONO, ['nome' => 'A']));
    }

    public function testCriarTimeSanitizaONome(): void
    {
        $times = $this->createMock(TimeRepository::class);
        $times->expects($this->once())->method('create')->with(self::DONO, 'Meu time')->willReturn(3);
        $pokemons = $this->createStub(PokemonRepository::class);

        $r = (new TimeService($times, $pokemons))->create(self::DONO, ['nome' => '<b>Meu time</b>']);

        $this->assertSame(3, $r['id']);
        $this->assertSame('Meu time', $r['nome']);
    }
}