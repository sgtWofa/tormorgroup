<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$sim = new \Sims\Sim;
	$session = get_session();
	$user_id = !empty($session->id) ? $session->id : 0;
	$csrf = csrf_verify($postdata);
	$identifierErrors = [];

	$entriesJson = $postdata['sim_entries'] ?? '[]';
	try {
		$rawEntries = json_decode($entriesJson, true);
	} catch (\Throwable $th) {
		$rawEntries = [];
	}
	if(!is_array($rawEntries)) {
		$rawEntries = [];
	}

	$normalizedEntries = [];
	foreach ($rawEntries as $entry) {
		$serial = trim($entry['serial'] ?? '');
		$puk = trim($entry['puk'] ?? '');
		$key = $entry['key'] ?? uniqid('srv_', true);
		if($serial === '' && $puk === '') {
			continue;
		}
		$normalizedEntries[] = [
			'key' => $key,
			'serial_number' => $serial,
			'puk' => $puk,
		];
	}

	if (function_exists('sims_ensure_schema')) {
		sims_ensure_schema();
	}
	$imageColumnExists = false;
	try {
		$db = new \Core\Database;
		$column = $db->query("SHOW COLUMNS FROM sims LIKE 'image'");
		$imageColumnExists = !empty($column);
	} catch (\Throwable $th) {
		$imageColumnExists = false;
	}

	$uploadedImages = [];
	$imageFiles = $req->files('image');
	if (is_array($imageFiles) && !empty($imageFiles['name']) && is_array($imageFiles['name'])) {
		foreach ($imageFiles['name'] as $entryKey => $originalName) {
			if (empty($originalName)) {
				continue;
			}
			$error = $imageFiles['error'][$entryKey] ?? UPLOAD_ERR_NO_FILE;
			if ($error !== UPLOAD_ERR_OK) {
				$req->upload_errors['image'] = 'One or more SIM images could not be uploaded.';
				continue;
			}
			$type = $imageFiles['type'][$entryKey] ?? '';
			if (!in_array($type, $req->upload_file_types, true)) {
				$req->upload_errors['image'] = 'One or more SIM images have an invalid file type.';
				continue;
			}
			$size = (int)($imageFiles['size'][$entryKey] ?? 0);
			if ($size > ($req->upload_max_size * 1024 * 1024)) {
				$req->upload_errors['image'] = 'One or more SIM images exceed the maximum upload size.';
				continue;
			}
			$tmpName = $imageFiles['tmp_name'][$entryKey] ?? '';
			if (!is_uploaded_file($tmpName)) {
				$req->upload_errors['image'] = 'One or more SIM images could not be processed.';
				continue;
			}

			$folder = trim($req->upload_folder, '/') . '/';
			$originalName = basename(str_replace('\\', '/', (string)$originalName));
			$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
			$baseName = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
			$baseName = preg_replace('/[^a-z0-9_-]+/', '-', $baseName);
			$baseName = trim((string)$baseName, '-_');
			if ($baseName === '') {
				$baseName = 'sim-image';
			}
			if ($extension === '') {
				$extension = 'jpg';
			}
			$safeName = $baseName . '-' . date('YmdHis') . '-' . rand(1000, 9999) . '.' . $extension;
			$destination = $folder . $safeName;
			$num = 0;
			while (file_exists($destination) && $num < 10) {
				$num++;
				$destination = preg_replace('/\.[^.]+$/', '_' . $num . '.' . $extension, $destination);
			}
			if (!is_dir($folder)) {
				mkdir($folder, 0777, true);
			}
			if (!move_uploaded_file($tmpName, $destination)) {
				$req->upload_errors['image'] = 'One or more SIM images could not be saved.';
				continue;
			}
			$uploadedImages[$entryKey] = $destination;
		}
	}

	if (!empty($req->upload_errors)) {
		$sim->errors['image'] = implode(' ', array_values($req->upload_errors));
	}

	if($csrf)
	{
		if(user_can('add_sim'))
		{
			if(empty($normalizedEntries)) {
				$sim->errors['serial_number'] = 'Add at least one SIM identifier before saving.';
			}

			$validEntries = [];
			if(empty($sim->errors)) {
				foreach ($normalizedEntries as $entry) {
					$singleData = [
						'network' => $postdata['network'] ?? '',
						'cost' => $postdata['cost'] ?? '',
						'wholesale_price' => $postdata['wholesale_price'] ?? '',
						'retail_price' => $postdata['retail_price'] ?? '',
						'transaction_status' => $postdata['transaction_status'] ?? 'Unassigned',
						'assigned' => $postdata['assigned'] ?? null,
						'buyer_phone' => $postdata['buyer_phone'] ?? null,
						'serial_number' => $entry['serial_number'],
						'puk' => $entry['puk'],
					];
					if ($imageColumnExists && !empty($uploadedImages[$entry['key']])) {
						$singleData['image'] = $uploadedImages[$entry['key']];
					}

					$singleSim = new \Sims\Sim;
					if(!$singleSim->validate_insert($singleData)) {
						$entryIssues = [];
						if(!empty($singleSim->errors['serial_number'])) {
							$entryIssues['serial_number'] = $singleSim->errors['serial_number'];
							unset($singleSim->errors['serial_number']);
						}
						if(!empty($singleSim->errors['puk'])) {
							$entryIssues['puk'] = $singleSim->errors['puk'];
							unset($singleSim->errors['puk']);
						}
						if(!empty($entryIssues)) {
							$identifierErrors[$entry['key']] = $entryIssues;
						}
						foreach ($singleSim->errors as $field => $message) {
							$sim->errors[$field] = $message;
						}
					} else {
						$validEntries[] = $singleData;
					}
				}
			}

			if(!empty($validEntries)) {
				$username = trim((string)($session->first_name ?? '') . ' ' . ($session->last_name ?? '')) ?: 'System';
				$log = new \Sims\Log;
				$inserted = 0;

				foreach ($validEntries as $idx => $entryData) {
					$payload = $entryData;
					$payload['date_created'] = date('Y-m-d H:i:s');
					$payload['amount'] = $payload['wholesale_price'];
					$payload['created_by'] = $user_id;
					$payload['transaction_status'] = 'Unassigned';
					$payload['deleted'] = 0;

					$sim->insert($payload);
					if (!empty($sim->insert_id) && is_numeric($sim->insert_id) && (int)$sim->insert_id > 0) {
						$inserted++;
					} else {
						$sim->errors['database'] = 'Failed to save SIM entry ' . ($payload['serial_number'] ?? 'unknown') . '. Please try again.';
						break;
					}

					$log->insert([
						'table_name' => 'sims',
						'action' => 'added',
						'user_id' => $user_id,
						'user_name' => $username,
						'description' => "$username added {$payload['network']} sim with Serial Number::{$payload['serial_number']}",
						'date_created' => date("Y-m-d H:i:s"),
					]);
				}

				if(empty($sim->errors) && $inserted > 0) {
					message_success("{$inserted} SIM(s) added successfully!");
					redirect($admin_route.'/'.$plugin_route);
					return;
				}
			}
		} else {
			$sim->errors['network'] = "You do not have permission to add sims!";
		}
	}

	if(!$csrf)
		$sim->errors['serial_number'] = "Form expired!";

	set_value('identifier_errors',$identifierErrors);
	set_value('errors',$sim->errors);
