<?php

namespace Core;

defined('ROOT') or die("Direct script access denied");

/**
 * Session class
 */
class Session
{
	private $varKey = 'APP';
	private $userKey = 'USER';
	private int $idleTimeoutSeconds = 300;
	private bool $idleTimeoutResolved = false;

	private function resolveIdleTimeoutSeconds(): int
	{
		if ($this->idleTimeoutResolved) {
			return $this->idleTimeoutSeconds;
		}
		$this->idleTimeoutResolved = true;

		// Default 5 minutes
		$seconds = 300;
		try {
			$db = new \Core\Database;
			if ($db->table_exists(['settings'])) {
				$row = ($db->query("SELECT inactivity_limit_minutes FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
				$mins = $row ? (int)($row->inactivity_limit_minutes ?? 0) : 0;
				if ($mins > 0) {
					$seconds = $mins * 60;
				}
			}
		} catch (\Throwable $e) {
			$seconds = 300;
		}

		$this->idleTimeoutSeconds = $seconds;
		return $this->idleTimeoutSeconds;
	}

	private function startSession():int
	{
		if(session_status() === PHP_SESSION_NONE)
			session_start();

		return 1;
	}

	public function set(string|array $keyOrArray, mixed $value = null):bool
	{
		$this->startSession();
		if(is_array($keyOrArray))
		{	
			foreach ($keyOrArray as $key => $value) {
				$_SESSION[$this->varKey][$key] = $value;
			}
			return true;
		}else
		{
			$_SESSION[$this->varKey][$keyOrArray] = $value;
			return true;
		}

		return false;
	}

	public function get(string $key):mixed
	{
		$this->startSession();
		if(!empty($_SESSION[$this->varKey][$key]))
		{	
			return $_SESSION[$this->varKey][$key];
		}

		return false;
	}

	public function pop(string $key):mixed
	{
		$this->startSession();
		if(!empty($_SESSION[$this->varKey][$key]))
		{	
			$var = $_SESSION[$this->varKey][$key];
			unset($_SESSION[$this->varKey][$key]);
			return $var;
		}

		return false;
	}

	public function auth(object|array $row):bool
	{
		$this->startSession();

		if(is_array($row))
		{
			$row = (object)$row;
		}
		if(!is_object($row))
		{
			$row = (object)[];
		}
		if(!property_exists($row, 'id'))
		{
			$row->id = property_exists($row, 'user_id') ? ($row->user_id ?? 0) : 0;
		}

		$_SESSION[$this->userKey] = $row;

		// Track session start and activity for security enforcement
		$now = time();
		$_SESSION[$this->varKey]['login_date'] = gmdate('Y-m-d', $now);
		$_SESSION[$this->varKey]['last_activity'] = $now;

		return true;
	}

	private function enforce_session_limits(): void
	{
		$this->startSession();

		if (empty($_SESSION[$this->userKey])) {
			return;
		}

		$now = time();
		$meta = $_SESSION[$this->varKey] ?? [];
		$loginDate = is_array($meta) ? (string)($meta['login_date'] ?? '') : '';
		$lastActivity = is_array($meta) ? (int)($meta['last_activity'] ?? 0) : 0;

		// Logout if the session crosses into the next calendar day
		$today = gmdate('Y-m-d', $now);
		if ($loginDate !== '' && $loginDate !== $today) {
			$this->logout();
			return;
		}

		// Logout on idle timeout (default 5 minutes)
		$idleSeconds = $this->resolveIdleTimeoutSeconds();
		if ($lastActivity > 0 && ($now - $lastActivity) >= $idleSeconds) {
			$this->logout();
			return;
		}

		// Update activity timestamp
		$_SESSION[$this->varKey]['last_activity'] = $now;
		if ($loginDate === '') {
			$_SESSION[$this->varKey]['login_date'] = $today;
		}
	}

	public function is_admin():bool
	{

		if(!$this->is_logged_in())
			return false;

		$arr = do_filter('before_check_admin',['is_admin'=>false]);

		if($arr['is_admin'])
			return true;

		return false;
	}

	public function is_logged_in():bool
	{
		$this->startSession();
		$this->enforce_session_limits();

		if(empty($_SESSION[$this->userKey]))
			return false;

		if(is_object($_SESSION[$this->userKey]))
			return true;

		if(is_array($_SESSION[$this->userKey]))
			return true;

		return false;
	}

	public function reset():bool
	{
		session_destroy();
		session_regenerate_id();

		return true;
	}

	public function logout():bool
	{
		$this->startSession();

		if(!empty($_SESSION[$this->userKey]))
			unset($_SESSION[$this->userKey]);

		return true;
	}

	public function user(string $key = ''):mixed
	{
		$this->startSession();

		if(!empty($_SESSION[$this->userKey]))
		{
			if(empty($key))
				$_SESSION[$this->userKey];

			if(is_object($_SESSION[$this->userKey]))
			{
				if(!empty($_SESSION[$this->userKey]->$key))
					return $_SESSION[$this->userKey]->$key;
			}else
			if(is_array($_SESSION[$this->userKey]))
			{
				if(!empty($_SESSION[$this->userKey][$key]))
					return $_SESSION[$this->userKey][$key];
			}
			
		}

		return null;
	}

	public function all():mixed
	{
		$this->startSession();
		if(!empty($_SESSION[$this->varKey]))
		{	
			return $_SESSION[$this->varKey];
		}

		return null;
	}
	
}
