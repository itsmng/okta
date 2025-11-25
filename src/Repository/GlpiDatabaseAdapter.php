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

use DBmysql;

/**
 * Adapter for the GLPI DBmysql class that implements DatabaseInterface.
 * 
 * This adapter allows the UserRepository to work with the real database
 * in production while enabling mock injection for testing.
 */
class GlpiDatabaseAdapter implements DatabaseInterface
{
    private DBmysql $db;

    /**
     * @param DBmysql $db The GLPI database connection
     */
    public function __construct(DBmysql $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query): iterable
    {
        return $this->db->query($query);
    }

    /**
     * {@inheritdoc}
     */
    public function escape(string $value): string
    {
        return $this->db->escape($value);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrDie(string $table, array $values, array $where): bool
    {
        return $this->db->updateOrDie($table, $values, $where);
    }

    /**
     * {@inheritdoc}
     */
    public function request(array $criteria): iterable
    {
        return $this->db->request($criteria);
    }
}
