<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class CompatibilityBuilder extends Builder
{
    protected array $mappings = [];

    public function setMappings(array $mappings): self
    {
        $this->mappings = $mappings;
        return $this;
    }

    protected function mapColumn($column)
    {
        if (is_string($column)) {
            $parts = explode('.', $column);
            $colName = end($parts);
            if (array_key_exists($colName, $this->mappings)) {
                $parts[count($parts) - 1] = $this->mappings[$colName];
                return implode('.', $parts);
            }
        }
        return $column;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            $newColumn = [];
            foreach ($column as $key => $val) {
                $newColumn[$this->mapColumn($key)] = $val;
            }
            $column = $newColumn;
        } else {
            $column = $this->mapColumn($column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        return parent::whereIn($this->mapColumn($column), $values, $boolean, $not);
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return parent::whereNotIn($this->mapColumn($column), $values, $boolean);
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        if (is_array($columns)) {
            $columns = array_map([$this, 'mapColumn'], $columns);
        } else {
            $columns = $this->mapColumn($columns);
        }
        return parent::whereNull($columns, $boolean, $not);
    }

    public function whereNotNull($columns, $boolean = 'and')
    {
        if (is_array($columns)) {
            $columns = array_map([$this, 'mapColumn'], $columns);
        } else {
            $columns = $this->mapColumn($columns);
        }
        return parent::whereNotNull($columns, $boolean);
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and')
    {
        return parent::whereColumn($this->mapColumn($first), $operator, $this->mapColumn($second), $boolean);
    }

    public function orderBy($column, $direction = 'asc')
    {
        return parent::orderBy($this->mapColumn($column), $direction);
    }

    public function pluck($column, $key = null)
    {
        return parent::pluck(
            $this->mapColumn($column),
            $key ? $this->mapColumn($key) : null
        );
    }
}
