<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Small, model-agnostic persistence boundary. Domain repositories extend this
 * contract with meaningful queries; controllers never depend on it directly.
 *
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /** @return TModel|null */
    public function find(string|int $id, array $relations = []): ?Model;

    /** @return TModel */
    public function findOrFail(string|int $id, array $relations = []): Model;

    /** @return Collection<int, TModel> */
    public function all(array $relations = []): Collection;

    /** @return LengthAwarePaginator<TModel> */
    public function paginate(int $perPage = 25, array $relations = []): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes @return TModel */
    public function create(array $attributes): Model;

    /** @param array<string, mixed> $attributes @return TModel */
    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
