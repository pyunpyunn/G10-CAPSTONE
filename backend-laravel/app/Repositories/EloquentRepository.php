<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class EloquentRepository implements RepositoryInterface
{
    /** @var TModel */
    protected Model $model;

    /** @param TModel $model */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find(string|int $id, array $relations = []): ?Model
    {
        return $this->model->newQuery()->with($relations)->find($id);
    }

    public function findOrFail(string|int $id, array $relations = []): Model
    {
        return $this->model->newQuery()->with($relations)->findOrFail($id);
    }

    public function all(array $relations = []): Collection
    {
        return $this->model->newQuery()->with($relations)->get();
    }

    public function paginate(int $perPage = 25, array $relations = []): LengthAwarePaginator
    {
        return $this->model->newQuery()->with($relations)->paginate($perPage);
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
