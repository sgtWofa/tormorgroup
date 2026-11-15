<?php

namespace Settings;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Setting class
 */
class Setting extends Model
{

	protected $table = 'settings';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'user_id',
		'company_name',
		'image',
		'ceo_name',
		'ceo_contact',
		'admin_contact',
		'title',
		'transaction_fee',
		'momo_charges',
		'ezwich_charges',
		'agencybanking_charges',
		'buying_rate',
		'selling_rate',
		'company_sim',
		'work_open',
		'work_close',
		'late_attendance_time',
		'inactivity_limit_minutes',
		'notification_sound',
		'notification_sound_volume',
		'notification_sound_enabled',
		'notification_pill_color_serious',
		'notification_pill_color_mild',
		'notification_pill_color_regular',
		'notification_pill_plugin_map',
		'date_started',
		'date_created',
	];
	
	protected $allowedUpdateColumns = [
		'user_id',
		'company_name',
		'image',
		'ceo_name',
		'ceo_contact',
		'admin_contact',
		'title',
		'transaction_fee',
		'momo_charges',
		'ezwich_charges',
		'agencybanking_charges',
		'buying_rate',
		'selling_rate',
		'company_sim',
		'work_open',
		'work_close',
		'late_attendance_time',
		'inactivity_limit_minutes',
		'notification_sound',
		'notification_sound_volume',
		'notification_sound_enabled',
		'notification_pill_color_serious',
		'notification_pill_color_mild',
		'notification_pill_color_regular',
		'notification_pill_plugin_map',
		'date_started',
		'date_updated',
	];

	private function normalize_time_value($value): ?string
	{
		$value = trim((string)$value);
		if($value === ''){
			return null;
		}
		if(strlen($value) === 5){
			$value .= ':00';
		}

		$ts = strtotime('1970-01-01 ' . $value);
		if($ts === false){
			return null;
		}

		return date('H:i:s', $ts);
	}

	/**
	 * Public helper to ensure columns exist for the provided field names.
	 * This wraps the internal ensure_notification_pill_columns and is safe
	 * to call from controllers or views to auto-create missing columns.
	 */
	public function ensure_columns_exist(array $fields): void
	{
		if(empty($fields)) return;
		$payload = [];
		foreach($fields as $f){ $payload[$f] = ''; }
		try{
			$this->ensure_notification_pill_columns($payload);
		}catch(\Throwable $e){
			// swallow: best-effort to create columns
		}
	}

	public function normalize_hour_settings_payload(array $data): array
	{
		foreach (['work_open', 'work_close', 'late_attendance_time'] as $key) {
			if(!array_key_exists($key, $data)){
				continue;
			}

			$normalized = $this->normalize_time_value($data[$key]);
			$data[$key] = $normalized ?? '';
		}

		if(array_key_exists('notification_sound', $data)){
			$data['notification_sound'] = trim((string)$data['notification_sound']);
		}

		if(array_key_exists('notification_sound_enabled', $data)){
			$data['notification_sound_enabled'] = (int)$data['notification_sound_enabled'] === 1 ? 1 : 0;
		}

		foreach (['notification_pill_color_serious', 'notification_pill_color_mild', 'notification_pill_color_regular'] as $key) {
			if(array_key_exists($key, $data)){
				if(is_string($data[$key])){
					$data[$key] = trim((string)$data[$key]);
				}
			}
		}

		if(array_key_exists('notification_sound_volume', $data)){
			$volume = (int)$data['notification_sound_volume'];
			$data['notification_sound_volume'] = max(0, min(100, $volume));
		}

		if(array_key_exists('notification_pill_plugin_map', $data)){
			if(is_array($data['notification_pill_plugin_map'])){
				$data['notification_pill_plugin_map'] = json_encode($data['notification_pill_plugin_map']);
			} elseif(is_string($data['notification_pill_plugin_map'])){
				$data['notification_pill_plugin_map'] = trim((string)$data['notification_pill_plugin_map']);
			}
		}

		return $data;
	}

	private function validate_hour_settings(array $data): void
	{
		$rawOpen = trim((string)($data['work_open'] ?? ''));
		$rawClose = trim((string)($data['work_close'] ?? ''));
		$rawLate = trim((string)($data['late_attendance_time'] ?? ''));

		$workOpen = $this->normalize_time_value($rawOpen);
		$workClose = $this->normalize_time_value($rawClose);
		$lateAttendance = $this->normalize_time_value($rawLate);

		if($rawOpen !== '' && $workOpen === null){
			$this->errors['work_open'] = 'Opening time is invalid';
		}

		if($rawClose !== '' && $workClose === null){
			$this->errors['work_close'] = 'Closing time is invalid';
		}

		if($rawLate !== '' && $lateAttendance === null){
			$this->errors['late_attendance_time'] = 'Late attendance time is invalid';
		}

		if($workOpen !== null && $workClose !== null){
			$openTs = strtotime('1970-01-01 ' . $workOpen);
			$closeTs = strtotime('1970-01-01 ' . $workClose);
			if($openTs !== false && $closeTs !== false && $closeTs < $openTs){
				$this->errors['work_close'] = 'Closing time cannot be earlier than opening time';
			}
		}

		if($lateAttendance !== null){
			$lateTs = strtotime('1970-01-01 ' . $lateAttendance);

			if($workOpen !== null){
				$openTs = strtotime('1970-01-01 ' . $workOpen);
				if($lateTs !== false && $openTs !== false && $lateTs < $openTs){
					$this->errors['late_attendance_time'] = 'Late attendance time cannot be earlier than opening time';
				}
			}

			if($workClose !== null){
				$closeTs = strtotime('1970-01-01 ' . $workClose);
				if($lateTs !== false && $closeTs !== false && $lateTs > $closeTs){
					$this->errors['late_attendance_time'] = 'Late attendance time cannot be later than closing time';
				}
			}
		}
	}

	private function validate_notification_pill_colors(array $data): void
	{
		foreach ([
			'notification_pill_color_serious',
			'notification_pill_color_mild',
			'notification_pill_color_regular',
		] as $field) {
			if(!array_key_exists($field, $data)){
				continue;
			}

			$value = trim((string)$data[$field]);
			if($value === ''){
				continue;
			}

			if(!preg_match('/^#[0-9A-Fa-f]{6}$/', $value) && !preg_match('/^[a-zA-Z0-9_-]+(\s+[a-zA-Z0-9_-]+)*$/', $value)){
				$this->errors[$field] = 'Invalid pill color format';
			}
		}
	}

	private function validate_notification_pill_plugin_map(array $data): void
	{
		if(!array_key_exists('notification_pill_plugin_map', $data)) return;
		$raw = $data['notification_pill_plugin_map'];
		if($raw === null || $raw === '') return;
		if(is_string($raw)){
			$decoded = json_decode($raw, true);
			if($decoded === null || !is_array($decoded)){
				$this->errors['notification_pill_plugin_map'] = 'Invalid plugin mapping format';
				return;
			}
		} elseif(is_array($raw)){
			$decoded = $raw;
		} else {
			$this->errors['notification_pill_plugin_map'] = 'Invalid plugin mapping format';
			return;
		}

		// ensure allowed categories and array values
		$allowedCats = ['serious','mild','regular'];
		foreach($decoded as $k => $v){
			if(!in_array($k, $allowedCats, true)){
				$this->errors['notification_pill_plugin_map'] = 'Invalid plugin mapping keys';
				return;
			}
			if(!is_array($v)){
				$this->errors['notification_pill_plugin_map'] = 'Plugin mapping values must be arrays';
				return;
			}
			foreach($v as $pid){
				if(!is_string($pid) && !is_numeric($pid)){
					$this->errors['notification_pill_plugin_map'] = 'Plugin ids must be strings';
					return;
				}
			}
		}
	}

	private function validate_notification_settings(array $data): void
	{
		$sound = trim((string)($data['notification_sound'] ?? ''));
		if($sound !== '' && function_exists('admin_settings_notification_sound_options')){
			$options = \admin_settings_notification_sound_options();
			if(!isset($options[$sound])){
				$this->errors['notification_sound'] = 'Selected notification sound is not available';
			}
		}

		if(array_key_exists('notification_sound_volume', $data)){
			$volume = (int)$data['notification_sound_volume'];
			if($volume < 0 || $volume > 100){
				$this->errors['notification_sound_volume'] = 'Volume must be between 0 and 100';
			}
		}
	}


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['company_name']))
 		{
 			$this->errors['company_name'] = 'A company name is required';
 		}

 		if(empty($data['ceo_name']))
 		{
 			$this->errors['ceo_name'] = "CEO's name is required";
 		}

 		if(empty($data['date_started']))
 		{
 			$this->errors['date_started'] = "Date started is required";
 		}

		$this->validate_hour_settings($data);
		$this->validate_notification_settings($data);
		$this->validate_notification_pill_colors($data);
		$this->validate_notification_pill_plugin_map($data);

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		if(empty($data['company_name']))
 		{
 			$this->errors['company_name'] = 'A company name is required';
 		}

		$this->validate_hour_settings($data);
		$this->validate_notification_settings($data);
		$this->validate_notification_pill_colors($data);
		$this->validate_notification_pill_plugin_map($data);

		return empty($this->errors);
	}

	/**
	 * Filter payload to only columns that actually exist in the settings table.
	 * This prevents SQL errors when new columns haven't yet been migrated.
	 */
	private function filter_to_existing_columns(array $data): array
	{
		static $cols = null;

		if($cols === null)
		{
			$cols = [];
			try{
				$res = $this->query("SHOW COLUMNS FROM $this->table") ?: [];
				foreach ($res as $c) {
					if(!is_object($c) || empty($c->Field)) continue;
					$cols[strtolower((string)$c->Field)] = true;
				}
			}catch(\Throwable $e){
				// if we cannot introspect (no DB connection), assume all columns present
				return $data;
			}
		}

		foreach ($data as $k => $v) {
			if(!isset($cols[strtolower($k)])){
				unset($data[$k]);
			}
		}

		return $data;
	}

	public function insert(array $data)
	{
		// Attempt to create missing notification pill columns if the payload contains them
		try{
			$this->ensure_notification_pill_columns($data);
		}catch(\Throwable $e){
			// swallow: if we cannot create columns (missing PDO/permissions), continue gracefully
		}

		$data = $this->filter_to_existing_columns($data);
		return parent::insert($data);
	}

	public function update(string|int $_my_id, array $data)
	{
		try{
			$this->ensure_notification_pill_columns($data);
		}catch(\Throwable $e){
			// swallow
		}

		$data = $this->filter_to_existing_columns($data);
		return parent::update($_my_id, $data);
	}

	/**
	 * Ensure the three notification pill color columns exist when the payload includes them.
	 * This will attempt to run ALTER TABLE to add missing columns. It first checks PDO availability
	 * to avoid fatal CLI errors when drivers are missing.
	 */
	private function ensure_notification_pill_columns(array $data): void
	{
		$want = [
			'notification_pill_color_serious',
			'notification_pill_color_mild',
			'notification_pill_color_regular',
			'notification_pill_plugin_map',
		];

		$present = array_intersect($want, array_keys($data));
		if(empty($present)) return;

		// If PDO or driver not available, skip creating columns (we'll filter instead)
		if(!class_exists('PDO')) return;
		try{
			$drivers = \PDO::getAvailableDrivers();
		}catch(\Throwable $e){
			return;
		}
		if(!defined('DB_DRIVER') || !in_array(DB_DRIVER, $drivers, true)){
			return;
		}

		try{
			$cols = $this->query("SHOW COLUMNS FROM $this->table") ?: [];
			$existing = [];
			foreach($cols as $c){ if(is_object($c) && !empty($c->Field)) $existing[strtolower($c->Field)] = true; }

			foreach ($want as $col) {
				if(isset($existing[strtolower($col)])) continue;
				if(!in_array($col, $present, true)) continue; // only add if present in payload
				// safe add using VARCHAR(255) NULL DEFAULT NULL similar to migration
				$sql = "ALTER TABLE $this->table ADD COLUMN $col VARCHAR(255) NULL DEFAULT NULL";
				try{ $this->query($sql); }catch(\Throwable $e){ /* ignore */ }
			}
		}catch(\Throwable $e){
			// ignore any errors — we will fall back to filtering
			return;
		}
	}
}
