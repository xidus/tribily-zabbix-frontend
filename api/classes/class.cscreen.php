<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing CScreen class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Screens
 */
class CScreen extends CZBXAPI{
/**
 * Get Screen data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['order'] deprecated parameter (for now)
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('screens' => 's.screenid'),
			'from' => array('screens' => 'screens s'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'screenids'					=> null,
			'screenitemids'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'filter'					=> null,
			'pattern'					=> '',
// OutPut
			'extendoutput'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'select_screenitems'		=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_screenitems'])){
				$options['select_screenitems'] = API_OUTPUT_EXTEND;
			}
		}

// editable + PERMISSION CHECK

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// screenids
		if(!is_null($options['screenids'])){
			zbx_value2array($options['screenids']);
			$sql_parts['where'][] = DBcondition('s.screenid', $options['screenids']);
		}

// screenitemids
		if(!is_null($options['screenitemids'])){
			zbx_value2array($options['screenitemids']);
			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['screenitemid'] = 'si.screenitemid';
			}
			$sql_parts['from']['screens_items'] = 'screens_items si';
			$sql_parts['where']['ssi'] = 'si.screenid=s.screenid';
			$sql_parts['where'][] = DBcondition('si.screenitemid', $options['screenitemids']);
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['screens'] = 's.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT s.screenid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(s.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['screenid'])){
				$sql_parts['where']['screenid'] = 's.screenid='.$options['filter']['screenid'];
			}
			if(isset($options['filter']['name'])){
				$sql_parts['where']['name'] = 's.name='.zbx_dbstr($options['filter']['name']);
			}
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 's.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('s.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('s.*', $sql_parts['select'])){
				$sql_parts['select'][] = 's.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$screenids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('s.screenid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($screen = DBfetch($res)){
			if(!is_null($options['count'])){
				$result = $screen;
			}
			else{
				$screenids[$screen['screenid']] = $screen['screenid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$screen['screenid']] = array('screenid' => $screen['screenid']);
				}
				else{
					if(!isset($result[$screen['screenid']])) $result[$screen['screenid']]= array();

					if(!is_null($options['select_screenitems']) && !isset($result[$screen['screenid']]['screenitems'])){
						$result[$screen['screenid']]['screenitems'] = array();
					}

					if(isset($screen['screenitemid']) && is_null($options['select_screenitems'])){
						if(!isset($result[$screen['screenid']]['screenitems']))
							$result[$screen['screenid']]['screenitems'] = array();

						$result[$screen['screenid']]['screenitems'][] = array('screenitemid' => $screen['screenitemid']);
						unset($screen['screenitemid']);
					}

					$result[$screen['screenid']] += $screen;
				}
			}
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){}
		else if(!empty($result)){
			$groups_to_check = array();
			$hosts_to_check = array();
			$graphs_to_check = array();
			$items_to_check = array();
			$maps_to_check = array();
			$screens_to_check = array();
			$screens_items = array();

			$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
			while($sitem = DBfetch($db_sitems)){
				if($sitem['resourceid'] == 0) continue;

				$screens_items[$sitem['screenitemid']] = $sitem;

				switch($sitem['resourcetype']){
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
						$groups_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_HOST_TRIGGERS:
						$hosts_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_GRAPH:
						$graphs_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$items_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_MAP:
						$maps_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SCREEN:
						$screens_to_check[] = $sitem['resourceid'];
					break;
				}
			}

			$groups_to_check = array_unique($groups_to_check);
			$hosts_to_check = array_unique($hosts_to_check);
			$graphs_to_check = array_unique($graphs_to_check);
			$items_to_check = array_unique($items_to_check);
			$maps_to_check = array_unique($maps_to_check);
			$screens_to_check = array_unique($screens_to_check);
/*
sdii($graphs_to_check);
sdii($items_to_check);
sdii($maps_to_check);
sdii($screens_to_check);
//*/
// group
			$group_options = array(
								'nodeids' => $nodeids,
								'groupids' => $groups_to_check,
								'editable' => $options['editable']);
			$allowed_groups = CHostgroup::get($group_options);
			$allowed_groups = zbx_objectValues($allowed_groups, 'groupid');

// host
			$host_options = array(
								'nodeids' => $nodeids,
								'hostids' => $hosts_to_check,
								'editable' => $options['editable']);
			$allowed_hosts = CHost::get($host_options);
			$allowed_hosts = zbx_objectValues($allowed_hosts, 'hostid');

// graph
			$graph_options = array(
								'nodeids' => $nodeids,
								'graphids' => $graphs_to_check,
								'editable' => $options['editable']);
			$allowed_graphs = CGraph::get($graph_options);
			$allowed_graphs = zbx_objectValues($allowed_graphs, 'graphid');

// item
			$item_options = array(
								'nodeids' => $nodeids,
								'itemids' => $items_to_check,
								'webitems' => 1,
								'editable' => $options['editable']);
			$allowed_items = CItem::get($item_options);
			$allowed_items = zbx_objectValues($allowed_items, 'itemid');
// map
			$map_options = array(
								'nodeids' => $nodeids,
								'sysmapids' => $maps_to_check,
								'editable' => $options['editable']);
			$allowed_maps = CMap::get($map_options);
			$allowed_maps = zbx_objectValues($allowed_maps, 'sysmapid');
// screen
			$screens_options = array(
								'nodeids' => $nodeids,
								'screenids' => $screens_to_check,
								'editable' => $options['editable']);
			$allowed_screens = CScreen::get($screens_options);
			$allowed_screens = zbx_objectValues($allowed_screens, 'screenid');


			$restr_groups = array_diff($groups_to_check, $allowed_groups);
			$restr_hosts = array_diff($hosts_to_check, $allowed_hosts);
			$restr_graphs = array_diff($graphs_to_check, $allowed_graphs);
			$restr_items = array_diff($items_to_check, $allowed_items);
			$restr_maps = array_diff($maps_to_check, $allowed_maps);
			$restr_screens = array_diff($screens_to_check, $allowed_screens);


/*
SDI('---------------------------------------');
SDII($restr_graphs);
SDII($restr_items);
SDII($restr_maps);
SDII($restr_screens);
SDI('/////////////////////////////////');
//*/
// group
			foreach($restr_groups as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO,SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW,SCREEN_RESOURCE_HOSTGROUP_TRIGGERS))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// host
			foreach($restr_hosts as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_HOST_TRIGGERS))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// graph
			foreach($restr_graphs as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_GRAPH)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// item
			foreach($restr_items as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// map
			foreach($restr_maps as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_MAP)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// screen
			foreach($restr_screens as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_SCREEN)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
		}

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}


// Adding ScreenItems
		if(!is_null($options['select_screenitems']) && str_in_array($options['select_screenitems'], $subselects_allowed_outputs)){
			if(!isset($screens_items)){
				$screens_items = array();
				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;
				}
			}

			foreach($screens_items as $snum => $sitem){
				if(!isset($result[$sitem['screenid']]['screenitems'])){
					$result[$sitem['screenid']]['screenitems'] = array();
				}

				$result[$sitem['screenid']]['screenitems'][] = $sitem;
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public static function getObjects($data){
		$options = array(
			'filter' => $data,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($data['node']))
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		else if(isset($data['nodeids']))
			$options['nodeids'] = $data['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($data){
		$options = array(
			'filter' => $data,
			'preservekeys' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1
		);

		if(isset($data['node']))
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		else if(isset($data['nodeids']))
			$options['nodeids'] = $data['nodeids'];

		$sysmaps = self::get($options);

	return !empty($sysmaps);
	}

	protected static function checkItems($screenitems){
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();
		$maps = array();
		$screens = array();

		foreach($screenitems as $item){
			if((isset($item['resourcetype']) && !isset($item['resourceid'])) ||
				(!isset($item['resourcetype']) && isset($item['resourceid']))){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
			switch($item['resourcetype']){
				case SCREEN_RESOURCE_HOSTS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					$hostgroups[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_HOST_TRIGGERS:
					$hosts[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_GRAPH:
					$graphs[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					$items[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_MAP:
					$maps[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_SCREEN:
					$screens[] = $item['resourceid'];
				break;
			}
		}

		if(!empty($hostgroups)){
			$result = CHostGroup::get(array(
				'groupids' => $hostgroups,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($hostgroups as $id){
				if(!isset($result[$id])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_HOSTGROUP);
			}
		}
		if(!empty($hosts)){
			$result = CHost::get(array(
				'hostids' => $hosts,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($hosts as $id){
				if(!isset($result[$id])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_HOST);
			}
		}
		if(!empty($graphs)){
			$result = CGraph::get(array(
				'graphids' => $graphs,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($graphs as $id){
				if(!isset($result[$id])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_GRAPH);
			}
		}
		if(!empty($items)){
			$result = CItem::get(array(
				'itemids' => $items,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
				'webitems' => 1,
			));
			foreach($items as $id){
				if(!isset($result[$id])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_ITEM);
			}
		}
		if(!empty($maps)){
			$result = CMap::get(array(
				'sysmapids' => $maps,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($maps as $id){
				if(!isset($result[$id])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_MAP);
			}
		}
		if(!empty($screens)){
			$result = self::get(array(
				'screenids' => $screens,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($screens as $id){
				if(!isset($result[$id])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_SCREEN);
			}
		}
	}

/**
 * Create Screen
 *
 * @param _array $screens
 * @param string $screens['name']
 * @param array $screens['hsize']
 * @param int $screens['vsize']
 * @return array
 */
	public static function create($screens){
		$screens = zbx_toArray($screens);
		$insert_screens = array();
		$insert_screen_items = array();

		try{
			self::BeginTransaction(__METHOD__);

			foreach($screens as $snum => $screen){
				$screen_db_fields = array('name' => null);
				if(!check_db_fields($screen_db_fields, $screen)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen [ '.$screen['name'].' ]');
				}

				$sql = 'SELECT screenid '.
					' FROM screens '.
					' WHERE name='.zbx_dbstr($screen['name']).
						' AND '.DBin_node('screenid', false);
				if(DBfetch(DBselect($sql))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

				$iscr = array('name' => $screen['name']);
				if(isset($screen['hsize'])) $iscr['hsize'] = $screen['hsize'];
				if(isset($screen['vsize'])) $iscr['vsize'] = $screen['vsize'];
				$insert_screens[$snum] = $iscr;
			}
			$screenids = DB::insert('screens', $insert_screens);

			foreach($screens as $snum => $screen){
				if(isset($screen['screenitems'])){
					foreach($screen['screenitems'] as $screenitem){
						$screenitem['screenid'] = $screenids[$snum];
						$insert_screen_items[] = $screenitem;
					}
				}
			}
			self::addItems($insert_screen_items);

			self::EndTransaction(true, __METHOD__);
			return array('screenids' => $screenids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update Screen
 *
 * @param _array $screens multidimensional array with Hosts data
 * @param string $screens['screenid']
 * @param int $screens['name']
 * @param int $screens['hsize']
 * @param int $screens['vsize']
 * @return boolean
 */
	public static function update($screens){
		$screens = zbx_toArray($screens);
		$update = array();

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'screenids' => zbx_objectValues($screens, 'screenid'),
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_screens = self::get($options);
			foreach($screens as $gnum => $screen){
				if(!isset($screen['screenid'], $upd_screens[$screen['screenid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			foreach($screens as $snum => $screen){
				if(isset($screen['name'])){
					$options = array(
						'filter' => array('name' => $screen['name']),
						'preservekeys' => 1,
						'nopermissions' => 1,
						'output' => API_OUTPUT_SHORTEN,
					);
					$exist_screens = self::get($options);
					$exist_screen = reset($exist_screens);

					if($exist_screen && ($exist_screen['screenid'] != $screen['screenid']))
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

				$screenid = $screen['screenid'];
				unset($screen['screenid']);
				if(!empty($screen)){
					$update[] = array(
						'values' => $screen,
						'where' => array('screenid='.$screenid),
					);
				}

				if(isset($screen['screenitems'])){
					$update_items = array(
						'screenids' => $screenid,
						'screenitems' => $screen['screenitems'],
					);
					self::updateItems($update_items);
				}
			}
			DB::update('screens', $update);

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete Screen
 *
 * @param array $screenids
 * @return boolean
 */
	public static function delete($screenids){
		$screenids = zbx_toArray($screenids);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'screenids' => $screenids,
				'editable' => 1,
				'preservekeys' => 1,
			);
			$del_screens = self::get($options);
			foreach($screenids as $screenid){
				if(!isset($del_screens[$screenid])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			DB::delete('screens_items', DBcondition('screenid', $screenids));
			DB::delete('screens_items', array(DBcondition('resourceid', $screenids), 'resourcetype='.SCREEN_RESOURCE_SCREEN));
			DB::delete('slides', DBcondition('screenid', $screenids));
			DB::delete('screens', DBcondition('screenid', $screenids));

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Add ScreenItem
 *
 * @param array $screenitems
 * @return boolean
 */
	protected static function addItems($screenitems){
		$insert = array();

		self::checkItems($screenitems);

		foreach($screenitems as $screenitem){
			$items_db_fields = array(
				'screenid' => null,
				'resourcetype' => null,
				'resourceid' => null,
				'x' => null,
				'y' => null,
			);
			if(!check_db_fields($items_db_fields, $screenitem)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen items');
			}

			$insert[] = $screenitem;
		}
		DB::insert('screens_items', $insert);
		return true;
	}

	protected static function updateItems($data){
		$screenids = zbx_toArray($data['screenids']);
		$insert = array();
		$update = array();
		$delete = array();


		self::checkItems($data['screenitems']);

		$options = array(
			'screenids' => $screenids,
			'nopermissions' => 1,
			'output' => API_OUTPUT_EXTEND,
			'select_screenitems' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
		);
		$screens = self::get($options);


		foreach($data['screenitems'] as $new_item){
			$items_db_fields = array(
				'x' => null,
				'y' => null,
			);
			if(!check_db_fields($items_db_fields, $new_item)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen items');
			}
		}

		foreach($screens as $screen){
			$new_items = $data['screenitems'];

			foreach($screen['screenitems'] as $cnum => $current_item){
				foreach($new_items as $nnum => $new_item){
					if(($current_item['x'] == $new_item['x']) && ($current_item['y'] == $new_item['y'])){

						$tmpupd = array(
							'where' => array(
								'screenid='.$screen['screenid'],
								'x='.$new_item['x'],
								'y='.$new_item['y']
							)
						);

						unset($new_item['screenid'], $new_item['screenitemid'], $new_item['x'], $new_item['y']);
						$tmpupd['values'] = $new_item;

						$update[] = $tmpupd;

						unset($screen['screenitems'][$cnum]);
						unset($new_items[$nnum]);
						break;
					}
				}
			}

			foreach($new_items as $new_item){
				$items_db_fields = array(
					'resourcetype' => null,
					'resourceid' => null,
				);
				if(!check_db_fields($items_db_fields, $new_item)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen items');
				}

				$new_item['screenid'] = $screen['screenid'];
				$insert[] = $new_item;
			}

			foreach($screen['screenitems'] as $del_item){
				$delete[] = $del_item['screenitemid'];
			}
		}

		if(!empty($insert)) DB::insert('screens_items', $insert);
		if(!empty($update)) DB::update('screens_items', $update);
		if(!empty($delete)) DB::delete('screens_items', DBcondition('screenitemid', $delete));

		return true;
	}

}
?>
