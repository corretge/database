<?php

/**
 * SQL Dialect for SQLite
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Flux_Database_Adapter_SQLite extends Flux_Database_Adapter
{
	public function generateDsn()
	{
		if (!isset($this->options['file'])) {
			throw new Exception('No filename specified for SQLite database.');
		}

		return 'sqlite:'.$this->options['file'];
	}

	public function setNames($charset)
	{
		$sql = 'PRAGMA encoding = '.$this->quote($charset);
		if ($this->exec($sql) === false)
			return;

		$this->charset = $charset;
	}

	public function runTruncate(Flux_Database_Query_Truncate $query)
	{
		if (empty($query->getTable()))
			throw new Exception('A TRUNCATE query must have a table specified.');

		// Reset sequence counter
		$sql = 'DELETE FROM sqlite_sequence WHERE name = '.$this->quote($query->getTable()).';';
		$sql .= 'DELETE FROM '.$query->getTable();

		return $this->prepare($sql);
	}

	public function runTableExists(Flux_Database_Query_TableExists $query)
	{
		$sql = 'SELECT 1 FROM sqlite_master WHERE name = \''.$query->getTable().'\' AND type=\'table\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$query->getTable().'_'.$query->index.' ON '.$query->getTable().' ('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runIndexExists(Flux_Database_Query_IndexExists $query)
	{
		$sql = 'SELECT 1 FROM sqlite_master WHERE name = \''.$query->getTable().'_'.$query->index.'\' AND tbl_name = \''.$query->getTable().'\' AND type=\'index\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runDropIndex(Flux_Database_Query_DropIndex $query)
	{
		$sql = 'DROP INDEX '.$query->getTable().'_'.$query->index;
		return $this->exec($sql);
	}

	public function runAlterField(Flux_Database_Query_AlterField $query)
	{
		// SQLite does not need to change the type of the column, as long as the values are according to the type
		return true;
	}

	public function runFieldExists(Flux_Database_Query_FieldExists $query)
	{
		$result = $this->query('PRAGMA table_info('.$query->getTable().')');
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			if ($row['name'] == $query->field)
			{
				return true;
			}
		}
		return false;
	}

	public function runDropField(Flux_Database_Query_DropField $query)
	{
		// Fetch table SQL
		$result = $this->query('SELECT sql FROM sqlite_master WHERE type = \'table\' AND tbl_name = \''.$query->getTable().'\'');

		$table_sql = $result->fetchColumn();
		if ($table_sql == NULL)
		{
			return false;
		}

		// Create temporary table
		$now = time();
		$tmptable_sql = str_replace('CREATE TABLE '.$query->getTable().' (', 'CREATE TABLE '.$query->getTable().'_t'.$now.' (', $table_sql);
		$this->exec($tmptable_sql);

		$this->exec('INSERT INTO '.$query->getTable().'_t'.$now.' SELECT * FROM '.$query->getTable());

		$table = $this->tableInfo($query->getTable())->run();

		unset($table['columns'][$query->field]);
		$new_columns = array_keys($table['columns']);

		$new_sql = 'CREATE TABLE '.$query->getTable().' (';

		foreach ($table['columns'] as $cur_column => $column)
		{
			$new_sql .= "\n".$cur_column.' '.$column['type'].(!empty($column['default']) ? ' DEFAULT '.$column['default'] : '').($column['allow_null'] ? '' : ' NOT NULL').',';
		}

		// TODO!
		if (isset($table['unique']))
			$new_sql .= "\n".$table['unique'].',';

		if (!empty($table['primary_key']))
			$new_sql .= "\n".'PRIMARY KEY ('.$table['primary_key'].'),';

		$new_sql = trim($new_sql, ',')."\n".');';

		// Drop old table
		$this->exec('DROP TABLE '.$query->getTable());

		// Create new table
		$this->exec($new_sql);

		// Recreate indexes
		if (!empty($table['indices']))
		{
			foreach ($table_indices as $index_name => $cur_index)
			{
				if (!in_array($query->field, $cur_index['fields']))
				{
					$this->dropIndex($query->getTable(), $index_name)->run();
				}
			}
		}

		// Copy content back
		$this->exec('INSERT INTO '.$query->getTable().' SELECT '.implode(', ', $new_columns).' FROM '.$query->getTable().'_t'.$now);

		$this->exec('DROP TABLE '.$query->getTable().'_t'.$now);

		// TODO: Handle query errors
		return true;
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$query->getTable().'_'.$query->index.' ON '.$query->getTable().'('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runTableInfo(Flux_Database_Query_TableInfo $query)
	{
		$result = $this->query('SELECT sql FROM sqlite_master WHERE tbl_name = \''.$query->getTable().'\' ORDER BY type DESC');

		$rows = $result->fetchAll(PDO::FETCH_ASSOC);
		$num_rows = count($rows);

		if ($num_rows == 0)
			return;

		$table = array(
			'indices'	=> array(),
			'sql'		=> '',
			'columns'	=> array(),
		);
		foreach ($rows as $cur_index)
		{
			if (empty($cur_index['sql']))
				continue;

			if (empty($table['sql']))
				$table['sql'] = $cur_index['sql'];
			else
				$table['indices'][] = $cur_index['sql'];
		}

		// Work out the columns in the table currently
		$result = $this->query('PRAGMA table_info('.$query->getTable().')');
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$table['columns'][$row['name']] = $row;
		}

		// TODO: Primary key etc.
		return $table;
	}

	protected function compileColumnSerial($name)
	{
		return $name.' INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT';
	}

	protected function compileLimitOffset($limit, $offset)
	{
		$sql = '';

		if ($offset > 0 && $limit == 0)
			$limit = -1;

		if ($limit > 0)
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

		return $sql;
	}
}
