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

if(!isset($DB)){
	$DB = array();
	if(isset($DB_TYPE))		$DB['TYPE'] 	= $DB_TYPE;
	if(isset($DB_SERVER)) 	$DB['SERVER'] 	= $DB_SERVER;
	if(isset($DB_PORT))		$DB['PORT'] 	= $DB_PORT;
	if(isset($DB_DATABASE))	$DB['DATABASE'] = $DB_DATABASE;
	if(isset($DB_USER))		$DB['USER'] 	= $DB_USER;
	if(isset($DB_PASSWORD))	$DB['PASSWORD'] = $DB_PASSWORD;
}

	function DBconnect(&$error){
		$result = true;

		global $DB;

		$DB['DB'] = null;
		$DB['TRANSACTIONS'] = 0;

//Stats
		$DB['SELECT_COUNT'] = 0;
		$DB['EXECUTE_COUNT'] = 0;

//SDI('type: '.$DB['TYPE'].'; server: '.$DB['SERVER'].'; port: '.$DB['PORT'].'; db: '.$DB['DATABASE'].'; usr: '.$DB['USER'].'; pass: '.$DB['PASSWORD']);

		if(!isset($DB['TYPE'])){
			$error = "Unknown database type.";
			$result = false;
		}
		else{
			$DB['TYPE'] = zbx_strtoupper($DB['TYPE']);

			switch($DB['TYPE']){
				case 'MYSQL':
					$mysql_server = $DB['SERVER'].( !empty($DB['PORT']) ? ':'.$DB['PORT'] : '');

					if (!$DB['DB']= mysql_connect($mysql_server,$DB['USER'],$DB['PASSWORD'])){
						$error = 'Error connecting to database ['.mysql_error().']';
						$result = false;
					}
					else{
						if (!mysql_select_db($DB['DATABASE'])){
							$error = 'Error database in selection ['.mysql_error().']';
							$result = false;
						}
						else{
							DBexecute('SET NAMES utf8');
							DBexecute('SET CHARACTER SET utf8');
						}
					}
					break;
				case 'POSTGRESQL':
					$pg_connection_string =
						( !empty($DB['SERVER']) ? 'host=\''.$DB['SERVER'].'\' ' : '').
						'dbname=\''.$DB['DATABASE'].'\' '.
						( !empty($DB['USER']) ? 'user=\''.$DB['USER'].'\' ' : '').
						( !empty($DB['PASSWORD']) ? 'password=\''.$DB['PASSWORD'].'\' ' : '').
						( !empty($DB['PORT']) ? 'port='.$DB['PORT'] : '');

					$DB['DB']= pg_connect($pg_connection_string);
					if(!$DB['DB']){
						$error = 'Error connecting to database';
						$result = false;
					}
					break;
				case 'ORACLE':
					$connect = '';
					if (!empty($DB['SERVER'])){
						$connect = '//'.$DB['SERVER'];

						if ($DB['PORT'] != '0')
							$connect .= ':'.$DB['PORT'];

						if ($DB['DATABASE'])
							$connect .= '/'.$DB['DATABASE'];
					}

					$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], $connect);
//					$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST='.$DB['SERVER'].')(PORT=1521))(CONNECT_DATA=(SERVICE_NAME='.$DB['DATABASE'].')))');
					if(!$DB['DB']){
						$error = 'Error connecting to database';
						$result = false;
					}
					break;
				case 'SQLITE3':
					$DB['TRANSACTIONS'] = 0;
					if(!function_exists('init_db_access')){
						function init_db_access(){
							global $DB, $ZBX_SEM_ID;

							$ZBX_SEM_ID = false;
							if(function_exists('ftok') && function_exists('sem_get') && file_exists($DB['DATABASE'])){
								$ZBX_SEM_ID = sem_get(ftok($DB['DATABASE'], 'z'), 1);
							}
						}
					}

					if(!function_exists('lock_db_access')){
						function lock_db_access(){
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_acquire')){
								sem_acquire($ZBX_SEM_ID);
							}
						}
					}

					if(!function_exists('unlock_db_access')){
						function unlock_db_access(){
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_release'))
								sem_release($ZBX_SEM_ID);
						}
					}

					if(!function_exists('free_db_access')){
						function free_db_access(){
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_remove'))
								sem_remove($ZBX_SEM_ID);

							$ZBX_SEM_ID = false;
						}
					}


					if(file_exists($DB['DATABASE'])){
						$DB['DB']= sqlite3_open($DB['DATABASE']);
						if(!$DB['DB']){
							$error = 'Error connecting to database';
							$result = false;
						}
					}
					else{
						$error = 'Missing database';
						$result = false;
					}

					init_db_access();
					break;
				default:
					$error = 'Unsupported database';
					$result = false;
			}
		}
		if( false == $result )
			$DB['DB']= null;

		return $result;
	}

	function DBclose(){
		global $DB;
		$result = false;

		if( isset($DB['DB']) && !empty($DB['DB']) ){
			switch($DB['TYPE']){
				case 'MYSQL':
					$result = mysql_close($DB['DB']);
					break;
				case 'POSTGRESQL':
					$result = pg_close($DB['DB']);
					break;
				case 'ORACLE':
					$result = ocilogoff($DB['DB']);
					break;
				case 'SQLITE3':
					$result = true;
					sqlite3_close($DB['DB']);
					free_db_access();
					break;
				default:		break;
			}
		}

		unset(
			$GLOBALS['DB'],
			$GLOBALS['DB_TYPE'],
			$GLOBALS['DB_SERVER'],
			$GLOBALS['DB_PORT'],
			$GLOBALS['DB_DATABASE'],
			$GLOBALS['DB_USER'],
			$GLOBALS['DB_PASSWORD'],
			$GLOBALS['SQLITE_TRANSACTION']
			);

		return $result;
	}

	function DBloadfile($file, &$error){
		global $DB;

		if(!file_exists($file)){
			$error = 'DBloadfile. Missing file['.$file.']';
			return false;
		}

		$fl = file($file);

		foreach($fl as $n => $l) if(substr($l,0,2)=='--') unset($fl[$n]);

		$fl = explode(";\n", implode("\n",$fl));
		unset($fl[count($fl)-1]);

		foreach($fl as $sql){
			if(empty($sql)) continue;

			if(!DBexecute($sql,0)){
				$error = '';
				return false;
			}
		}
		return true;
	}

	function DBstart($strict=true){
		global $DB;
//SDI('DBStart(): '.$DB['TRANSACTIONS']);
		$DB['STRICT'] = $strict;

		$DB['TRANSACTIONS']++;

		if($DB['TRANSACTIONS']>1){
			info('POSSIBLE ERROR: Used incorrect logic in database processing, started subtransaction!');
		return $DB['TRANSACTION_STATE'];
		}

		$DB['TRANSACTION_STATE'] = true;

		$result = false;
		if(isset($DB['DB']) && !empty($DB['DB']))
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = DBexecute('begin');
				break;
			case 'POSTGRESQL':
				$result = DBexecute('begin');
				break;
			case 'ORACLE':
				$result = true;
// TODO			OCI_DEFAULT
				break;
			case 'SQLITE3':
				if(1 == $DB['TRANSACTIONS']){
					lock_db_access();
					$result = DBexecute('begin');
				}
				break;
		}
	return $result;
	}


	function DBend($result=null){
		global $DB;
//SDI('DBend(): '.$DB['TRANSACTIONS']);
		if($DB['TRANSACTIONS'] != 1){
			$DB['TRANSACTIONS']--;

			if($DB['TRANSACTIONS'] < 1){
				$DB['TRANSACTIONS'] = 0;
				$DB['TRANSACTION_STATE'] = false;
				info('POSSIBLE ERROR: Used incorrect logic in database processing, transaction not started!');
			}

		if(!is_null($result))
			$DB['TRANSACTION_STATE'] = $result && $DB['TRANSACTION_STATE'];

		return $DB['TRANSACTION_STATE'];
		}

		$DB['TRANSACTIONS'] = 0;

		if(is_null($result)){
			$DBresult = $DB['TRANSACTION_STATE'];
		}
		else{
			$DBresult = $result && $DB['TRANSACTION_STATE'];
		}

//SDI('Result: '.$result);

		if($DBresult){ // OK
			$DBresult = DBcommit();
		}

		if(!$DBresult){ // FAIL
			DBrollback();
		}

		$result = (!is_null($result) && $DBresult)?$result:$DBresult;

	return $result;
	}

	function DBcommit(){
		global $DB;

		$result = false;
		if( isset($DB['DB']) && !empty($DB['DB']) )
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = DBexecute('commit');
				break;
			case 'POSTGRESQL':
				$result = DBexecute('commit');
				break;
			case 'ORACLE':
				$result = ocicommit($DB['DB']);

				break;
			case 'SQLITE3':
				$result = DBexecute('commit');
				unlock_db_access();
				break;
		}

	return $result;
	}

	function DBrollback(){
		global $DB;

		$result = false;
		if( isset($DB['DB']) && !empty($DB['DB']) )
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = DBexecute('rollback');
				break;
			case 'POSTGRESQL':
				$result = DBexecute('rollback');
				break;
			case 'ORACLE':
				$result = ocirollback($DB['DB']);
				break;
			case 'SQLITE3':
				$result = DBexecute('rollback');
				unlock_db_access();
				break;
		}

	return $result;
	}

/* NOTE:
	LIMIT and OFFSET records

	Example: select 6-15 row.

	MySQL:
		SELECT a FROM tbl LIMIT 5,10
		SELECT a FROM tbl LIMIT 10 OFFSET 5
	PostgreSQL:
		SELECT a FROM tbl LIMIT 10 OFFSET 5
	Oracle:
		SELECT a FROM tbe WHERE ROWNUM < 15 // ONLY < 15
		SELECT * FROM (SELECT ROWNUM as RN, * FROM tbl) WHERE RN BETWEEN 6 AND 15
//*/

	function &DBselect($query, $limit='NO', $offset=0){
		global $DB;

		$time_start=microtime(true);
		$result = false;

		if( isset($DB['DB']) && !empty($DB['DB']) ){
			$DB['SELECT_COUNT']++;
//SDI('SQL['.$DB['SELECT_COUNT'].']: '.$query);
			switch($DB['TYPE']){
				case 'MYSQL':
					if(zbx_numeric($limit)){
						$query .= ' LIMIT '.intval($limit).' OFFSET '.intval($offset);
					}

					$result=mysql_query($query,$DB['DB']);
					if(!$result){
						error('Error in query ['.$query.'] ['.mysql_error().']');
					}
					break;
				case 'POSTGRESQL':
					if(zbx_numeric($limit)){
						$query .= ' LIMIT '.intval($limit).' OFFSET '.intval($offset);
					}

					$result = pg_query($DB['DB'],$query);
					if(!$result){
						error('Error in query ['.$query.'] ['.pg_last_error().']');
					}
					break;
				case 'ORACLE':
					if(zbx_numeric($limit)){
						$till = $offset + $limit;
						$query = 'SELECT * FROM ('.$query.') WHERE rownum BETWEEN '.intval($offset).' AND '.intval($till);
					}

					$result=OCIParse($DB['DB'],$query);
					if(!$result){
						$e=@ocierror();
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else if(!@OCIExecute($result,($DB['TRANSACTIONS']?OCI_DEFAULT:OCI_COMMIT_ON_SUCCESS))){
						$e=ocierror($result);
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}

					break;
				case 'SQLITE3':
					if(!$DB['TRANSACTIONS']){
						lock_db_access();
					}

					if(zbx_numeric($limit)){
						$query .= ' LIMIT '.intval($limit).' OFFSET '.intval($offset);
					}

					if(!$result = sqlite3_query($DB['DB'],$query)){
						error('Error in query ['.$query.'] ['.sqlite3_error($DB['DB']).']');
					}
					else{
						$data = array();

						while($row = sqlite3_fetch_array($result)){
							foreach($row as $id => $name){
								if(!zbx_strstr($id,'.')) continue;
								$ids = explode('.',$id);
								$row[array_pop($ids)] = $row[$id];
								unset($row[$id]);
							}
							$data[] = $row;
						}

						sqlite3_query_close($result);

						$result = &$data;
					}
					if(!$DB['TRANSACTIONS']){
						unlock_db_access();
					}
					break;
			}

			if($DB['TRANSACTIONS'] && !$result){
				$DB['TRANSACTION_STATE'] &= $result;
			}
		}
COpt::savesqlrequest(microtime(true)-$time_start,$query);

	return $result;
	}

	function DBexecute($query, $skip_error_messages=0){
		global $DB;
		$result = false;

		$time_start=microtime(true);
		if( isset($DB['DB']) && !empty($DB['DB']) ){
			$DB['EXECUTE_COUNT']++;
//SDI('SQL xec: '.$query);

			switch($DB['TYPE']){
				case 'MYSQL':
					$result = mysql_query($query,$DB['DB']);
					if(!$result){
						error('Error in query ['.$query.'] ['.mysql_error().']');
					}
					break;
				case 'POSTGRESQL':
					$result = (bool) pg_query($DB['DB'],$query);
					if(!$result){
						error('Error in query ['.$query.'] ['.pg_last_error().']');
					}
					break;
				case 'ORACLE':
					$result=OCIParse($DB['DB'],$query);
					if(!$result){
						$e=@ocierror();
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else if(!@OCIExecute($result,($DB['TRANSACTIONS']?OCI_DEFAULT:OCI_COMMIT_ON_SUCCESS))){
						$e=ocierror($result);
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else{
						/* It should be here. The function must return boolen */
						$result = true;
					}

					break;
				case 'SQLITE3':
					if(!$DB['TRANSACTIONS']){
						lock_db_access();
					}

					$result = sqlite3_exec($DB['DB'], $query);
					if(!$result){
						error('Error in query ['.$query.'] ['.sqlite3_error($DB['DB']).']');
					}

					if(!$DB['TRANSACTIONS']){
						unlock_db_access();
					}
					break;
			}

			if($DB['TRANSACTIONS'] && !$result){
				$DB['TRANSACTION_STATE'] &= $result;
			}
		}
COpt::savesqlrequest(microtime(true)-$time_start,$query);
	return (bool) $result;
	}

	function DBfetch(&$cursor){
		global $DB;

		$result = false;

		if(isset($DB['DB']) && !empty($DB['DB']))
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = mysql_fetch_assoc($cursor);
				if(!$result){
					mysql_free_result($cursor);
				}
				break;
			case 'POSTGRESQL':
				$result = pg_fetch_assoc($cursor);
				if(!$result){
					pg_free_result($cursor);
				}
				break;
			case 'ORACLE':
				if(ocifetchinto($cursor, $row, (OCI_ASSOC+OCI_RETURN_NULLS))){
					$result = array();
					foreach($row as $key => $value){
						$field_type = zbx_strtolower(oci_field_type($cursor,$key));
						$value = (str_in_array($field_type,array('varchar','varchar2','blob','clob')) && is_null($value))? '':$value;

						if(is_object($value) && (zbx_stristr($field_type, 'lob') !== false)){
							$value = $value->load();
						}

						$result[zbx_strtolower($key)] = $value;
					}
				}
				break;
			case 'SQLITE3':
				if($cursor){
					$result = array_shift($cursor);
					if(is_null($result)) $result = false;
				}
				break;
		}
/*
		if($result === false){
			switch($DB['TYPE']){
				case 'MYSQL': mysql_free_result($cursor); break;
				case 'POSTGRESQL': pg_free_result($cursor); break;
				case 'ORACLE': oci_free_statement($cursor); break;
			}
		}
//*/
	return $result;
	}

// string value prepearing
if(isset($DB['TYPE']) && $DB['TYPE'] == 'ORACLE') {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".preg_replace('/\'/','\'\'',$value)."'";
			return $var;
		}

	return "'".preg_replace('/\'/','\'\'',$var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS NUMBER(20)) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == "MYSQL") {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".mysql_real_escape_string($value)."'";
			return $var;
		}

	return "'".mysql_real_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS UNSIGNED) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == "POSTGRESQL") {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".pg_escape_string($value)."'";
			return $var;
		}

	return "'".pg_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
}
else {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".addslashes($value)."'";
			return $var;
		}

	return "'".addslashes($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
}

	function zbx_dbconcat($params){
		global $DB;

		switch($DB['TYPE']){
			case "SQLITE3":
				return implode(' || ',$params);
			default:
				return 'CONCAT('.implode(',',$params).')';
		}
	}

	function zbx_sql_mod($x,$y){
		global $DB;

		switch($DB['TYPE']){
			case "SQLITE3":
				return ' ('.$x.' %% '.$y.')';
			default:
				return ' MOD('.$x.','.$y.')';
		}
	}

	function DBid2nodeid($id_name){
		global $DB;

		switch($DB['TYPE']){
			case "MYSQL":
				$result = '('.$id_name.' div 100000000000000)';
				break;
			case "ORACLE":
				$result = 'round('.$id_name.'/100000000000000)';
				break;
			default:
				$result = '('.$id_name.'/100000000000000)';
		}
		return $result;
	}

	function id2nodeid($id_var){
		return (int)bcdiv("$id_var",'100000000000000');
	}

	function DBin_node($id_name, $nodes = null){
		if(is_null($nodes))	$nodes = get_current_nodeid();
		else if(is_bool($nodes)) $nodes = get_current_nodeid($nodes);

		if(empty($nodes)){
			$nodes = array(0);
		}
		else if(!is_array($nodes)){
			if(is_string($nodes)){
				if(!preg_match('/^([0-9,]+)$/', $nodes))
					fatal_error('Incorrect "nodes" for "DBin_node". Passed ['.$nodes.']');
			}
			else if(!zbx_ctype_digit($nodes)){
				fatal_error('Incorrect type of "nodes" for "DBin_node". Passed ['.gettype($nodes).']');
			}

			$nodes = zbx_toArray($nodes);
		}

		$sql = '';
		foreach($nodes as $nnum => $nodeid){
			$sql.= '('.$id_name.'  BETWEEN '.$nodeid.'00000000000000 AND '.$nodeid.'99999999999999)';
			$sql.= ' OR ';
		}

		$sql = '('.trim($sql, 'OR ').')';
	return $sql;
	}

	function in_node( $id_var, $nodes = null ){
		if(is_null($nodes))
			$nodes = get_current_nodeid();

		if(empty($nodes))
			$nodes = 0;

		if(zbx_numeric($nodes)){
			$nodes = array($nodes);
		}
		else if(is_string($nodes)){
			if(!preg_match('/^([0-9,]+)$/', $nodes))
				fatal_error('Incorrect "nodes" for "in_node". Passed ['.$nodes.']');

			$nodes = explode(',', $nodes);
		}
		else if(!is_array($nodes)){
			fatal_error('Incorrect type of "nodes" for "in_node". Passed ['.gettype($nodes).']');
		}

	return uint_in_array(id2nodeid($id_var), $nodes);
	}

	function get_dbid($table,$field){
// PGSQL on transaction failure on all queries returns false..
		global $DB, $ZBX_LOCALNODEID;
		if(($DB['TYPE'] == 'POSTGRESQL') && $DB['TRANSACTIONS'] && !$DB['TRANSACTION_STATE']) return 0;
//------
		$nodeid = get_current_nodeid(false);

		$found = false;
		do{
			$min=bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000'));
			$max=bcadd(bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000')),'99999999999');
			$row = DBfetch(DBselect('SELECT nextid FROM ids WHERE nodeid='.$nodeid .' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field)));
			if(!$row){
				$row = DBfetch(DBselect('SELECT max('.$field.') AS id FROM '.$table.' WHERE '.$field.'>='.$min.' AND '.$field.'<='.$max));
				if(!$row || is_null($row['id'])){
					DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',$min)");
				}
				else{
/*					$ret1 = $row["id"];
					if($ret1 >= $max) {
						"Maximum number of id's was exceeded"
					}
//*/

					DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',".$row['id'].')');
				}
				continue;
			}
			else{
				$ret1 = $row['nextid'];
				if((bccomp($ret1,$min) < 0) || !(bccomp($ret1,$max) < 0)) {
					DBexecute('DELETE FROM ids WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field));
					continue;
				}

				$sql = 'UPDATE ids SET nextid=nextid+1 WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field);
				DBexecute($sql);

				$row = DBfetch(DBselect('SELECT nextid FROM ids WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field)));
				if(!$row || is_null($row["nextid"])){
// Should never be here
					continue;
				}
				else{
					$ret2 = $row["nextid"];
					if(bccomp(bcadd($ret1,1),$ret2) == 0){
						$found = true;
					}
				}
			}
		}
		while(false == $found);

	return $ret2;
	}

	function create_id_by_nodeid($id,$nodeid=0){

		global $ZBX_LOCALNODEID;
		$nodeid = ($nodeid == 0)?get_current_nodeid(false):$nodeid;

		$id=remove_nodes_from_id($id);
		$id=bcadd($id,bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000')));
	return $id;
	}

	function zbx_db_distinct($sql_parts){
		if(count($sql_parts['from']) > 1) return ' DISTINCT ';
		else return ' ';

		$distinct_tables = array(
			'hosts_groups', 'hosts_templates',
			'functions', 'graphs_items', 'screens_items', 'slides',
			'httpstepitem', 'items_applications',
			'maintenances_hosts', 'maintenances_groups',
			'sysmaps_elements', 'sysmaps_link_triggers',
			'rights', 'users_groups'
		);
	}

	function remove_nodes_from_id($id){
		return bcmod($id,'100000000000');
	}

	function check_db_fields(&$db_fields, &$args){
		if(!is_array($args)) return false;

		foreach($db_fields as $field => $def){
			if(!isset($args[$field])){
				if(is_null($def)){
					return false;
				}
				else{
					$args[$field] = $def;
				}
			}
		}
	return true;
	}

	function DBcondition($fieldname, $array, $notin=false, $string=false){
		global $DB;
		$condition = '';

		if(!is_array($array)){
			info('DBcondition Error: ['.$fieldname.'] = '.$array);
			$array = explode(',',$array);
			if(empty($array))
				return ' 1=0 ';
		}

		$in = 		$notin ? ' NOT IN ':' IN ';
		$concat = 	$notin ? ' AND ':' OR ';

		switch($DB['TYPE']) {
			case 'SQLITE3':
			case 'MYSQL':
			case 'POSTGRESQL':
			case 'ORACLE':
			default:
				$items = array_chunk($array, 950);
				foreach($items as $id => $values){
					if($string) $values = zbx_dbstr($values);

					$condition.=!empty($condition) ? ')'.$concat.$fieldname.$in.'(':'';
					$condition.= implode(',',$values);
				}
				break;
		}

		if(zbx_empty($condition)) $condition = $string ? "'-1'":'-1';

	return ' ('.$fieldname.$in.'('.$condition.')) ';
	}



	class DB{
		const SCHEMA_FILE = 'schema.inc.php';
		const DBEXECUTE_ERROR = 1;
		const RESERVEIDS_ERROR = 2;

		const FIELD_TYPE_INT = 'int';
		const FIELD_TYPE_STR = 'str';

		static $schema = null;

		private static function exception($code, $errors=array()){
			throw new APIException($code, $errors);
		}

		protected static function reserveIds($table, $count){
			global $ZBX_LOCALNODEID;

			$nodeid = get_current_nodeid(false);
			$id_name = self::getSchema($table);
			$id_name = $id_name['key'];

			$min = bcadd(bcmul($nodeid,'100000000000000'), bcmul($ZBX_LOCALNODEID,'100000000000'), 0);
			$max = bcadd(bcadd(bcmul($nodeid,'100000000000000'), bcmul($ZBX_LOCALNODEID,'100000000000')),'99999999999', 0);

			$sql = 'SELECT nextid '.
				' FROM ids '.
				' WHERE nodeid='.$nodeid .'
					AND table_name='.zbx_dbstr($table).
					' AND field_name='.zbx_dbstr($id_name);
			$res = DBfetch(DBselect($sql));
			if($res){
				$nextid = bcadd($res['nextid'], 1, 0);

				if((bccomp($nextid, $max) == 1) || (bccomp($nextid, $min) == -1))
					self::exception(self::RESERVEIDS_ERROR, __METHOD__.' ID out of range for ['.$table.']');

				$sql = 'UPDATE ids '.
					' SET nextid=nextid+'.$count.
					' WHERE nodeid='.$nodeid.
						' AND table_name='.zbx_dbstr($table).
						' AND field_name='.zbx_dbstr($id_name);
				if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
			}
			else{
				$sql = 'SELECT max('.$id_name.') AS id'.
						' FROM '.$table.
						' WHERE '.$id_name.'>='.$min.
							' AND '.$id_name.'<='.$max;
				$row = DBfetch(DBselect($sql));

				$nextid = (!$row || is_null($row['id'])) ? $min : $row['id'];

				$sql = 'INSERT INTO ids (nodeid,table_name,field_name,nextid) '.
					' VALUES ('.$nodeid.','.zbx_dbstr($table).','.zbx_dbstr($id_name).','.bcadd($nextid, $count, 0).')';

				$nextid = bcadd($nextid, 1, 0);

				if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
			}

			return $nextid;
		}


		protected static function getSchema($table=null){
			if(is_null(self::$schema)){
				self::$schema = include(self::SCHEMA_FILE);
			}

			if(is_null($table))
				return self::$schema;
			else if(isset(self::$schema[$table]))
				return self::$schema[$table];
			else return false;
		}

/**
 * Insert data into DB
 *
 * @param string $table
 * @param array $values pair of fieldname => fieldvalue
 * @return array of ids
 */
		public static function insert($table, $values){
			if(empty($values)) return true;
			$result_ids = array();

			$id = self::reserveIds($table, count($values));
			$table_schema = self::getSchema($table);

			foreach($values as $key => $row){
				$result_ids[$key] = $id;

				unset($row[$table_schema['key']]);

				foreach($row as $field => $v){
					if(!isset($table_schema['fields'][$field])){
						unset($row[$field]);
					}
					else if($table_schema['fields'][$field] == self::FIELD_TYPE_STR){
						$row[$field] = zbx_dbstr($v);
					}
				}

				$sql = 'INSERT INTO '.$table.' ('.$table_schema['key'].','.implode(',',array_keys($row)).')'.
					' VALUES ('.$id.','.implode(',',array_values($row)).')';

				$id = bcadd($id, 1, 0);
				if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
			}
			return $result_ids;
		}

/**
 * Update data in DB
 *
 * @param string $table
 * @param array $data
 * @param array $data[...]['values'] pair of fieldname => fieldvalue for SET clause
 * @param array $data[...]['where'] pair of fieldname => fieldvalue for WHERE clause
 * @return array of ids
 */
		public static function update($table, $data){
			if(empty($data)) return true;

			$data = zbx_toArray($data);
			$table_schema = self::getSchema($table);

			foreach($data as $row){
				$sql_set = '';
				foreach($row['values'] as $field => $value){
					if(!isset($table_schema['fields'][$field])){
						continue;
					}
					else if($table_schema['fields'][$field] == self::FIELD_TYPE_STR){
						$value = zbx_dbstr($value);
					}

					$sql_set .= $field.'='.$value.',';
				}
				$sql_set = rtrim($sql_set, ',');

				if(!empty($sql_set)){
					$sql = 'UPDATE '.$table.' SET '.$sql_set.' WHERE '.implode(' AND ', $row['where']);
					if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
				}
			}
			return true;
		}

		public static function delete($table, $where){
			$where = zbx_toArray($where);

			$sql = 'DELETE FROM '.$table.' WHERE '.implode(' AND ', $where);
			if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');

			return true;
		}

	}

?>
