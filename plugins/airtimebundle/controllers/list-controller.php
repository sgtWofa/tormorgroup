<?php
// Set timezone to Accra
date_default_timezone_set('Africa/Accra');

// Ensure JSON Content Type
header('Content-Type: application/json; charset=utf-8');

try {
    // Check for POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get raw input
    $rawData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!isset($rawData['findValue'])) {
        throw new Exception('Missing findValue parameter');
    }

    $findValue = $rawData['findValue'];

    if ($findValue) {
        $data = fetchData($findValue);
        airtimebundle_json_response([
            'success' => true,
            'data' => $data,
        ]);
    }

    throw new Exception('Unknown find Value');

} catch (\Throwable $e) {
    airtimebundle_json_response(['success' => false, 'error' => $e->getMessage()], 400);
}

/**
 * Fetch marked users for a specific find.
 *
 * @param string $find
 * @return array
 */
function fetchData($find)
{
    // Initialize session and validate user
    $session = get_session();
    if (empty($session->id)) {
        throw new Exception('Invalid user session.');
    }

    $user_id = $session->id;
    $role = strtolower($session->roles[0] ?? '');

    $airtimebundle = new \Airtimebundles\Airtimebundle();
    $find = '%' . trim($find) . '%';

    $baseQuery = "
        SELECT
            m.*, 
            u.first_name AS user_first_name, 
            u.last_name AS user_last_name, 
            u.image AS user_image,
            u_updated.first_name AS updated_by_first_name, 
            u_updated.last_name AS updated_by_last_name, 
            u_updated.image AS updated_by_image  
        FROM airtimebundles AS m 
        LEFT JOIN users AS u ON m.created_by = u.id 
        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
    ";

    // Prepare conditional queries
    $whereClause = "
        WHERE (
            m.network LIKE :find 
            OR m.id LIKE :find 
            OR m.phone LIKE :find 
            OR m.transaction_type LIKE :find 
            OR m.created_by LIKE :find 
            OR u.first_name LIKE :find 
            OR u.last_name LIKE :find 
            OR m.transaction_status LIKE :find
            OR m.assigned LIKE :find 
            OR m.date_created LIKE :find)
        AND m.deleted = 0
    ";
    
    $queryParams = ['find' => $find];

    // Restrict by role
    $restrictedRoles = ['momo agent', 'solo agent'];
    if (in_array($role, $restrictedRoles, true)) {
        $whereClause .= " AND m.created_by = :user_id";
        $queryParams['user_id'] = $user_id;
    }

    // Final query
    $query = $baseQuery . $whereClause . " ORDER BY m.id DESC";
    $rows = $airtimebundle->query($query, $queryParams);

    // Add computed totals
    $totalsQuery = "
        SELECT
            SUM(m.amount) AS total_amount, 
            SUM(m.balance_after) AS total_balance_after, 
            COUNT(m.id) AS total_qty 
        FROM airtimebundles AS m 
        LEFT JOIN users AS u ON m.created_by = u.id 
        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
        $whereClause
    ";

    $totals = $airtimebundle->query($totalsQuery, $queryParams)[0] ?? [];
   
   // Check if $rows is an array. If not, initialize it to an empty array.
   if (!is_array($rows)) {
        $rows = [];
    }

   $rows['totals'] = [
        'total_amount' => $totals->total_amount ?? 0,
        'total_balance_after' => $totals->total_balance_after ?? 0,
        'total_qty' => $totals->total_qty ?? 0,
    ];

    // Process image paths
    foreach ($rows as &$row) {
        $row = (array) $row; // Convert to array
        $row['user_image'] = get_image($row['user_image'] ?? '');
    }
    unset($row); // break the reference

    return $rows ?: [];
}

