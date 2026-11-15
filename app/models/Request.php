<?php

namespace Core;

defined('ROOT') or die("Direct script access denied");

/**
 * Request class
 */
class Request
{
	public $upload_max_size 	= 1;
	public $upload_folder 		= 'uploads';
	public $upload_errors 		= [];
	public $upload_error_code 	= 0;
	public $upload_file_types 	= [
		'image/jpeg',
		'image/jpg',
		'image/pjpeg',
		'image/png',
		'image/webp',
		'image/heic',
		'image/heif',
		'image/heic-sequence',
		'image/heif-sequence',
		'image/gif',
	];

	public function method():string
	{
		return $_SERVER['REQUEST_METHOD'];
	}

	public function posted():bool
	{
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}

	public function post(string $key = ''):string|array
	{
		if(empty($key))
			return $_POST;

		if(!empty($_POST[$key]))
			return $_POST[$key];

		return '';
	}

	public function input(string $key, string $default = ''):string
	{
		if(!empty($_POST[$key]))
			return $_POST[$key];

		return $default;
	}

	public function get(string $key = ''):string
	{
		if(empty($key))
			return $_GET;

		if(!empty($_GET[$key]))
			return $_GET[$key];

		return '';
	}

	public function files(string $key = ''):string|array
	{
		if(empty($key))
			return $_FILES;

		if(!empty($_FILES[$key]))
			return $_FILES[$key];

		return '';
	}

	public function all(string $key = ''):string|array
	{
		if(empty($key))
			return $_REQUEST;

		if(!empty($_REQUEST[$key]))
			return $_REQUEST[$key];

		return '';
	}

	public function upload_files(string $key = ''):string|array
	{
		$this->upload_errors 		= [];
		$this->upload_error_code 	= 0;

		$uploaded = empty($key) ? [] : '';

		$filenames = array_column($this->files(), 'name');
		$found = false;
		foreach ($filenames as $i => $value) {
			if(!empty($value)){
				$found = true;
				break;
			}
		}

		if(!$found)
			return $uploaded;

		$get_one = false;
		if(!empty($key))
			$get_one = true;
 
		$uploaded = [];

		foreach ($this->files() as $ikey => $file_arr) {
			
			if($get_one && $ikey != $key)
				continue;
			
			if(empty($file_arr['name']))
				continue;

			if($file_arr['error'] > 0)
			{
				$this->upload_error_code = $file_arr['error'];
				$this->upload_errors[$ikey] = "An error occured with file: ". $file_arr['name'];
				continue;
			}

			if(!in_array($file_arr['type'], $this->upload_file_types))
			{
				$this->upload_errors[$ikey] = "Invalid file type: ". $file_arr['name'];
				continue;
			}

			if($file_arr['size'] > ($this->upload_max_size * 1024 * 1024))
			{
				$this->upload_errors[$ikey] = "File too large (max {$this->upload_max_size}MB): ". $file_arr['name'];
				continue;
			}
			
			$folder = trim($this->upload_folder,'/') . '/';
			$originalName = basename(str_replace('\\', '/', (string)$file_arr['name']));
			$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
			$baseName = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
			$baseName = preg_replace('/[^a-z0-9_-]+/', '-', $baseName);
			$baseName = trim((string)$baseName, '-_');
			if($baseName === '')
				$baseName = 'upload';
			if($extension === '')
				$extension = 'jpg';
			$safeName = $baseName . '-' . date('YmdHis') . '-' . rand(1000,9999) . '.' . $extension;
			$destination = $folder . $safeName;

			$num = 0;
			while(file_exists($destination) && $num < 10)
			{
				$num++;
				$ext = explode(".", $destination);
				$ext = end($ext);

				$destination = preg_replace("/\.$ext$/", "_" . rand(0,99) . ".$ext", $destination);
			}

			if(!is_dir($folder))
				mkdir($folder,0777,true);

			if(!move_uploaded_file($file_arr['tmp_name'], $destination))
			{
				$this->upload_errors[$ikey] = "Could not upload file: ". $file_arr['name'];
				continue;
			}
			$uploaded[] = $destination;

			if($get_one)
				break;
		}

		if($get_one)
			return $uploaded[0] ?? '';

		return $uploaded;
	}

}
