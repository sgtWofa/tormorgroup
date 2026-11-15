<?php
// Set timezone to Accra
date_default_timezone_set('Africa/Accra');

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
    $info = ['success' => false, 'results' => []];

    if ($findValue) {
        $find = fetchData($findValue);
        ezwich_json_response([
            'success' => true,
            'data' => $find ?? [],
        ]);
    }

    throw new Exception('Unknown find Value');

} catch (Exception $e) {
    ezwich_json_response(['success' => false, 'error' => $e->getMessage()], 500);
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
    $username = $session->first_name . ' ' . $session->last_name;
    $role = strtolower($session->roles[0] ?? '');

    // Initialize Ezwich class
    $ezwich = new \Ezwichs\Ezwich();
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
        FROM ezwichs AS m 
        LEFT JOIN users AS u ON m.created_by = u.id 
        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
    ";

    // Prepare conditional queries
    $whereClause = "
        WHERE (
            m.network LIKE :find 
            OR m.transaction_id LIKE :find 
            OR m.id LIKE :find 
            OR m.transaction_type LIKE :find 
            OR m.transaction_status LIKE :find 
            OR u.first_name LIKE :find 
            OR u.last_name LIKE :find 
            OR m.phone LIKE :find
            OR m.date_created LIKE :find
        )
        AND m.deleted = 0
    ";

    $queryParams = ['find' => $find];

    // Adjust query based on role
    if ($role === 'momo agent' || $role === 'solo agent') {
        $whereClause .= " AND m.created_by = :user_id";
        $queryParams['user_id'] = $user_id;
    }

    // Final query
    $query = $baseQuery . $whereClause . " ORDER BY m.id DESC";

    $rows = $ezwich->query($query, $queryParams);

    // Add computed totals
    $totalsQuery = "
        SELECT
            SUM(m.amount) AS total_amount, 
            SUM(m.charge) AS total_charge, 
            COUNT(m.id) AS total_qty 
        FROM ezwichs AS m 
        LEFT JOIN users AS u ON m.created_by = u.id 
        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
        $whereClause
    ";

    $totals = $ezwich->query($totalsQuery, $queryParams)[0] ?? [];

    $rows['totals'] = [
        'total_amount' => $totals->total_amount ?? 0,
        'total_charge' => $totals->total_charge ?? 0,
        'total_qty' => $totals->total_qty ?? 0,
    ];

    // Process image paths
    foreach ($rows as &$row) {
        $row = (array) $row; // Convert to array
        $row['user_image'] = get_image($row['user_image'] ?? '');
    }

    return $rows ?: [];
}
