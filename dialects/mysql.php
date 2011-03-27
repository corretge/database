<?php

/**
 * SQL Dialect for MySQL
 * 
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLDialect_MySQL extends SQLDialect
{
	protected function create_table(CreateTableQuery $query)
	{
		$sql = parent::create_table($query);

		if (!empty($this->charset))
			$sql .= ' CHARSET = '.$this->db->quote($this->charset);

		return $sql;
	}
}