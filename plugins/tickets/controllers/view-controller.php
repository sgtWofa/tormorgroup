<?php
if (!empty($row)) {
    // Set timezone to Accra
    date_default_timezone_set('Africa/Accra');

    // Initialize response structure
    $info = [
        'success' => false,
        'message' => "",
        'errors' => [],
        'data' => []
    ];

    // Initialize classes
    $ticket = new \Tickets\Ticket;
    $response = new \Tickets\Ticket_response;
    $canViewAllTickets = function_exists('tickets_user_can_view_all') ? tickets_user_can_view_all() : user_can('is_admin');

    // Retrieve session details
    $session = get_session();
    $username = $session->first_name . ' ' . $session->last_name ?? '';
    $user_id = $session->id ?? 0;

    // Get the list of online users (ensure it's always an array)
    $onlineUsers = get_online_users() ?: [];

    // Check if request is POST for search or response submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postdata = $req->post();
        $filedata = $req->files();

        $files_ok = true;
        if(!empty($filedata)){
            $postdata['image'] = $req->upload_files('image');
            if(!empty($req->upload_errors))
                $files_ok = false;
        }

        // Handle ticket search
        if (isset($postdata['find'])) {
            $find = $postdata['find'] ?? '';
            $tickets = [];

            if (!empty($find)) {
                // Search-specific query
                $searchTerm = '%' . trim($find) . '%';
                $params = ['find' => $searchTerm];
                $scopeSql = '';
                if (!$canViewAllTickets) {
                    $scopeSql = ' AND (m.created_by = :user_id OR m.assigned = :user_id) ';
                    $params['user_id'] = $user_id;
                }
                $query = "
                    SELECT m.*, 
                    u.first_name AS user_first_name, 
                    u.last_name AS user_last_name, 
                    u.image AS user_image, 
                    COUNT(responses.id) AS total_responses,
                    SUM(CASE WHEN responses.seen = 0 THEN 1 ELSE 0 END) AS unseen_responses
                    FROM tickets AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN ticket_responses AS responses ON m.id = responses.ticket_id 
                    WHERE (
                        m.subject LIKE :find 
                        OR m.description LIKE :find 
                        OR u.first_name LIKE :find 
                        OR u.last_name LIKE :find 
                        OR m.id LIKE :find
                    )
                    $scopeSql
                    GROUP BY m.id
                    ORDER BY m.date_created DESC";
                $tickets = $ticket->query($query, $params);
            } else {
                // Default query to fetch all tickets
                $params = [];
                $scopeSql = '';
                if (!$canViewAllTickets) {
                    $scopeSql = 'WHERE (m.created_by = :user_id OR m.assigned = :user_id)';
                    $params['user_id'] = $user_id;
                }
                $query = "
                    SELECT m.*, 
                    u.first_name AS user_first_name, 
                    u.last_name AS user_last_name, 
                    u.image AS user_image, 
                    COUNT(responses.id) AS total_responses,
                    SUM(CASE WHEN responses.seen = 0 THEN 1 ELSE 0 END) AS unseen_responses
                    FROM tickets AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN ticket_responses AS responses ON m.id = responses.ticket_id 
                    $scopeSql
                    GROUP BY m.id
                    ORDER BY m.date_created DESC";
                $tickets = $ticket->query($query, $params);
            }

            if (!empty($tickets)) {
                foreach ($tickets as $ticket) {
                    
                    $fullname = $ticket->user_first_name . ' ' . $ticket->user_last_name;

                    $info['data'][] = [
                        'id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'description' => $ticket->description,
                        'date_created' => $ticket->date_created,
                        'user_first_name' => $ticket->user_first_name,
                        'user_last_name' => $ticket->user_last_name,
                        'user_full_name' => $fullname,
                        'user_full_name_short' => substr($fullname, 0, 18),
                        'user_image' => get_image($ticket->user_image),
                        'total_responses' => $ticket->total_responses,
                        'unseen_responses' => $ticket->unseen_responses,
                    ];
                }
                $info['success'] = true;
            } else {
                $info['message'] = "No tickets found.";
            }

            echo json_encode($info);
            die;
        }

        // Handle response and or image submission
        if (isset($postdata['image']) || isset($postdata['response'])) {
            $csrf = csrf_verify($postdata);
            if ($csrf && $files_ok && $response->validate_insert($postdata)) {
                if (user_can('add_tickets')) {
                    $postdata['date_created'] = date("Y-m-d H:i:s");
                    $postdata['id'] = time();
                    $postdata['seen'] = 0;

                    $postdata['sender_id'] = $user_id;
                    $postdata['created_by'] = $user_id;

                    $response->insert($postdata);                   

                    // Update `seen` for online users who are receiving response associated with the ticket
                    $associatedUsers = [$postdata['sender_id']]; // Users associated with the ticket
                    foreach ($associatedUsers as $associatedUser) {
                        $query = "UPDATE ticket_responses SET seen = 1 
                                  WHERE ticket_id = :ticket_id AND created_by != :associated_user";
                        $response->query($query, [
                            'ticket_id' => $row->id,
                            'associated_user' => $associatedUser
                        ]);
                    }

                    $info['success'] = true;
                    $info['message'] = "Response added successfully!";
                    $info['data'] = [];
                    if (!empty($postdata['image'])) {
                           $info['data'] = [
                            'id' => $postdata['id'],
                            'ticket_id' => $postdata['ticket_id'],
                            'response' => $postdata['response'],
                            'image' => get_image($postdata['image'] ?? null),
                            'seen' => $postdata['seen'],
                            'sender_id' => $user_id,
                            'sender_name' => $username,
                            'user_image' => get_image($session->image ?? null),
                            'date_created' => get_date_time($postdata['date_created']),
                        ];
                    }else{
                            $info['data'] = [
                            'id' => $postdata['id'],
                            'ticket_id' => $postdata['ticket_id'],
                            'response' => $postdata['response'],
                            'seen' => $postdata['seen'],
                            'sender_id' => $user_id,
                            'sender_name' => $username,
                            'user_image' => get_image($session->image ?? null),
                            'date_created' => get_date_time($postdata['date_created']),
                        ];
                    }

                    //log changes in the log table
                    $action                             = 'added';
                    $transaction                    = "new  response to ticket #". $postdata['ticket_id'];
                    $table                              = 'ticket responses';
                    $description                    = "$username $action a $transaction";
                    $date_created                   = date("Y-m-d H:i:s");

                    $log = new \Tickets\Log;
                    $log_activity = $log->insert([
                        'table_name'=>$table,
                        'action'=>$action,
                        'user_id'=>$user_id,
                        'user_name'=>$username,
                        'description'=>$description,
                        'date_created'=>$date_created,
                     ]);

                } else {
                    $info['errors'][] = "You do not have permission to add responses.";
                }
            } else {
                $info['errors'][] = $csrf ? "Response message or image cannot be empty." : "CSRF token verification failed.";
            }

            echo json_encode($info);
            die;
        }
    }

    // Handle initial page load to display all tickets
    $params = [];
    $scopeSql = '';
    if (!$canViewAllTickets) {
        $scopeSql = 'WHERE (m.created_by = :user_id OR m.assigned = :user_id)';
        $params['user_id'] = $user_id;
    }
    $query = "
        SELECT 
            m.*, 
            u.first_name AS user_first_name, 
            u.last_name AS user_last_name, 
            u.image AS user_image, 
            COUNT(responses.id) AS total_responses,
            SUM(CASE WHEN responses.seen = 0 THEN 1 ELSE 0 END) AS unseen_responses
        FROM tickets AS m 
        LEFT JOIN users AS u ON m.created_by = u.id 
        LEFT JOIN ticket_responses AS responses ON m.id = responses.ticket_id 
        $scopeSql
        GROUP BY m.id
        ORDER BY m.date_created DESC";

    $tickets = $ticket->query($query, $params);


    if (!empty($tickets)) {
        foreach ($tickets as $ticket) {
            $fullname = $ticket->user_first_name . ' ' . $ticket->user_last_name;

            $info['data'][] = [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'date_created' => $ticket->date_created,
                'user_first_name' => $ticket->user_first_name,
                'user_last_name' => $ticket->user_last_name,
                'user_full_name' => $fullname,
                'user_full_name_short' => substr($fullname, 0, 18),
                'user_image' => get_image($ticket->user_image),
                'total_responses' => $ticket->total_responses,
                'unseen_responses' => $ticket->unseen_responses,
            ];
        }


        $info['success'] = true;
    }

    echo json_encode($info);
    die;
} else {
    echo json_encode(['success' => false, 'message' => "Record not found."]);
}
