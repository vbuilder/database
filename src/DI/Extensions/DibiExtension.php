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

namespace vBuilder\DI\Extensions;

use Nette\InvalidStateException;
use Nette\DI\CompilerExtension;
use vBuilder\Utils\FileSystem;

/**
 * Extended DibiExtension. Provides any necessary information
 * for TableManager.
 *
 * @see DibiNette21Extension
 *
 * @author Adam Staněk (velbloud)
 * @since Jul 22, 2014
 */
class DibiExtension extends CompilerExtension {

	public function loadConfiguration() {

		$container = $this->getContainerBuilder();
		$config = $this->getConfig();

		$useProfiler = isset($config['profiler'])
			? $config['profiler']
			: $container->parameters['debugMode'];

		unset($config['profiler']);

		if (isset($config['flags'])) {
			$flags = 0;
			foreach ((array) $config['flags'] as $flag) {
				$flags |= constant($flag);
			}
			$config['flags'] = $flags;
		}

		$tableManagerConfig = array_intersect_key($config, array_flip(array(
			'tables',
			'requiredTables'
		)));

		$connection = $container->addDefinition($this->prefix('connection'))
			->setClass('DibiConnection', array(array_diff_key($config, $tableManagerConfig)));

		if ($useProfiler) {
			$panel = $container->addDefinition($this->prefix('panel'))
				->setClass('DibiNettePanel')
				->addSetup('Nette\Diagnostics\Debugger::getBar()->addPanel(?)', array('@self'))
				->addSetup('Nette\Diagnostics\Debugger::getBlueScreen()->addPanel(?)', array('DibiNettePanel::renderException'));

			$connection->addSetup('$service->onEvent[] = ?', array(array($panel, 'logEvent')));
		}

		// TableManager service
		$tmDef = $container->addDefinition($this->prefix('tableManager'))
			->setClass('vBuilder\\Database\\TableManager')
			->addSetup(
				'$service->setScriptDirPath(?)',
				array(FileSystem::normalizePath(
					$this->getContainerBuilder()->expand('%appDir%/../db')
				))
			);

		// Add all required tables and their definition
		if(isset($tableManagerConfig['requiredTables'])) {
			$requiredTables = (array) $tableManagerConfig['requiredTables'];
			foreach($requiredTables as $table) {

				$tableConfig = array(
					'structure' => NULL,
					'data' => NULL
				);

				// Defined table
				if(isset($tableManagerConfig['tables'][$table])) {
					// table_name: { structure: script1.sql, data: script2.sql }
					if(is_array($tableManagerConfig['tables'][$table])) {
						$tableConfig = array_intersect_key(
							$tableManagerConfig['tables'][$table],
							array_flip(array('structure', 'data'))
						);

						if(isset($db->config['tables'][$table]['structure']))
							$tableConfig['structure'] = $db->config['tables'][$table]['structure'];

					// table_name: script.sql
					} else {
						$tableConfig['structure'] = $tableManagerConfig['tables'][$table];
					}
				}

				// Normalize paths
				if($tableConfig['structure'])
					$tableConfig['structure'] = FileSystem::normalizePath($tableConfig['structure']);

				if($tableConfig['data'])
					$tableConfig['data'] = FileSystem::normalizePath($tableConfig['data']);

				$tmDef->addSetup(
					'$service->setTableScripts(?, ?, ?)',
					array($table, $tableConfig['structure'], $tableConfig['data'])
				);
			}
		}
	}

}
