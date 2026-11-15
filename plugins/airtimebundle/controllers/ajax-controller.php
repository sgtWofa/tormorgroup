<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_attendance') {
    header('Content-Type: application/json'); // Ensure the response is JSON

    try {
        // Get the date from POST data
        $date = $_POST['date'] ?? null;

        // Validate the date
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            airtimebundle_json_response(['error' => 'Invalid or missing date'], 400);
        }

        // Database queries
        $unmarkedQuery = "
            SELECT id, first_name, last_name, image 
            FROM users 
            WHERE id NOT IN (
                SELECT user_id 
                FROM attendance 
                WHERE date = :date
            )
        ";

        $markedQuery = "
            SELECT 
                u.first_name, u.last_name, u.image, a.time, a.remarks 
            FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.date = :date
        ";

        // Execute queries (replace $user and $attendance with actual DB handlers)
        $unmarkedResult = $user->query($unmarkedQuery, ['date' => $date]); // Fetch unmarked users
        $markedResult = $attendance->query($markedQuery, ['date' => $date]); // Fetch marked users

        airtimebundle_json_response(['unmarked' => $unmarkedResult, 'marked' => $markedResult]);
    } catch (Exception $e) {
        airtimebundle_json_response(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    }
}
