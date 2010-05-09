<?php

class MongoDB_Info	{
	
	const DB_DEDFAULT = 'admin';
	
	private static $_db_instance;
	
	private static $_dbs = Array();
	
	public static $mongo_connection = null;
	
	private function __construct()	{}
	
	/**
	 * @var MongoDB
	 */
	private static $current_db;
	
	/**
	 * @var Mongo
	 */
	private static $current_mongo;
	
	
	/**
	 * Instance of Mongo_stats
	 * @param $db_name
	 * @return Mongo_stats
	 */
	public static function instance($db_name = NULL)	{
		if ($db_name == NULL)	{
			$db_name = self::DB_DEDFAULT;
		}
		
		if (!isset(self::$_db_instance))	{
			self::$_db_instance = new MongoDB_Info();
		}
		
		if (isset(self::$_dbs[$db_name]))	{
			return self::$_db_instance;
		}
		
		$connection = (self::$mongo_connection instanceof Mongo) ? self::$mongo_connection : new Mongo();
		self::$current_mongo = $connection;	
		self::$_dbs[$db_name] = $connection->$db_name;
		
		self::$current_db = self::$_dbs[$db_name];
		return self::$_db_instance;
	}
	
	/**
	 * get server status
	 * @return Array
	 */
	public function get_server_status()	{
		if (isset(self::$current_db))	{
			return self::$current_db->command(array('serverStatus' => 1, 'repl' => 2));
		}
		return Array();
	}
	
	
	/**
	 * get build info
	 * @return Array
	 */
	public function get_build_info()	{
		if (isset(self::$current_db))	{
			return self::$current_db->command(array('buildinfo' => 1));
		}
		return Array();
	}
	
	
	/**
	 * Get Master info
	 * @return Array
	 */
	public function get_master_info()	{
		if (isset(self::$current_db))	{
			return self::$current_db->command(array('ismaster' => 1));
		}
		return Array();
	}
	
	
	/**
	 * Get Replication info: master and slave
	 * http://github.com/mongodb/mongo/blob/master/shell/db.js
	 */
	public function get_replication_info()	{				
		if (self::$current_mongo instanceof Mongo)	{			
			$mongo = self::$current_mongo;
			$db = $mongo->local;			
			$result = array();
  			
  			$oplog = $db->system->namespaces->findOne(array('name' => 'local.oplog.$main'));
    		$result['ok'] = 1;
  			if ($oplog && array_key_exists('options', $oplog))	{
  				$result['logSizeMB'] = $oplog['options']['size'] / 1000 / 1000;  				
  			}
  			else	{
  				$result['errmsg'] = 'local.oplog.$main, or its options, not found in system.namespaces collection (not --master?)';
  				$result['ok'] = 0;
  				return $result;
  			}
  			
  			$oplog_collection = $db->selectCollection('oplog.$main');
  			$firstc = $oplog_collection->find()->sort(Array(
  				'$natural' => 1	//Ascending
  			))->limit(1);
  			$lastc = $oplog_collection->find()->sort(Array(
  				'$natural' => -1	//Descending
  			))->limit(1);
  			
  			if (!$firstc->hasNext() && !$lastc->hasNext())	{
  				$result['errmsg'] = 'objects not found in local.oplog.$main -- is this a new and empty db instance?';
  				$result['ok'] = 0;
  				$result['oplogMainRowCount'] = $oplog_collection->count();
  				return $result;
  			}
  			
  			  			
  			$first = $firstc->getNext();
  			$last = $lastc->getNext();
  			
  			$first_ts = $first['ts'];
  			$last_ts = $last['ts'];
  			
  			if ($first_ts && $last_ts)	{
  				$first_ts = $first_ts->inc;
  				$last_ts = $last_ts->inc;
  				
  				$result['timeDiff'] = $last_ts - $first_ts;
	  			$result['timeDiffHours'] = round($result['timeDiff'] / 36) / 1000;
	  			$result['tFirst'] = date('r', $first_ts);
	  			$result['tLast'] = date('r', $last_ts);
	  			$result['now'] = date('r');
	  			
	  			$slave = $this->get_slave_replication_info($db);
  				$result['slaveInfo'] = $slave;
  			}
  			else	{
  				$result['errmsg'] = 'ts element not found in oplog objects';
  			}
  			
  			return $result;
		}
		return Array();
	}
	
	
	/**
	 * get slave replication information
	 * @param $master Mongo
	 * @return Array
	 */
	private function get_slave_replication_info($master)	{
		$db = $master;
		if ($db->sources->count() == 0) {
			return Array();
		}
		$slave = Array();
		$cursor = $db->sources->find();
		while ($cursor->hasNext()) {
			$source = $cursor->getNext();						
			$arr = Array();
			$arr['host'] =  $source['host'];
			$now = new MongoDate();
			$ago = ($now->sec - $source['syncedTo']->inc);			
			$hrs = round($ago / 36) / 100;			
			$arr['synced_hours'] = $hrs;
			$arr['synced_secs'] = round($ago);
			
			$slave[] = $arr;
		}
		return $slave;
	}
	
	public function get_last_error()	{
		if (isset(self::$current_db))	{
			return self::$current_db->command(array('getlasterror' => 1));
		}
		return Array();
	}
	
	public function get_previous_error()	{
		if (isset(self::$current_db))	{
			return self::$current_db->command(array('getpreverror' => 1));
		}
		return Array();
	}
	
	public function get_current_operations()	{
		if (isset(self::$current_db))	{
			return self::$current_db->selectCollection('$cmd.sys.inprog')->find(array('$all' => 1)) ;
		}
		return Array();
	}
	
	
	/**
	 * Method for validate command
	 * Time: O(n2)
	 * @param String $db_name
	 */
	public function validate_db($db_name)	{
		self::instance($db_name);		
		$connection = (self::$mongo_connection instanceof Mongo) ? self::$mongo_connection : new Mongo();		
		$db = $connection->$db_name;		
		$info = Array();
		$pattern = "/^tmp.mr.mapreduce_([0-9a-z_]*)$/";
		foreach ($db->listCollections() as $collection)	{
			$collection_name = $collection->getName();
			$match = preg_match($pattern, $collection_name);
			if ($match !== 1)	{
				$data = self::$current_db->command(array('validate' => $collection_name));
				array_push($info, Array(
					Array(
						$collection_name => $data,
						'name' => $db_name . "." . $collection_name
					)
				));
			}
			 
		}
		return $info;
	}
}

//USAGE//

MongoDB_Info::$mongo_connection = new Mongo("mongodb://localhost:27017,localhost:27018");
$mongo_stats = MongoDB_Info::instance();

print_r($mongo_stats->get_build_info());

print_r($mongo_stats->get_replication_info());

print_r($mongo_stats->get_master_info());

print_r($mongo_stats->validate_db('db_name'));