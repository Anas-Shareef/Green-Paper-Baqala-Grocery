<?php

namespace App\Database;

use DateTimeInterface;
use Illuminate\Database\PostgresConnection as BasePostgresConnection;

class PostgresConnection extends BasePostgresConnection
{
    /**
     * Prepare query bindings for execution.
     *
     * In PostgreSQL with Supabase Pooler (PgBouncer transaction mode), PDO::ATTR_EMULATE_PREPARES
     * is enabled. Under emulated prepares, PDO inlines bindings without native type negotiation.
     * Laravel's base Connection converts booleans to integer 1 / 0, causing:
     * "SQLSTATE[42804]: Datatype mismatch: ERROR: column is of type boolean but expression is of type integer".
     *
     * Converting booleans to 'true' and 'false' strings ensures PDO quotes them correctly and
     * PostgreSQL coerces them safely to boolean values across all models, inserts, updates, and queries.
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return $bindings;
    }
}
