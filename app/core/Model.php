<?php

namespace Model;
use \Core\Database;

defined('ROOT') or die("Direct script access denied");

/**
 * Model class
 */
class Model extends Database
{
	
	public $order 			= 'desc';
	public $order_column 	= 'id';
	public $primary_key 	= 'id';

	public $limit 			= 100;
	public $offset 			= 0;
	public $errors 			= [];

	public function where(array $where_array = [], array $where_not_array = [], string $data_type = 'object'):array|bool
	{

		$query = "select * from $this->table where ";

		if(!empty($where_array))
		{
			foreach ($where_array as $key => $value) {
				$query .= $key . '= :'.$key . ' && ';
			}
		}
	
		if(!empty($where_not_array))
		{
			foreach ($where_not_array as $key => $value) {
				$query .= $key . ' != :'.$key . ' && ';
			}
		}

		$query = trim($query,' && ');
		$query .= " order by $this->order_column $this->order limit $this->limit offset $this->offset";

		$data = array_merge($where_array,$where_not_array);

		return $this->query($query,$data);
		
	}

	public function first(array $where_array = [], array $where_not_array = [], string $data_type = 'object'):object|bool
	{

		$rows = $this->where($where_array, $where_not_array,$data_type);
		if(!empty($rows))
			return $rows[0];

		return false;
	}

	public function getAll(string $data_type = 'object'):array|bool
	{

		$query = "select * from $this->table order by $this->order_column $this->order limit $this->limit offset $this->offset";
		return $this->query($query,[],$data_type);
	}

	public function insert(array $data)
	{
		if(($this->table ?? '') === 'logs')
		{
			static $logsSchemaEnsured = false;
			static $logsColumns = null;

			$ensureLogsSchema = function() use (&$logsSchemaEnsured, &$logsColumns){
				if($logsSchemaEnsured && is_array($logsColumns)){
					return;
				}
				$logsSchemaEnsured = true;
				$logsColumns = [];

				try{
					$cols = $this->query("SHOW COLUMNS FROM logs") ?: [];
					foreach($cols as $c){
						if(!is_object($c) || empty($c->Field)) continue;
						$logsColumns[strtolower((string)$c->Field)] = true;
					}

					$missing = [];
					foreach(['ip_address','user_agent','device_details'] as $need){
						if(empty($logsColumns[$need])) $missing[] = $need;
					}
					if(empty($missing)){
						return;
					}

					if(in_array('ip_address', $missing, true)){
						$this->query("ALTER TABLE logs ADD COLUMN ip_address varchar(45) null");
						$this->query("ALTER TABLE logs ADD KEY (ip_address)");
					}
					if(in_array('user_agent', $missing, true)){
						$this->query("ALTER TABLE logs ADD COLUMN user_agent text null");
					}
					if(in_array('device_details', $missing, true)){
						$this->query("ALTER TABLE logs ADD COLUMN device_details text null");
					}

					// Refresh columns after ALTER
					$logsColumns = [];
					$cols2 = $this->query("SHOW COLUMNS FROM logs") ?: [];
					foreach($cols2 as $c){
						if(!is_object($c) || empty($c->Field)) continue;
						$logsColumns[strtolower((string)$c->Field)] = true;
					}
				}catch(\Throwable $e){
					// Swallow: we will only write fields that exist.
					$logsColumns = is_array($logsColumns) ? $logsColumns : [];
				}
			};

			$ensureLogsSchema();

			// Ensure new audit fields are not stripped by per-plugin allowedColumns lists
			if(!empty($this->allowedColumns) && is_array($this->allowedColumns))
			{
				foreach (['ip_address','user_agent','device_details'] as $col) {
					if(!in_array($col, $this->allowedColumns, true))
					{
						$this->allowedColumns[] = $col;
					}
				}
			}

			$server = $_SERVER ?? [];
			$ip = $server['HTTP_CF_CONNECTING_IP']
				?? $server['HTTP_CLIENT_IP']
				?? ($server['HTTP_X_FORWARDED_FOR'] ?? null);
			if(!empty($ip) && is_string($ip) && str_contains($ip, ','))
			{
				$ip = trim(explode(',', $ip)[0] ?? '');
			}
			if(empty($ip))
			{
				$ip = $server['REMOTE_ADDR'] ?? null;
			}
			$ip = is_string($ip) ? trim($ip) : null;
			if($ip === '') $ip = null;

			$ua = $server['HTTP_USER_AGENT'] ?? null;
			$ua = is_string($ua) ? trim($ua) : null;
			if($ua === '') $ua = null;

			$platform = $server['HTTP_SEC_CH_UA_PLATFORM'] ?? null;
			$platform = is_string($platform) ? trim($platform, " \t\n\r\0\x0B\"") : null;
			if($platform === '') $platform = null;

			$details = [
				'ua' => $ua,
				'platform' => $platform,
				'language' => $server['HTTP_ACCEPT_LANGUAGE'] ?? null,
				'referer' => $server['HTTP_REFERER'] ?? null,
			];
			$deviceDetails = json_encode($details, JSON_UNESCAPED_SLASHES);

			if(!array_key_exists('ip_address', $data) && !empty($logsColumns['ip_address']))
			{
				$data['ip_address'] = $ip ?? (defined('IP_ADDRESS') ? IP_ADDRESS : null);
			}
			if(!array_key_exists('user_agent', $data) && !empty($logsColumns['user_agent']))
			{
				$data['user_agent'] = $ua ?? (defined('AGENT') ? AGENT : null);
			}
			if(!array_key_exists('device_details', $data) && !empty($logsColumns['device_details']))
			{
				$data['device_details'] = $deviceDetails;
			}
		}

		if(!empty($this->allowedColumns))
		{
			foreach ($data as $key => $value) {
				if(!in_array($key, $this->allowedColumns))
				{
					unset($data[$key]);
				}
			}
		}

		if(!empty($data))
		{
			$keys = array_keys($data);

			$query = "insert into $this->table (".implode(",", $keys).") values (:".implode(",:", $keys).")";
			return $this->query($query,$data);
		}

		return false;
	}

	public function update(string|int $_my_id, array $data)
	{
		if(!empty($this->allowedUpdateColumns) || !empty($this->allowedColumns))
		{
			$this->allowedUpdateColumns = empty($this->allowedUpdateColumns) ? $this->allowedColumns : $this->allowedUpdateColumns;
			foreach ($data as $key => $value) {
				if(!in_array($key, $this->allowedUpdateColumns))
				{
					unset($data[$key]);
				}
			}
		}
		
		if(!empty($data))
		{
			$query = "update $this->table set ";
			foreach ($data as $key => $value) {

				$query .= $key . '= :'.$key.",";
			}

			$query = trim($query,",");
			$data['my_id'] = $_my_id;

			$query .= " where $this->primary_key = :my_id";

			return $this->query($query,$data);
		}

		return false;
	}

	public function delete(string|int $_my_id)
	{
 
		$query = "delete from $this->table ";
		$query .= " where $this->primary_key = :_my_id limit 1";
		
		$data['_my_id'] = $_my_id;
		return $this->query($query,$data);

	}

    /** generates a url string from usually a post title **/
    /** used to generate a text unique id safe for url use **/
    public function str_to_url(string $url):string
    {
	   $url = str_replace("'", "", $url);
	   $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url);
	   $url = trim($url, "-");
	   $url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
	   $url = strtolower($url);
	   $url = preg_replace('~[^-a-z0-9_]+~', '', $url);

	   return $url;
	}
	

}

