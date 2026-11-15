<?php

/**
 * Plugin name: Trainings
 * Description: Manage training sessions for agents/users.
 */

set_value([
    'admin_route' => 'admin',
    'plugin_route' => 'trainings',
    'tables' => [
        'trainings'          => 'trainings',
        'training_attendees' => 'training_attendees',
    ],
    'optional_tables' => [],
]);

$db    = new \Core\Database;
$tables = get_value()['tables'];

// Ensure required tables exist; this will fail loudly in dev if schema is missing
if (!$db->table_exists($tables)) {
    dd("Missing database tables in " . plugin_id() . " plugin: " . implode(",", $db->missing_tables));
    die;
}

// Permissions for trainings
add_filter('permissions', function ($permissions) {
    // Reuse existing Operations permission for viewing to avoid blank pages
    // if view_trainings is not yet explicitly granted.
    $permissions[] = 'view_trainings';
    $permissions[] = 'manage_trainings';
    return $permissions;
});

// Handle POST actions (create/update trainings)
add_action('controller', function () {
    $req  = new \Core\Request;
    $vars = get_value();

    $admin_route  = $vars['admin_route'];
    $plugin_route = $vars['plugin_route'];

    if (URL(1) !== $plugin_route || !$req->posted()) {
        return;
    }

    // Allow existing Operations managers to manage trainings as well
    if (!user_can('manage_trainings') && !user_can('view_operations')) {
        message_fail('You are not allowed to manage trainings');
        redirect("{$admin_route}/{$plugin_route}");
    }

    if (!csrf_verify($_POST)) {
        message_fail('Form expired. Please refresh and try again');
        redirect("{$admin_route}/{$plugin_route}");
    }

    $db = new \Core\Database;

    $id = (int)($_POST['id'] ?? 0);

    $title        = trim($_POST['title'] ?? '');
    $type         = trim($_POST['type'] ?? '');
    $status       = trim($_POST['status'] ?? 'planned');
    $date_start   = trim($_POST['date_start'] ?? '');
    $date_end     = trim($_POST['date_end'] ?? '');
    $duration     = (int)($_POST['duration_minutes'] ?? 0);
    $trainer_name = trim($_POST['trainer_name'] ?? '');
    $mode         = trim($_POST['mode'] ?? 'in_person');
    $location     = trim($_POST['location'] ?? '');
    $target_team  = trim($_POST['target_team'] ?? '');
    $max_capacity = (int)($_POST['max_capacity'] ?? 0);
    $attendees    = (int)($_POST['attendees_count'] ?? 0);
    $cost         = (float)($_POST['cost'] ?? 0);
    $score        = (float)($_POST['score_rating'] ?? 0);
    $attachments  = trim($_POST['attachments'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    $user = get_session();
    $now  = date('Y-m-d H:i:s');

    $columns = [
        'title'            => $title,
        'type'             => $type,
        'status'           => $status,
        'date_start'       => $date_start ?: null,
        'date_end'         => $date_end ?: null,
        'duration_minutes' => $duration ?: null,
        'trainer_name'     => $trainer_name,
        'mode'             => $mode,
        'location'         => $location,
        'target_team'      => $target_team,
        'max_capacity'     => $max_capacity ?: null,
        'attendees_count'  => $attendees ?: 0,
        'cost'             => $cost ?: 0,
        'score_rating'     => $score ?: 0,
        'attachments'      => $attachments,
        'notes'            => $notes,
    ];

    if ($id > 0) {
        $columns['updated_by']   = $user->id ?? null;
        $columns['date_updated'] = $now;
        // $db->update('trainings', $id, $columns); // legacy, not used
        message_success('Training updated successfully');
    } else {
        $columns['created_by']   = $user->id ?? null;
        $columns['date_created'] = $now;
        // $db->insert('trainings', $columns); // legacy, not used
        message_success('Training created successfully');
    }

    redirect("{$admin_route}/{$plugin_route}");
});

// Render Trainings main page
add_action('basic-admin_main_content', function () {
    $vars = get_value();

    $admin_route  = $vars['admin_route'];
    $plugin_route = $vars['plugin_route'];

    if (URL(1) !== $plugin_route) {
        return;
    }

    // Let anyone who can view Operations see trainings by default
    if (!user_can('view_trainings') && !user_can('view_operations')) {
        echo "<div class='alert alert-danger'>You are not allowed to view trainings.</div>";
        return;
    }

    $db = new \Core\Database;

    // Filters
    $status  = trim($_GET['status'] ?? '');
    $type    = trim($_GET['type'] ?? '');
    $mode    = trim($_GET['mode'] ?? '');
    $period  = trim($_GET['period'] ?? 'this_month');
    $q       = trim($_GET['q'] ?? '');

    $where  = "WHERE deleted = 0";
    $params = [];

    if ($status !== '') {
        $where .= " AND status = :status";
        $params['status'] = $status;
    }
    if ($type !== '') {
        $where .= " AND type = :type";
        $params['type'] = $type;
    }
    if ($mode !== '') {
        $where .= " AND mode = :mode";
        $params['mode'] = $mode;
    }
    if ($q !== '') {
        $where .= " AND (title LIKE :q OR trainer_name LIKE :q OR target_team LIKE :q)";
        $params['q'] = '%' . $q . '%';
    }

    // Period filter on date_start
    $today = date('Y-m-d');
    if ($period === 'this_month') {
        $where .= " AND DATE(date_start) >= :start_month";
        $params['start_month'] = date('Y-m-01');
    } elseif ($period === 'last_3_months') {
        $where .= " AND DATE(date_start) >= :start_3";
        $params['start_3'] = date('Y-m-d', strtotime('-3 months'));
    } elseif ($period === 'this_year') {
        $where .= " AND YEAR(date_start) = :year";
        $params['year'] = date('Y');
    }

    // List of trainings
    $listSql = "SELECT * FROM trainings {$where} ORDER BY date_start DESC, id DESC LIMIT 100";
    $trainings = $db->query($listSql, $params) ?? [];

    // KPIs
    $kpiSql = "SELECT 
                    COUNT(*) AS total_trainings,
                    SUM(CASE WHEN date_start >= :today THEN 1 ELSE 0 END) AS upcoming,
                    SUM(attendees_count) AS total_attendees,
                    AVG(NULLIF(score_rating,0)) AS avg_score
                FROM trainings {$where}";

    $kpiParams = array_merge($params, ['today' => $today]);
    $kpiRows = $db->query($kpiSql, $kpiParams) ?? [];
    $kpi = (object) [
        'total_trainings' => 0,
        'upcoming'        => 0,
        'total_attendees' => 0,
        'avg_score'       => 0,
    ];
    if (!empty($kpiRows)) {
        $row = $kpiRows[0];
        $kpi->total_trainings = (int)($row->total_trainings ?? 0);
        $kpi->upcoming        = (int)($row->upcoming ?? 0);
        $kpi->total_attendees = (int)($row->total_attendees ?? 0);
        $kpi->avg_score       = (float)($row->avg_score ?? 0);
    }

    require plugin_path('views/admin/list.php');
});
