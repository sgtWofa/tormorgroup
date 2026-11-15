<?php


function set_value(string|array $key, mixed $value = ''):bool
{
	global $USER_DATA;

	$called_from = debug_backtrace();
	$ikey = array_search(__FUNCTION__, array_column($called_from, 'function'));
	$path = get_plugin_dir(debug_backtrace()[$ikey]['file']) . 'config.json';

	if(file_exists($path))
	{
		$json = json_decode(file_get_contents($path));
		$plugin_id = $json->id;

		if(is_array($key))
		{
			foreach ($key as $k => $entryValue) {
				
				$USER_DATA[$plugin_id][$k] = $entryValue;
			}
		}else
		{
			$USER_DATA[$plugin_id][$key] = $value;
		}


		return true;
	}

	return false;
}

function plugin_id():string
{
	$called_from = debug_backtrace();
	$ikey = array_search(__FUNCTION__, array_column($called_from, 'function'));
	$path = get_plugin_dir(debug_backtrace()[$ikey]['file']) . 'config.json';

	$json = json_decode(file_get_contents($path));
	return $json->id ?? '';
}

function get_value(string $key = ''):mixed
{
	global $USER_DATA;

	$called_from = debug_backtrace();
	$ikey = array_search(__FUNCTION__, array_column($called_from, 'function'));
	$path = get_plugin_dir(debug_backtrace()[$ikey]['file']) . 'config.json';

	if(file_exists($path))
	{
		$json = json_decode(file_get_contents($path));
		$plugin_id = $json->id;

		if(empty($key))
			return $USER_DATA[$plugin_id];

		return !empty($USER_DATA[$plugin_id][$key]) ? $USER_DATA[$plugin_id][$key] : null;
	}

	return null;

}

function APP($key = '')
{
	global $APP;

	if(!empty($key))
	{
		return !empty($APP[$key]) ? $APP[$key] : null;
	}else{

		return $APP;
	}

	return null;
}

function show_plugins()
{
	global $APP;
	
	$names = array_column($APP['plugins'] ?? [], 'name');
	dd($names ?? []);

}

/**splits the query string in the url**/
function split_url(string $url): array
{
	return explode("/", trim($url,'/'));
}

function URL($key = '')
{
	global $APP;

	if(is_numeric($key) || !empty($key))
	{
		if(!empty($APP['URL'][$key]))
		{
			return $APP['URL'][$key];
		}
	}else{
		return $APP['URL'];
	}

	return '';
}

function get_plugin_folders()
{
	global $APP;

	if(empty($APP['all_plugin_folders']))
	{
		$plugins_folder = 'plugins/';
		$res = [];
		$folders = scandir($plugins_folder);
		foreach ($folders as $folder) {
			if($folder != '.' && $folder != '..' && is_dir($plugins_folder . $folder))
				$res[] = $folder;
		}
		
		$APP['all_plugin_folders'] = $res;
		return $res;
	}
	
	return $APP['all_plugin_folders'];

}

function load_plugins(array $plugin_folders): bool
{
	global $APP;
	$loaded = false;
	$dependencies = [];

	foreach ($plugin_folders as $folder) {
		
		$file = 'plugins/' . $folder . '/config.json';
		if(file_exists($file))
		{
			$json = json_decode(file_get_contents($file));
			
			if(is_object($json) && isset($json->id))
			{
				if(!empty($json->active))
				{
					$file = 'plugins/' . $folder . '/plugin.php';
					if(file_exists($file) && valid_route($json))
					{
						$json->index = $json->index ?? 1;
						$json->version = $json->version ?? "1.0.0";
						$json->dependencies = $json->dependencies ?? (object)[];
						$json->index_file = $file;
						$json->path = 'plugins/' . $folder . '/';
						$json->http_path = ROOT . '/' . $json->path;

						$APP['plugins'][] = $json;

					}
				}
			}else
			{
				dd("Invalid json file: " . $file);
			}
		}
	}

	if(!empty($APP['plugins']))
	{
		$APP['plugins'] = sort_plugins($APP['plugins']);
		foreach ($APP['plugins'] as $json)
		{
			/** check for plugin dependencies **/
			if(!empty((array)$json->dependencies))
			{
				foreach ((array)$json->dependencies as $plugin_id => $version) {
					
					if($plugin_data = plugin_exists($plugin_id))
					{
						$required_version = (int)str_replace(".", "", $version);
						$existing_version = (int)str_replace(".", "", $plugin_data->version);
						if($existing_version < $required_version)
						{
							dd("Missing plugin dependency: ". $plugin_id . " version: ".$version.", Requested by plugin: ". $json->id);
							die;
						}
					}else
					{
						dd("Missing plugin dependency: ". $plugin_id . " version: ".$version.", Requested by plugin: ". $json->id);
						die;
					}
				}

			}

			/** load plugin file **/
			if(file_exists($json->index_file))
			{
				require_once $json->index_file;
				$loaded = true;
			}
		}
	}

	return $loaded;
}

function plugin_exists(string $plugin_id):bool|object 
{
	global $APP;

	$folders = get_plugin_folders();
	$plugins = [];
	foreach ($folders as $folder)
	{
		$file = 'plugins/' . $folder . '/config.json';
		if(file_exists($file))
		{
			$json = json_decode(file_get_contents($file));
			
			if(is_object($json) && isset($json->id))
				if(!empty($json->active))
					$plugins[] = $json;

		}
	}

	$ids = array_column($plugins, 'id');
	$key = array_search($plugin_id, $ids);
	if($key !== false)
	{
		return $plugins[$key];
	}

	return false;
}

function sort_plugins(array $plugins):array
{
	$to_sort = [];
	$sorted  = [];

	foreach ($plugins as $key => $obj) {
		$to_sort[$key] = $obj->index;
	}
	
	asort($to_sort);
	
	foreach ($to_sort as $key => $value) {
		$sorted[] = $plugins[$key];
	}

	return $sorted;
}

function valid_route(object $json):bool
{
	if(!empty($json->routes->off) && is_array($json->routes->off))
	{
		if(in_array(page(), $json->routes->off))
			return false;
	}

	if(!empty($json->routes->on) && is_array($json->routes->on))
	{
		if($json->routes->on[0] == 'all')
			return true;

		if(in_array(page(), $json->routes->on))
			return true;
	}

	return false;
}

function add_action(string $hook, mixed $func, int $priority = 10):bool
{

	global $ACTIONS;

	while(!empty($ACTIONS[$hook][$priority])) {
		$priority++;
	}

	$ACTIONS[$hook][$priority] = $func;

	return true;
}

function do_action(string $hook, array $data = [])
{
	global $ACTIONS;

	if(!empty($ACTIONS[$hook]))
	{
		ksort($ACTIONS[$hook]);
		foreach ($ACTIONS[$hook] as $key => $func) {
			$func($data);
		}
	}

}

function add_filter(string $hook, mixed $func, int $priority = 10):bool
{
	global $FILTER;

	while(!empty($FILTER[$hook][$priority])) {
		$priority++;
	}

	$FILTER[$hook][$priority] = $func;

	return true;
}

function do_filter(string $hook, mixed $data = ''):mixed
{
	global $FILTER;

	if(!empty($FILTER[$hook]))
	{
		ksort($FILTER[$hook]);
		foreach ($FILTER[$hook] as $key => $func) {
			$data = $func($data);
		}
	}

	return $data;
}

function dd(mixed $data): void
{
	echo "<pre><div style='margin:1px;background-color:#444;color:white;padding:5px 10px'>";
	print_r($data);
	echo "</div></pre>";
}

function page()
{
	return URL(0);
}

function redirect(string $url): void
{
	header("Location: ". ROOT .'/'. $url);
	die;
}

function plugin_path(string $path = '')
{
	$called_from = debug_backtrace();
	$key = array_search(__FUNCTION__, array_column($called_from, 'function'));
	return get_plugin_dir(debug_backtrace()[$key]['file']) . $path;
}

function plugin_http_path(string $path = '')
{
	$called_from = debug_backtrace();
	$key = array_search(__FUNCTION__, array_column($called_from, 'function'));
	
	return app_http_path(get_plugin_dir(debug_backtrace()[$key]['file']) . $path);
}

function get_plugin_dir(string $filepath):string
{

	$path = "";

	$basename = basename($filepath);
	$path = str_replace($basename, "", $filepath);

	if(strstr($path, DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR))
	{
		$parts = explode(DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR, $path);
		$parts = explode(DIRECTORY_SEPARATOR, $parts[1]);
		$path = 'plugins' . DIRECTORY_SEPARATOR . $parts[0].DIRECTORY_SEPARATOR;

	}

	return $path;
}

function app_base_url(): string
{
        static $baseUrl = null;

        if($baseUrl !== null){
                return $baseUrl;
        }

        if(defined('ROOT') && ROOT !== ''){
                $baseUrl = rtrim(ROOT, '/');
                return $baseUrl;
        }

        $httpsFlag = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $scheme = (($httpsFlag && $httpsFlag !== 'off') || $forwardedProto === 'https') ? 'https' : 'http';

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        if($host === ''){
                $host = 'localhost';
        }

        $scriptName = str_replace('\\\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = str_replace('\\\\', '/', dirname($scriptName));

        if($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\\\'){
                $scriptDir = '';
        }else{
                $scriptDir = rtrim($scriptDir, '/');
        }

        $baseUrl = $scheme . '://' . $host . $scriptDir;
        return $baseUrl;
}

function app_http_path(string $path = ''): string
{
	$path = str_replace('\\', '/', $path);
	return rtrim(app_base_url(), '/') . '/' . ltrim($path, '/');
}

function render_app_error_screen(
	int $statusCode,
	string $title,
	string $message,
	string $actionUrl,
	string $actionLabel,
	bool $standalone = false,
	?string $reference = null
): void {
	http_response_code($statusCode);
	$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
	$safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
	$safeActionUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
	$safeActionLabel = htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8');
	$safeReference = $reference !== null ? htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') : '';
	$codeLabel = (string)$statusCode;

	if ($standalone) {
		echo '<!doctype html><html lang="en" data-bs-theme="auto"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeTitle . ' · ' . htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') . '</title><script src="' . htmlspecialchars(app_http_path('plugins/basic-admin/assets/js/color-modes.js'), ENT_QUOTES, 'UTF-8') . '"></script></head><body>';
	}

	echo '<style>
		.app-error-page{--error-ink:#172033;--error-muted:#64748b;--error-surface:#fff;--error-line:#e2e8f0;--error-accent:#3157d5;box-sizing:border-box;color:var(--error-ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
		.app-error-page *{box-sizing:border-box}
		.app-error-page.app-error-standalone{position:fixed;inset:0;z-index:9999;min-height:100vh;display:grid;place-items:center;overflow:auto;padding:clamp(1.25rem,5vw,4rem);background:radial-gradient(ellipse at 18% 12%,rgba(49,87,213,.12),transparent 42%),linear-gradient(145deg,#f8faff,#eef2f8)}
		.app-error-panel{width:min(100%,680px);margin:2rem auto;padding:clamp(1.5rem,5vw,3.25rem);border:1px solid var(--error-line);border-radius:1.5rem;background:var(--error-surface);box-shadow:0 24px 70px rgba(15,23,42,.10);text-align:center}
		.app-error-code{display:inline-flex;align-items:center;gap:.5rem;padding:.4rem .75rem;border:1px solid rgba(49,87,213,.18);border-radius:999px;background:rgba(49,87,213,.08);color:var(--error-accent);font-size:.75rem;font-weight:750;letter-spacing:.1em;text-transform:uppercase}
		.app-error-icon{display:grid;width:5rem;height:5rem;margin:1.5rem auto 1.25rem;place-items:center;border-radius:1.4rem;background:linear-gradient(145deg,#edf2ff,#e0e9ff);color:var(--error-accent)}
		.app-error-icon svg{width:2.4rem;height:2.4rem}
		.app-error-panel h1{margin:0;color:var(--error-ink);font-size:clamp(1.65rem,4vw,2.25rem);font-weight:750;letter-spacing:-.04em;line-height:1.15}
		.app-error-message{max-width:34rem;margin:1rem auto 1.75rem;color:var(--error-muted);font-size:1rem;line-height:1.7}
		.app-error-action{display:inline-flex;min-height:2.9rem;align-items:center;justify-content:center;padding:.7rem 1.15rem;border:1px solid var(--error-accent);border-radius:.75rem;background:var(--error-accent);color:#fff!important;font-weight:700;text-decoration:none;transition:transform .16s ease,box-shadow .16s ease,background .16s ease}
		.app-error-action:hover{transform:translateY(-1px);background:#2446bd;box-shadow:0 8px 20px rgba(49,87,213,.22)}
		.app-error-footnote{margin:1.2rem 0 0;color:var(--error-muted);font-size:.8rem}
		:root[data-bs-theme="dark"] .app-error-page{--error-ink:#e5eaf4;--error-muted:#a5b0c2;--error-surface:#151d2c;--error-line:#2d394d;--error-accent:#91aaff}
		:root[data-bs-theme="dark"] .app-error-page.app-error-standalone{background:radial-gradient(ellipse at 18% 12%,rgba(95,126,230,.18),transparent 42%),linear-gradient(145deg,#0e1420,#111a29)}
		:root[data-bs-theme="dark"] .app-error-icon{background:linear-gradient(145deg,#202c47,#1c2941)}
		@media(prefers-color-scheme:dark){:root:not([data-bs-theme="light"]):not([data-bs-theme="dark"]) .app-error-page{--error-ink:#e5eaf4;--error-muted:#a5b0c2;--error-surface:#151d2c;--error-line:#2d394d;--error-accent:#91aaff}:root:not([data-bs-theme="light"]):not([data-bs-theme="dark"]) .app-error-page.app-error-standalone{background:radial-gradient(ellipse at 18% 12%,rgba(95,126,230,.18),transparent 42%),linear-gradient(145deg,#0e1420,#111a29)}:root:not([data-bs-theme="light"]):not([data-bs-theme="dark"]) .app-error-icon{background:linear-gradient(145deg,#202c47,#1c2941)}}
	</style>';

	echo '<main class="app-error-page' . ($standalone ? ' app-error-standalone' : '') . '" role="main"><section class="app-error-panel" role="alert" aria-labelledby="app-error-title">';
	echo '<div class="app-error-code">Request status <span>' . $codeLabel . '</span></div>';
	echo '<div class="app-error-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/></svg></div>';
	echo '<h1 id="app-error-title">' . $safeTitle . '</h1><p class="app-error-message">' . $safeMessage . '</p>';
	echo '<a class="app-error-action" href="' . $safeActionUrl . '">' . $safeActionLabel . '</a>';
	echo '<p class="app-error-footnote">If you believe this is an error, contact your administrator.</p>';
	if ($safeReference !== '') {
		echo '<p class="app-error-footnote">Reference: ' . $safeReference . '</p>';
	}
	echo '</section></main>';

	if ($standalone) {
		echo '</body></html>';
	}
}

function render_app_database_failure(\PDOException $exception): void
{
	$debugEnabled = defined('DEBUG') && DEBUG;
	$debugMessage = $debugEnabled ? $exception->getMessage() : null;
	$reference = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
	error_log(sprintf(
		'[app:%s] Database connection failed: %s in %s:%d',
		$reference,
		$exception->getMessage(),
		$exception->getFile(),
		$exception->getLine()
	));

	while (ob_get_level() > 0) {
		ob_end_clean();
	}

	http_response_code(500);
	header_remove('Location');
	$isJsonRequest = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
		|| strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
		|| preg_match('/(^|\/)ajax_/', (string)($_GET['url'] ?? '')) === 1;

	if ($isJsonRequest) {
		header('Content-Type: application/json; charset=utf-8');
		$response = [
			'success' => false,
			'message' => 'The application is temporarily unable to connect to its database. Please try again later.',
			'reference' => $reference,
		];
		if ($debugMessage !== null) {
			$response['debug_message'] = $debugMessage;
		}
		echo json_encode($response, JSON_UNESCAPED_SLASHES);
		exit;
	}

	$message = 'We are having trouble connecting to the application database. Please try again in a moment. If the problem continues, contact your administrator and share the reference below.';
	if ($debugMessage !== null) {
		$message .= ' Debug details: ' . $debugMessage;
	}

	header('Content-Type: text/html; charset=utf-8');
	render_app_error_screen(
		500,
		'Database connection unavailable',
		$message,
		defined('ROOT') ? ROOT . '/home' : '/',
		'Try again',
		true,
		$reference
	);
	exit;
}

function register_app_error_handlers(): void
{
	$renderFailure = static function (string $reference): void {
		$GLOBALS['APP_ERROR_PAGE_RENDERED'] = true;
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		http_response_code(500);
		header_remove('Location');
		$isJsonRequest = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
			|| strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
			|| preg_match('/(^|\/)ajax_/', (string)($_GET['url'] ?? '')) === 1;

		if ($isJsonRequest) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'success' => false,
				'message' => 'Something went wrong while processing your request. Please try again.',
				'reference' => $reference,
			], JSON_UNESCAPED_SLASHES);
			return;
		}

		header('Content-Type: text/html; charset=utf-8');
		render_app_error_screen(
			500,
			'Something went wrong',
			'We could not complete your request. Please try again. If the problem continues, contact your administrator and share the reference below.',
			defined('ROOT') ? ROOT . '/home' : '/',
			'Return to the app',
			true,
			$reference
		);
	};

	set_exception_handler(static function (\Throwable $exception) use ($renderFailure): void {
		$reference = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
		error_log(sprintf(
			'[app:%s] Uncaught %s: %s in %s:%d%s%s',
			$reference,
			get_class($exception),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			PHP_EOL,
			$exception->getTraceAsString()
		));
		$renderFailure($reference);
	});

	register_shutdown_function(static function () use ($renderFailure): void {
		if (!empty($GLOBALS['APP_ERROR_PAGE_RENDERED'])) {
			return;
		}

		$error = error_get_last();
		$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
		if (!$error || !in_array((int)$error['type'], $fatalTypes, true)) {
			return;
		}

		$reference = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
		error_log(sprintf(
			'[app:%s] Fatal PHP error: %s in %s:%d',
			$reference,
			(string)$error['message'],
			(string)$error['file'],
			(int)$error['line']
		));
		$renderFailure($reference);
	});
}

function render_app_image_cropper_inline(): void
{
	static $rendered = false;

	if($rendered){
		return;
	}

	$candidatePaths = [
		ROOTPATH . 'plugins/basic-admin/assets/js/image-upload-cropper.js',
		ROOTPATH . 'assets/js/image-upload-cropper.js',
	];

	$source = '';
	foreach($candidatePaths as $cropperPath){
		if(!is_file($cropperPath) || !is_readable($cropperPath)){
			continue;
		}
		$loaded = file_get_contents($cropperPath);
		if($loaded !== false && $loaded !== ''){
			$source = $loaded;
			break;
		}
	}

	if($source === false || $source === ''){
		return;
	}

	$rendered = true;
	$source = str_replace('</script>', '<\/script>', $source);
	echo '<script data-app-image-cropper="inline">' . $source . '</script>';
}

function get_app_image_cropper_inline_source(): string
{
	static $source = null;

	if($source !== null){
		return $source;
	}

	$candidatePaths = [
		ROOTPATH . 'plugins/basic-admin/assets/js/image-upload-cropper.js',
		ROOTPATH . 'assets/js/image-upload-cropper.js',
	];

	foreach($candidatePaths as $cropperPath){
		if(!is_file($cropperPath) || !is_readable($cropperPath)){
			continue;
		}

		$loaded = file_get_contents($cropperPath);
		if($loaded !== false && $loaded !== ''){
			$source = str_replace('</script>', '<\/script>', $loaded) . "\n";
			return $source;
		}
	}

	$source = '';
	return $source;
}

function get_app_image_viewer_inline_source(): string
{
	static $source = null;

	if($source !== null){
		return $source;
	}

	$style = <<<CSS
#app-image-viewer-modal{display:none;position:fixed;inset:0;z-index:2147483647;align-items:center;justify-content:center;background:rgba(0,0,0,.85);padding:1.5rem;box-sizing:border-box;}
#app-image-viewer-modal[data-visible="true"]{display:flex;}
#app-image-viewer-modal .app-image-viewer-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.75);pointer-events:auto;}
#app-image-viewer-modal .app-image-viewer-shell{position:relative;width:100%;max-width:1200px;max-height:90vh;display:flex;align-items:center;justify-content:center;pointer-events:none;padding:1rem;}
#app-image-viewer-modal .app-image-viewer-content{position:relative;width:100%;max-height:100%;overflow:hidden;border-radius:1.25rem;box-shadow:0 42px 110px rgba(0,0,0,.5);background:#111;border:1px solid rgba(255,255,255,.08);pointer-events:auto;}
#app-image-viewer-modal .app-image-viewer-content img{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;border-radius:.75rem;}
#app-image-viewer-modal .app-image-viewer-close{position:absolute;top:0.75rem;right:0.75rem;background:#fff;color:#111;border:none;border-radius:999px;width:2.25rem;height:2.25rem;font-size:1.25rem;line-height:1;cursor:pointer;box-shadow:0 16px 35px rgba(0,0,0,.25);transition:transform .2s ease,background .2s ease;z-index:2;}
#app-image-viewer-modal .app-image-viewer-close:hover{transform:scale(1.08);background:#f4f4f4;}
CSS;

	$script = <<<JS
(function(){
	function openAppImageViewer(src){
		if(!src) return;
		var modal = document.getElementById('app-image-viewer-modal');
		if(!modal){
			modal = document.createElement('div');
			modal.id = 'app-image-viewer-modal';
			modal.innerHTML = '<div class="app-image-viewer-backdrop" data-app-image-viewer-close></div><div class="app-image-viewer-shell"><div class="app-image-viewer-content"><button type="button" class="app-image-viewer-close" data-app-image-viewer-close>&times;</button><img src="" alt="Image preview"></div></div>';
			document.body.appendChild(modal);
		}
		var img = modal.querySelector('.app-image-viewer-content img');
		if(img){ img.src = src; }
		document.body.style.overflow = 'hidden';
		modal.setAttribute('data-visible','true');
	}

	function closeAppImageViewer(){
		var modal = document.getElementById('app-image-viewer-modal');
		if(!modal) return;
		modal.removeAttribute('data-visible');
		document.body.style.overflow = '';
		var img = modal.querySelector('.app-image-viewer-content img');
		if(img){ img.src = ''; }
	}

	document.addEventListener('click', function(event){
		var viewerTarget = event.target.closest('[data-app-image-viewer]');
		if(viewerTarget){
			event.preventDefault();
			event.stopImmediatePropagation();
			var imageSrc = viewerTarget.dataset.appImage || viewerTarget.src || viewerTarget.getAttribute('href');
			openAppImageViewer(imageSrc);
			return;
		}
		if(event.target.matches('[data-app-image-viewer-close]') || event.target.closest('[data-app-image-viewer-close]')){
			event.preventDefault();
			event.stopImmediatePropagation();
			closeAppImageViewer();
		}
	});

	document.addEventListener('keydown', function(event){
		if(event.key === 'Escape'){
			closeAppImageViewer();
		}
	});
})();
JS;

	$source = '<style data-app-image-viewer="inline">' . $style . '</style>' . '<script data-app-image-viewer="inline">' . $script . '</script>';
	return $source;
}

function get_session()
{
	$ses = new \Core\Session;
	
	if($ses->is_logged_in())
	{
		$user  = $_SESSION['USER'];
	}
	else{
	
		$user  = $_SESSION;

	}

	if(is_array($user))
	{
		$user = (object)$user;
	}

	if(!is_object($user))
	{
		$user = (object)[];
	}

	if(!property_exists($user, 'id'))
	{
		$user->id = property_exists($user, 'user_id') ? ($user->user_id ?? 0) : 0;
	}

	return $user;
}



function user_can(?string $permission):bool
{
	if(empty($permission)) return true;

	$ses = new \Core\Session;
	
	if($permission == 'logged_in')
	{
		if($ses->is_logged_in())
			return true;

		return false;
	}

	if($permission == 'not_logged_in')
	{
		if(!$ses->is_logged_in())
			return true;

		return false;
	}
	
	if($ses->is_admin())
		return true;

	global $APP;

	if(empty($APP['user_permissions']))
		$APP['user_permissions'] = [];

	$APP['user_permissions'] = do_filter('user_permissions',$APP['user_permissions']);
	
	if(in_array('all', $APP['user_permissions']))
		return true;
	
	if(in_array($permission, $APP['user_permissions']))
		return true;

	return false;
}

function old_value(string $key, string $default = '',string $type = 'post'):string
{
	$array = $_POST;
	if($type == 'get')
		$array = $_GET;

	if(!empty($array[$key]))
		return $array[$key];

	return $default;
}

function old_select(string $key, string $value, string $default = '',string $type = 'post'):string
{
	$array = $_POST;
	if($type == 'get')
		$array = $_GET;

	if(!empty($array[$key]))
	{
		$left = is_string($array[$key]) ? trim($array[$key]) : (string)$array[$key];
		$right = trim($value);
		if($left === $right || strtolower($left) === strtolower($right))
			return ' selected ';
	}else
	{
		$left = trim((string)$default);
		$right = trim($value);
		if($left === $right || strtolower($left) === strtolower($right))
			return ' selected ';
	}

	return '';
}

function old_checked(string $key, string $value, string $default = '',string $type = 'post'):string
{
	$array = $_POST;
	if($type == 'get')
		$array = $_GET;

	if(!empty($array[$key]))
	{
		if($array[$key] == $value)
			return ' checked ';
	}else
	{
		if($default == $value)
			return ' checked ';
	}

	return '';
}

function old_date(string $key, string $default = '', string $type = 'post'): string
{
    // Determine the source of the input data
    $array = $_POST;
    if ($type == 'get') {
        $array = $_GET;
    }

    // Check if the key exists in the array and is not empty
    if (!empty($array[$key])) {
        $date = date('Y-m-d', strtotime($array[$key]));  // Convert to a valid date format (YYYY-MM-DD)
        return $date; // Return formatted date
    }

    // If key not found, return the default value as a formatted date
    return date('Y-m-d', strtotime($default)); 
}


function csrf(string $sesKey = 'csrf', int $hours = 1):string
{
	$key = '';

	$ses = new \Core\Session;
	$existing = $ses->get($sesKey);
	if (is_array($existing) && !empty($existing['key']) && !empty($existing['expires']) && $existing['expires'] > time()) {
		$key = (string)$existing['key'];
		$existing['expires'] = time() + ((60*60)*$hours);
		$ses->set($sesKey, $existing);
	} else {
		$key = hash('sha256', time() . rand(0,99));
		$expires = time() + ((60*60)*$hours);

		$ses->set($sesKey,[
			'key'=> $key,
			'expires'=>$expires
		]);
	}

	return "<input type='hidden' value='$key' name='$sesKey' />";
}


function csrf_verify(array $post, string $sesKey = 'csrf'):mixed
{
	
	if(empty($post[$sesKey]))
		return false;

	$ses = new \Core\Session;
	$data = $ses->get($sesKey);


	if(is_array($data))
	{
		if($data['key'] !== $post[$sesKey])
			return false;

		if($data['expires'] > time())
			return true;

		$ses->pop($sesKey);
		
	}

	return false;
}


function get_image(?string $path = '', string $type = 'post')
{
	$path = $path ?? '';

	if(file_exists($path))
		return ROOT . '/' . $path;
	
	if($type == 'post')
		return ROOT . '/assets/images/no_image.jpg';

	if($type == 'male')
		return ROOT . '/assets/images/user_male.jpg';

	if($type == 'female')
		return ROOT . '/assets/images/user_female.jpg';

	return ROOT . '/assets/images/no_image.jpg';
}

function esc(?string $str):?string
{
	return htmlspecialchars($str);
}

function get_date(?string $date):string
{
	$date = $date ?? '';
	$timestamp = strtotime($date);
	if($timestamp === false || $timestamp === -1) {
		return '';
	}
	return date("jS M, Y", $timestamp);
}

function get_date_time(?string $date):string
{
	$date = $date ?? '';
	$timestamp = strtotime($date);
	if($timestamp === false || $timestamp === -1) {
		return '';
	}
	return date("jS M, Y (H:i:s)", $timestamp);
}

function message_success(string $msg = '', bool $erase = false):?string
{
	$ses = new \Core\Session;

	if(!empty($msg))
	{
		$ses->set('message_success',$msg);
	}else
	if(!empty($ses->get('message_success')))
	{
		$msg = $ses->get('message_success');

		if($erase)
			$ses->pop('message_success');

		return $msg;
	}

	return '';
}


function message_fail(string $msg = '', bool $erase = false):?string
{
	$ses = new \Core\Session;

	if(!empty($msg))
	{
		$ses->set('message_fail',$msg);
	}else
	if(!empty($ses->get('message_fail')))
	{
		$msg = $ses->get('message_fail');

		if($erase)
			$ses->pop('message_fail');

		return $msg;
	}

	return '';
}

function class_path(string $folder, string $class_name): string
{
	return 'plugins/' . $folder. '/models/'. $class_name . '.php';
}

function reset_paaword($keyLength=8) {

    $str = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ?@&$";
    $randStr = substr(str_shuffle($str), 0, $keyLength);   
    return $randStr;
}

function get_online_users() {
    $db = new \Core\Database;
    $threshold = date("Y-m-d H:i:s", strtotime("-5 minutes"));
    $query = "SELECT user_id FROM logs WHERE date_created >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    return $db->query($query);
}
