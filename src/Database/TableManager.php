<?php

/**
 * This file is part of vBuilder Framework (vBuilder FW).
 *
 * Copyright (c) 2011 Adam Staněk <adam.stanek@v3net.cz>
 *
 * For more information visit http://www.vbuilder.cz
 *
 * vBuilder FW is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * vBuilder FW is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with vBuilder FW. If not, see <http://www.gnu.org/licenses/>.
 */

namespace vBuilder\Database;

use Nette\Utils\Strings;

/**
 * Database table manager.
 *
 * Provides information about required tables and their DDL scripts.
 *
 * @author Adam Staněk (velbloud)
 * @since Jul 22, 2014
 */
class TableManager {

	/** @var string */
	private $scriptDirPath;

	/** @var array */
	private $tables = array();

	/**
	 * Sets path to directory with SQL scripts
	 *
	 * @param string path
	 * @return self
	 */
	function setScriptDirPath($path) {
		$this->scriptDirPath = $path;
		return $this;
	}

	/**
	 * Sets path to SQL scripts for given table.
	 *
	 * @param string table name
	 * @param string path to DDL script
	 * @param string path to data script
	 *
	 * @return self
	 */
	function setTableScripts($tableName, $ddlScript, $dataScript = FALSE) {
		$this->tables[$tableName] = array(
			'structure' => $ddlScript,
			'data' => $dataScript
		);

		return $this;
	}

	/**
	 * Returns array of all defined (required) tables.
	 *
	 * @return string[]
	 */
	function getTables() {
		return array_keys($this->tables);
	}

	/**
	 * Returns path to DDL script.
	 *
	 * @param string table name
	 * @return string path
	 */
	function getDdlScript($tableName) {
		if(isset($this->tables[$tableName]) && $this->tables[$tableName]['structure'] !== NULL)
			return $this->tables[$tableName]['structure'];

		return $this->scriptDirPath . '/' . Strings::webalize($tableName, '_', false) . '.sql';
	}

	/**
	 * Returns path to data script.
	 *
	 * @param string table name
	 * @return string path
	 */
	function getDataScript($tableName) {
		if(isset($this->tables[$tableName]) && $this->tables[$tableName]['data'] !== NULL)
			return $this->tables[$tableName]['data'];

		return $this->scriptDirPath . '/' . Strings::webalize($tableName, '_', false) . '.data.sql';
	}

}
