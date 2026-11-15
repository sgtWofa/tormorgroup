<?php

namespace Core;

/**
 * App class
 */
class App
{
	public function index()
	{
		$controllerOutputLevel = ob_get_level();
		ob_start();
		$jsonOutput = static function (string $output): ?string {
			$headers = implode("\n", headers_list());
			if (stripos($headers, 'content-type: application/json') === false) {
				return null;
			}

			$output = trim($output);
			$length = strlen($output);
			$best = null;
			for ($start = 0; $start < $length; $start++) {
				if ($output[$start] !== '{' && $output[$start] !== '[') {
					continue;
				}
				for ($end = $length; $end > $start; $end--) {
					$candidate = trim(substr($output, $start, $end - $start));
					if ($candidate === '') {
						continue;
					}
					json_decode($candidate);
					if (json_last_error() === JSON_ERROR_NONE) {
						$best = $candidate;
						break 2;
					}
				}
			}

			return $best ?? '{}';
		};
		$flushControllerOutput = static function () use ($controllerOutputLevel, $jsonOutput): void {
			if (ob_get_level() <= $controllerOutputLevel) {
				return;
			}
			$output = (string)ob_get_clean();
			$cleanJson = $jsonOutput($output);
			echo $cleanJson !== null ? $cleanJson : $output;
		};
		register_shutdown_function(function () use ($controllerOutputLevel, $jsonOutput): void {
			if (ob_get_level() <= $controllerOutputLevel) {
				return;
			}
			$output = (string)ob_get_clean();
			$cleanJson = $jsonOutput($output);
			echo $cleanJson !== null ? $cleanJson : $output;
		});

		do_action('before_controller');
		do_action('controller');
		$flushControllerOutput();
		if (stripos(implode("\n", headers_list()), 'content-type: application/json') !== false) {
			return;
		}
		do_action('after_controller');

		ob_start();
		do_action('before_view');

		$before_content = ob_get_contents();
		do_action('view');
		$after_content = ob_get_contents();

		if(strlen($after_content) == strlen($before_content))
		{
			if(page() != '404'){
				redirect('404');
			}
		}

		do_action('after_view');

	}
}