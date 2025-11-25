<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2025 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of ITSM-NG.
 *
 * ITSM-NG is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ITSM-NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ITSM-NG. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Okta\Repository;

/**
 * Interface for database operations.
 * 
 * This interface abstracts database operations to enable unit testing
 * without requiring a real database connection.
 */
interface DatabaseInterface
{
    /**
     * Execute a SQL query.
     *
     * @param string $query The SQL query to execute
     *
     * @return iterable Query result that can be iterated
     */
    public function query(string $query): iterable;

    /**
     * Escape a string for safe SQL usage.
     *
     * @param string $value The value to escape
     *
     * @return string The escaped value
     */
    public function escape(string $value): string;

    /**
     * Update a record or die on failure.
     *
     * @param string $table  The table name
     * @param array  $values The values to update
     * @param array  $where  The where conditions
     *
     * @return bool True on success
     */
    public function updateOrDie(string $table, array $values, array $where): bool;

    /**
     * Execute a query using the request builder pattern.
     *
     * @param array $criteria Query criteria (SELECT, FROM, WHERE, etc.)
     *
     * @return iterable Query result that can be iterated
     */
    public function request(array $criteria): iterable;
}
