<?php

if (!function_exists('backoffice_whatsapp_config')) {
    function backoffice_whatsapp_config(): array
    {
        $configValue = static function (string $name, string $default = ''): string {
            $value = getenv($name);
            if ($value === false || trim((string)$value) === '') {
                $value = $_ENV[$name] ?? $_SERVER[$name] ?? '';
            }
            if (trim((string)$value) === '' && defined($name)) {
                $value = constant($name);
            }
            $value = trim((string)($value ?: $default));
            return trim($value, " \t\n\r\0\x0B\"'");
        };

        $token = $configValue('WHATSAPP_META_TOKEN');
        if ($token === '') {
            $token = $configValue('WHATSAPP_ACCESS_TOKEN');
        }

        return [
            'token' => $token,
            'phone_number_id' => $configValue('WHATSAPP_PHONE_NUMBER_ID'),
            'graph_version' => $configValue('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        ];
    }
}

if (!function_exists('backoffice_whatsapp_phone')) {
    /**
     * Resolves the WhatsApp SIM phone number or provides a explicit error reason.
     */
    function backoffice_whatsapp_phone(int $userId): array
    {
        if ($userId <= 0) {
            return ['phone' => '', 'reason' => 'Invalid assigned user ID provided.'];
        }

        try {
            $db = new \Core\Database;

            // First check user display name
            $userRows = $db->query(
                "SELECT first_name, last_name FROM users WHERE id = :user_id LIMIT 1",
                ['user_id' => $userId]
            ) ?: [];

            $userName = 'User #' . $userId;
            if (!empty($userRows)) {
                $fullName = trim((string)($userRows[0]->first_name ?? '') . ' ' . (string)($userRows[0]->last_name ?? ''));
                if ($fullName !== '') {
                    $userName = $fullName;
                }
            } else {
                return ['phone' => '', 'reason' => "Assigned user (ID: {$userId}) does not exist in the system."];
            }

            // Fetch any WhatsApp SIM assigned to the user
            $simRows = $db->query(
                "SELECT phone_number, COALESCE(deleted, 0) as deleted, COALESCE(disabled, 0) as disabled
                 FROM user_sim_assignments
                 WHERE user_id = :user_id
                   AND LOWER(sim_type) = :sim_type
                                     AND COALESCE(deleted, 0) = 0
                                     AND COALESCE(disabled, 0) = 0
                 ORDER BY id ASC
                 LIMIT 1",
                ['user_id' => $userId, 'sim_type' => 'whatsapp']
            ) ?: [];

            if (empty($simRows)) {
                return [
                    'phone' => '', 
                    'reason' => "Assigned user '{$userName}' does not have a SIM configured with 'WhatsApp' type."
                ];
            }

            $sim = $simRows[0];
            if ((int)$sim->deleted === 1) {
                return [
                    'phone' => '', 
                    'reason' => "The WhatsApp SIM assigned to '{$userName}' has been deleted."
                ];
            }

            if ((int)$sim->disabled === 1) {
                return [
                    'phone' => '', 
                    'reason' => "The WhatsApp SIM assigned to '{$userName}' is currently disabled."
                ];
            }

            $phone = trim((string)($sim->phone_number ?? ''));
            if ($phone === '') {
                return [
                    'phone' => '', 
                    'reason' => "The WhatsApp SIM for '{$userName}' has an empty phone number."
                ];
            }

            // Standardize format
            $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
            if (strpos($phoneClean, '+') === 0) {
                $phoneClean = substr($phoneClean, 1);
            }
            if (strpos($phoneClean, '00') === 0) {
                $phoneClean = substr($phoneClean, 2);
            }
            if (strpos($phoneClean, '0') === 0) {
                $phoneClean = '233' . substr($phoneClean, 1);
            }

            if (!preg_match('/^233[0-9]{9}$/', $phoneClean)) {
                return [
                    'phone' => '', 
                    'reason' => "The WhatsApp phone number ('{$phone}') for '{$userName}' is invalid."
                ];
            }

            return ['phone' => $phoneClean, 'reason' => ''];
        } catch (\Throwable $e) {
            error_log('[backoffice-whatsapp] Failed to resolve user SIM: ' . $e->getMessage());
            return ['phone' => '', 'reason' => 'Database error while checking WhatsApp SIM assignment.'];
        }
    }
}

if (!function_exists('backoffice_whatsapp_send')) {
    function backoffice_whatsapp_send(string $phone, string $message): array
    {
        $config = backoffice_whatsapp_config();
        if ($phone === '' || $message === '') {
            return ['sent' => false, 'reason' => 'Recipient phone number is missing or invalid.'];
        }
        if ($config['token'] === '' || $config['phone_number_id'] === '') {
            error_log('[backoffice-whatsapp] Meta credentials are not configured.');
            return ['sent' => false, 'reason' => 'WhatsApp API credentials are not configured.'];
        }
        if (!function_exists('curl_init')) {
            error_log('[backoffice-whatsapp] PHP cURL is unavailable.');
            return ['sent' => false, 'reason' => 'PHP cURL is unavailable.'];
        }

        $url = 'https://graph.facebook.com/' . rawurlencode($config['graph_version']) . '/' . rawurlencode($config['phone_number_id']) . '/messages';
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $message],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['token'],
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($response === false || $status < 200 || $status >= 300) {
            error_log('[backoffice-whatsapp] Meta API failed (' . $status . '): ' . ($error ?: (string)$response));
            $decoded = json_decode((string)$response, true);
            $reason = (string)($decoded['error']['message'] ?? 'Meta WhatsApp API request failed.');
            return ['sent' => false, 'reason' => $reason, 'http_status' => $status];
        }
        return ['sent' => true, 'reason' => 'Message accepted by Meta WhatsApp API.', 'http_status' => $status];
    }
}

if (!function_exists('backoffice_whatsapp_notify_transaction')) {
    function backoffice_whatsapp_notify_transaction(array $transaction): array
    {
        $summary = [
            'sent' => 0,
            'ready' => 0,
            'failed' => 0,
            'skipped' => 0,
            'recipient_label' => (string)($transaction['recipient_label'] ?? 'assigned user'),
            'reasons' => [],
            'links' => [],
        ];
        $shouldFlash = !array_key_exists('flash', $transaction) || !empty($transaction['flash']);
        try {
            $recipientId = (int)($transaction['recipient_id'] ?? $transaction['assigned_id'] ?? 0);
            if ($recipientId <= 0) {
                $summary['skipped'] = 1;
                $summary['reasons'][] = 'No notification recipient was selected or provided for this transaction.';
                if ($shouldFlash) {
                    backoffice_whatsapp_flash_result($summary);
                }
                return $summary;
            }
            $recipientIds = [$recipientId];

            $type = trim((string)($transaction['type'] ?? 'Transaction'));
            $id = (int)($transaction['id'] ?? 0);
            $status = trim((string)($transaction['status'] ?? 'Pending'));
            $details = is_array($transaction['details'] ?? null) ? $transaction['details'] : [];
            
            $icon = static function (string $unicode): string {
                $unicode = strtoupper(trim($unicode));
                $json = strlen($unicode) <= 4
                    ? '"\\u' . str_pad($unicode, 4, '0', STR_PAD_LEFT) . '"'
                    : '"\\u' . substr($unicode, 0, 4) . '\\u' . substr($unicode, 4, 4) . '"';
                return (string)json_decode($json);
            };

            $statusIcon = $icon('23F3'); // Hourglass
            $statusLower = strtolower($status);
            if (strpos($statusLower, 'approved') !== false || strpos($statusLower, 'completed') !== false || strpos($statusLower, 'success') !== false) {
                $statusIcon = $icon('2705'); // Check mark
            } elseif (strpos($statusLower, 'rejected') !== false || strpos($statusLower, 'failed') !== false || strpos($statusLower, 'declined') !== false) {
                $statusIcon = $icon('274C'); // Cross mark
            } elseif (strpos($statusLower, 'cancel') !== false) {
                $statusIcon = $icon('274E'); // Cross box
            }

            // Formatting message payload
            $lines = [];
            $notificationLabel = strtoupper(trim((string)($transaction['notification_label'] ?? 'NEW')));
            $lines[] = $icon('D83DDCE2') . " *{$notificationLabel} " . strtoupper($type) . " NOTIFICATION*";
            $lines[] = "──────────────────────────";
            $lines[] = $icon('D83CDD94') . " *Transaction ID:* `#" . $id . "`";
            $lines[] = "{$statusIcon} *Status:* *{$status}*";
            
            if (!empty($details)) {
                $lines[] = "";
                $lines[] = "*Details:*";
                foreach ($details as $label => $value) {
                    $value = trim((string)$value);
                    if ($value !== '') {
                        $lines[] = "• *{$label}:* _{$value}_";
                    }
                }
            }
            
            $viewUrl = trim((string)($transaction['view_url'] ?? ''));
            if ($viewUrl !== '') {
                $lines[] = "";
                $lines[] = "──────────────────────────";
                $lines[] = $icon('D83DDD17') . " *View Full Details:*";
                $lines[] = $viewUrl;
            }

            $message = implode("\n", $lines);

            foreach ($recipientIds as $recipientId) {
                $phoneResult = backoffice_whatsapp_phone($recipientId);
                $phone = $phoneResult['phone'];

                if ($phone === '') {
                    $summary['skipped']++;
                    $summary['reasons'][] = $phoneResult['reason'];
                    continue;
                }
                
                $summary['ready']++;
                $summary['links'][] = [
                    'phone' => $phone,
                    'url' => 'https://wa.me/' . $phone . '?text=' . rawurlencode($message),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[backoffice-whatsapp] Notification failed: ' . $e->getMessage());
            $summary['failed']++;
            $summary['reasons'][] = 'Unexpected WhatsApp notification error.';
        }
        
        if ($shouldFlash) {
            backoffice_whatsapp_flash_result($summary);
        }
        return $summary;
    }
}

if (!function_exists('backoffice_whatsapp_flash_result')) {
    function backoffice_whatsapp_flash_result(array $summary): void
    {
        $ses = new \Core\Session;
        $ses->set('backoffice_whatsapp_status', [
            'sent' => (int)($summary['sent'] ?? 0),
            'ready' => (int)($summary['ready'] ?? 0),
            'failed' => (int)($summary['failed'] ?? 0),
            'skipped' => (int)($summary['skipped'] ?? 0),
            'recipient_label' => (string)($summary['recipient_label'] ?? 'assigned user'),
            'links' => $summary['links'] ?? [],
            'reasons' => array_values(array_unique(array_filter(array_map('strval', $summary['reasons'] ?? [])))),
        ]);
    }
}

if (!function_exists('backoffice_whatsapp_notify_creator_on_assignee_update')) {
    /**
     * Makes the existing WhatsApp hand-off available when an assignee updates
     * a transaction. This deliberately does nothing for creator/admin edits.
     */
    function backoffice_whatsapp_notify_creator_on_assignee_update(array $transaction, int $actorId): array
    {
        $creatorId = (int)($transaction['creator_id'] ?? 0);
        $assignedId = (int)($transaction['assigned_id'] ?? 0);

        if ($actorId <= 0 || $creatorId <= 0 || $assignedId <= 0 || $actorId !== $assignedId || $creatorId === $actorId) {
            return ['sent' => 0, 'ready' => 0, 'failed' => 0, 'skipped' => 0, 'reasons' => [], 'links' => []];
        }

        $transaction['recipient_id'] = $creatorId;
        $transaction['notification_label'] = 'UPDATED';
        $transaction['recipient_label'] = 'creator';
        return backoffice_whatsapp_notify_transaction($transaction);
    }
}
