<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;

class MorphTo extends EloquentMorphTo
{
    /** @inheritdoc */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where(
                $this->ownerKey ?? $this->getForeignKeyName(),
                '=',
                $this->getForeignKeyFrom($this->parent),
            );
        }
    }

    /** @inheritdoc */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $ownerKey = $this->ownerKey ?? $instance->getKeyName();

        $query = $this->replayMacros($instance->newQuery())
                            ->mergeConstraintsFrom($this->getQuery())
                            ->with(array_merge(
                                $this->getQuery()->getEagerLoads(),
                                (array) ($this->morphableEagerLoads[get_class($instance)] ?? [])
                            ))
                            ->withCount(
                                (array) ($this->morphableEagerLoadCounts[get_class($instance)] ?? [])
                            );

        if ($callback = ($this->morphableConstraints[get_class($instance)] ?? null)) {
            $callback($query);
        }

        $whereIn = $this->whereInMethod($instance, $ownerKey);

        return $query->{$whereIn}(
            $ownerKey, $this->gatherKeysByType($type, $instance->getKeyType())
        )->get();
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param string $key
     *
     * @return string
     */
    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }
}
