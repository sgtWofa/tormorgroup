<?php

/**
 * @var string $admin_route
 * @var string $plugin_route
 * @var object|null $row
 * @var array $responses
 * @var array $onlineUserIds
 */
?>
<?php if (user_can('view_tickets_details')):

    $session = get_session();
    if (!empty($session->id)) {
        $username = $session->first_name . ' ' . $session->last_name;
        $user_id = $session->id;
    }

?>

    <style type="text/css">
        /* Shared styling for all responses */
        .ticket-detail-page .alert {
            margin-bottom: .45rem;
            border-radius: 7px;
            padding: 6px 8px;
        }

        .ticket-detail-page .form-control {
            border-color: var(--bs-border-color);
        }

        .ticket-detail-page .my-reply {
            /*border-bottom: 1px solid #0d6efd; Blue border */
            border-left: 5px solid #0d6efd;
            /* Blue border */
            background: rgba(206, 219, 244, 0.06);
            margin-left: 5em;
            min-height: 0;
        }

        .ticket-detail-page .original-message {
            border-bottom: 1px solid #0d6efd50;
            /*Blue border*/
            border-left: 5px solid #0d6efd50;
            /* Blue border */
            background: rgba(206, 219, 244, 0.05);
            min-height: 0;
        }

        .ticket-detail-page .feedback {
            /*border: 1px solid #8cd481fc;  Green border */
            border-left: 5px solid #8cd481fc;
            /* Green border */
            background: rgba(164, 223, 142, 0.10);
            margin-right: 5em;
            min-height: 0;
        }

        /* Hover Effect for Responses */
        .ticket-detail-page .alert:hover {
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Input styling for response form */
        .ticket-detail-page #response {
            resize: none;
            min-height: 50px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .ticket-detail-page .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .ticket-detail-page .receiver-badge {
            margin-top: -1.5em;
            border-right: 3px solid white;
            border-top: 1px solid white;
            border-bottom: none;
            border-left: none;
        }

        .ticket-detail-page .response-status {
            font-size: 12px;
            margin-left: 8px;
            color: gray;
        }

        .ticket-detail-page .status-seen {
            color: #0d6efd;
        }

        .ticket-detail-page .chat-area .card-body {
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            overflow-y: auto;
            scrollbar-width: thin;
            /* Firefox */
            scrollbar-color: #d1d1d1 transparent;
            /* Firefox scrollbar color */
        }

        /* Webkit-based browser custom scrollbar styling */
        .ticket-detail-page .chat-area .card-body::-webkit-scrollbar {
            width: 4px;
            /* Scrollbar width */
        }

        .ticket-detail-page .chat-area .card-body::-webkit-scrollbar-track {
            background: transparent;
            /* Track color */
        }

        .ticket-detail-page .chat-area .card-body::-webkit-scrollbar-thumb {
            background-color: #d1d1d1;
            /* Thumb color */
            border-radius: 10px;
            /* Round the thumb */
        }

        .ticket-detail-page .chat-area .card-body::-webkit-scrollbar-thumb:hover {
            background-color: #a8a8a8;
            /* Thumb color on hover */
        }

        .ticket-detail-page .note-editable {
            padding: 20px;
            overflow: auto;
            word-wrap: break-word;
            background: rgba(0, 0, 0, 0.03);
            color: blue;
            min-height: 50px;
            border-radius: 10px 0px 0px 10px;
        }

        .ticket-detail-page .note-editor.note-airframe,
        .ticket-detail-page .note-editor.note-frame {
            border: 1px solid var(--bs-border-color);
            border-radius: .7rem;
            overflow: hidden;
        }

        .ticket-detail-page .chat-area>.card-header {
            background: var(--bs-tertiary-bg);
            border-bottom: 1px solid var(--bs-border-color);
            padding: .9rem 1rem;
        }

        .ticket-detail-page .response-text {
            /*white-space: pre-wrap;  Preserve newlines and wrap long lines */
            word-break: break-word;
            /* Break long words to prevent overflow */
        }

        .ticket-detail-page .chat-area [data-response-id] {
            padding: 6px 10px !important;
            margin-bottom: .45rem !important;
        }

        .ticket-detail-page .chat-area [data-response-id]>strong {
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            margin: 0 !important;
            line-height: 1.15;
        }

        .ticket-detail-page .chat-area [data-response-id] p {
            margin: 0 !important;
            line-height: 1.3;
        }

        .ticket-detail-page .chat-area [data-response-id] .response-text>div,
        .ticket-detail-page .chat-area [data-response-id] .response-text .p-3 {
            padding: 4px 0 !important;
        }

        .ticket-detail-page .chat-area [data-response-id] .badge {
            line-height: 1.1;
        }

        .ticket-detail-page .chat-area [data-response-id] .image-thumbnail.p-2 {
            padding: 0 4px 0 0 !important;
        }

        .ticket-detail-page .wrap {
            white-space: nowrap;
        }

        .ticket-detail-page.ticket-container {
            display: flex;
            flex-direction: row;
            width: 100%;
            min-width: 0;
            gap: .75rem;
            overflow: hidden;
            position: relative;
            height: calc(100dvh - 110px);
            min-height: 360px;
            max-height: calc(100dvh - 150px);
            align-items: stretch;
        }

        /* Left Panel */
        .ticket-detail-page .search-tickets {
            flex: 0 0 clamp(210px, 25%, 290px);
            width: auto;
            min-width: 0;
            transition: transform 0.3s ease;
            background-color: rgba(0, 0, 0, 0.1);
            box-shadow: 2px 0px 5px rgba(0, 0, 0, 0.3);
            overflow-y: auto;
            height: 100%;
            min-height: 0;
        }

        .ticket-detail-page .search-tickets .table-responsive {
            height: 100%;
            overflow-y: auto;
        }

        .ticket-detail-page .search-tickets.hidden {
            transform: translateX(-100%);
            position: absolute;
            z-index: 10;
            box-shadow: 2px 0px 5px rgba(0, 0, 0, 0.3);
        }

        /* Right Panel */
        .ticket-detail-page .chat-area {
            flex: 1 1 0;
            width: auto;
            max-width: none;
            min-width: 0;
            transition: margin-left 0.3s ease, width 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            max-height: 100%;
            min-height: 0;
            overflow: hidden;
        }

        .ticket-detail-page .chat-area.full-width {
            max-width: 100%;
        }

        .ticket-detail-page .chat-area .card-header,
        .ticket-detail-page .chat-area .card-footer {
            flex: 0 0 auto;
        }

        .ticket-detail-page .chat-area .card-footer {
            background: var(--bs-body-bg);
            border-top: 1px solid rgba(0, 0, 0, .12);
            max-height: 210px;
            overflow-y: auto;
        }

        .ticket-detail-page .ticket-online-dots {
            margin-bottom: .5rem !important;
            padding: .45rem .7rem !important;
        }

        .ticket-detail-page #ticket-form .input-group:first-of-type {
            min-height: 38px;
        }

        .ticket-detail-page #ticket-form .response-input {
            min-height: 48px !important;
            max-height: 90px;
        }

        .ticket-detail-page a {
            text-decoration: none;
        }

        /* Toggle Button */
        .ticket-detail-page .toggle-btn {
            z-index: 50;
            background: rgba(0, 0, 0, 0.02);
            color: #111;
            border: none;
            display: none;
            cursor: pointer;
        }

        .ticket-detail-page .ticket-row:hover {
            background-color: rgba(255, 193, 7, 0.1) !important;
            /* Bootstrap warning color in rgba */
        }

        .ticket-detail-page .ticket-row.active {
            background-color: rgba(255, 193, 7, 0.3) !important;
            /* Bootstrap warning color in rgba */
        }

        /* Target the badge inside the active ticket row */
        .ticket-detail-page .ticket-row.active .rounded-pill {
            background-color: black !important;
            color: white !important;
        }

        .ticket-detail-page {
            width: min(100%, 1440px);
            max-width: 1440px;
            gap: 1rem;
            margin: 1rem auto !important;
            padding: .75rem !important;
            border: 1px solid var(--bs-border-color);
            border-radius: 1rem !important;
            background: var(--bs-tertiary-bg);
            box-shadow: 0 .8rem 2rem rgba(20, 35, 60, .08);
        }

        .ticket-detail-page .search-tickets {
            flex: 0 0 clamp(210px, 25%, 290px);
            width: auto;
            border: 1px solid var(--bs-border-color);
            border-radius: .8rem;
            background: var(--bs-body-bg);
            box-shadow: none;
        }

        .ticket-detail-page .search-tickets table {
            margin: 0;
        }

        .ticket-detail-page .search-tickets th {
            padding: .8rem;
            background: var(--bs-body-bg);
        }

        .ticket-detail-page #search-tickets {
            min-height: 2.7rem;
            border: 1px solid var(--bs-border-color) !important;
            border-radius: .6rem 0 0 .6rem;
        }

        .ticket-detail-page .search-tickets .input-group-text {
            border: 0;
            border-radius: 0 .6rem .6rem 0;
        }

        .ticket-detail-page .ticket-row {
            border-color: var(--bs-border-color);
            transition: background .15s ease;
        }

        .ticket-detail-page .ticket-row:hover {
            background: rgba(13, 110, 253, .08) !important;
        }

        .ticket-detail-page .ticket-row.active {
            background: rgba(13, 110, 253, .12) !important;
        }

        .ticket-detail-page .chat-area {
            max-width: none;
            min-width: 0;
            width: auto;
            border: 1px solid var(--bs-border-color);
            border-radius: .85rem;
            background: var(--bs-body-bg);
            box-shadow: 0 .4rem 1.2rem rgba(20, 35, 60, .05);
        }

        .ticket-detail-page .chat-area.full-width {
            max-width: none;
        }

        .ticket-detail-page .chat-area .card-body {
            padding: 1rem;
        }

        .ticket-detail-page .chat-area .card-body {
            display: flex;
            flex: 1 1 0;
            flex-direction: column;
            min-height: 0;
            overflow-y: auto;
        }

        .ticket-detail-page .ticket-message-list {
            display: flex;
            flex-direction: column;
            flex: 0 0 auto;
            gap: .75rem;
            min-width: 0;
            width: 100%;
        }

        .ticket-detail-page .original-message {
            width: min(100%, 760px);
            max-width: 100%;
            margin: 0 !important;
            padding: .9rem 1rem !important;
            border: 1px solid rgba(13, 110, 253, .15);
            border-left: 4px solid var(--bs-primary);
            border-radius: .8rem;
            background: rgba(13, 110, 253, .045);
            overflow-wrap: anywhere;
        }

        .ticket-detail-page .original-message img,
        .ticket-detail-page .original-message video,
        .ticket-detail-page .original-message iframe {
            max-width: 100% !important;
            height: auto !important;
        }

        .ticket-detail-page .original-message img {
            display: block;
            width: auto !important;
            max-height: min(34vh, 280px);
            object-fit: contain;
            object-position: left center;
        }

        .ticket-detail-page .my-reply,
        .ticket-detail-page .feedback {
            width: fit-content;
            max-width: min(82%, 680px);
            margin: 0 !important;
            padding: .7rem .85rem !important;
            border: 1px solid var(--bs-border-color);
            border-left: 4px solid var(--bs-primary);
            border-radius: .8rem;
            background: var(--bs-tertiary-bg);
            overflow-wrap: anywhere;
        }

        .ticket-detail-page .my-reply {
            align-self: flex-end;
        }

        .ticket-detail-page .feedback {
            align-self: flex-start;
        }

        .ticket-detail-page .response-text {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .ticket-detail-page .response-text img {
            display: block;
            width: auto !important;
            max-width: min(100%, 420px) !important;
            max-height: min(45vh, 340px) !important;
            object-fit: contain;
            object-position: left center;
            border-radius: .65rem;
            cursor: pointer;
        }

        .ticket-detail-page .response-text>div {
            overflow-wrap: anywhere;
        }

        .ticket-detail-page .response-author {
            display: block;
            margin: 0 0 .35rem !important;
            padding: 0 !important;
            font-size: .84rem;
        }

        .ticket-detail-page .response-content {
            min-width: 0;
        }

        .ticket-detail-page .response-meta {
            display: block;
            margin-top: .35rem;
            text-align: right;
            font-size: .7rem;
        }

        .ticket-detail-page .response-image {
            position: relative;
            display: inline-block;
            max-width: 100%;
            margin: .2rem 0 .5rem;
            border-radius: .65rem;
            background: var(--bs-tertiary-bg);
            overflow: hidden;
        }

        .ticket-detail-page .response-image .download-icon {
            position: absolute;
            top: .5rem;
            right: .5rem;
            padding: .4rem .5rem;
            border-radius: .5rem;
            color: #fff;
            background: rgba(0, 0, 0, .55);
        }

        .ticket-detail-page .response-text-body {
            white-space: pre-line;
        }

        .ticket-image-modal {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(0, 0, 0, .82);
        }

        .ticket-image-modal img {
            max-width: min(100%, 1200px);
            max-height: 90dvh;
            object-fit: contain;
            border-radius: .5rem;
        }

        .ticket-image-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: grid;
            width: 2.75rem;
            height: 2.75rem;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 50%;
            color: #fff;
            background: rgba(0, 0, 0, .55);
        }

        .ticket-detail-page .feedback {
            border-left-color: #198754;
        }

        .ticket-detail-page .chat-area .card-footer {
            padding: 1rem;
            background: var(--bs-body-bg);
        }

        .ticket-detail-page #ticket-form .response-input {
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
            border: 1px solid var(--bs-border-color);
            border-radius: .65rem 0 0 .65rem;
            background: var(--bs-tertiary-bg);
        }

        .ticket-detail-page #ticket-form,
        .ticket-detail-page #ticket-form .response-composer {
            width: 100%;
        }

        .ticket-detail-page #ticket-form .response-composer {
            display: flex;
            flex-flow: row nowrap;
            align-items: stretch;
        }

        .ticket-detail-page #ticket-form button[type="submit"] {
            min-width: 3.25rem;
            border-radius: 0 .65rem .65rem 0;
        }

        .ticket-detail-page .toggle-btn {
            color: var(--bs-body-color);
        }


        /* Small Screen Styling */
        @media (max-width: 900px) {
            .ticket-detail-page .toggle-btn {
                display: block;
            }

            .ticket-detail-page .search-tickets {
                position: absolute;
                transform: translateX(0%);
                z-index: 1;
                top: 3.5rem;
                left: .5rem;
                width: min(84vw, 320px);
                max-width: calc(100% - 1rem);
                max-height: calc(100% - 4rem);
            }

            .ticket-detail-page .chat-area {
                flex: 1 1 0;
                width: 100%;
                max-width: 100%;
            }

            .ticket-detail-page.ticket-container {
                height: calc(100dvh - 190px);
                min-height: 360px;
                max-height: calc(100dvh - 140px);
            }

            .ticket-detail-page .search-tickets {
                overflow-y: scroll;
                scrollbar-width: thin;
                /* Firefox */
                scrollbar-color: #d1d1d1 transparent;
                /* Firefox scrollbar color */
            }

            /* Webkit-based browser custom scrollbar styling */
            .ticket-detail-page .search-tickets::-webkit-scrollbar {
                width: 4px;
                /* Scrollbar width */
            }

            .ticket-detail-page .search-tickets::-webkit-scrollbar-track {
                background: transparent;
                /* Track color */
            }

            .ticket-detail-page .search-tickets::-webkit-scrollbar-thumb {
                background-color: #d1d1d1;
                /* Thumb color */
                border-radius: 10px;
                /* Round the thumb */
            }

            .ticket-detail-page .search-tickets::-webkit-scrollbar-thumb:hover {
                background-color: #a8a8a8;
                /* Thumb color on hover */
            }

            .ticket-detail-page .ticket-row:hover {
                background-color: rgba(255, 193, 7, 0.7) !important;
                /* Bootstrap warning color in rgba */
            }

            .ticket-detail-page .ticket-row.active {
                background-color: rgba(255, 193, 7, 0.9) !important;
                /* Bootstrap warning color in rgba */
            }
        }

        @media (max-width: 600px) {
            .ticket-detail-page {
                width: 100%;
                gap: .5rem;
                margin: .5rem auto !important;
                padding: .45rem !important;
                border-radius: .7rem !important;
            }

            .ticket-detail-page .chat-area .card-header,
            .ticket-detail-page .chat-area .card-body,
            .ticket-detail-page .chat-area .card-footer {
                padding: .65rem !important;
            }

            .ticket-detail-page .my-reply,
            .ticket-detail-page .feedback {
                max-width: 92%;
            }

            .ticket-detail-page .original-message {
                padding: .7rem !important;
            }

            .ticket-detail-page .response-text img {
                max-height: 35vh !important;
            }

            .ticket-detail-page #ticket-form .input-group.response {
                display: flex;
                flex-flow: row nowrap;
            }

            .ticket-detail-page #ticket-form .response-input {
                flex: 1 1 auto;
                width: 1%;
                min-width: 0;
            }
        }

        /* Large Screen - Ensure Left Panel is Visible */
        @media (min-width: 901px) {
            .ticket-detail-page .search-tickets.hidden {
                transform: translateX(0);
                /* Reset for larger screens */
            }
        }
    </style>


    <?php if (!empty($row)): ?>

        <div class="row mx-auto p-1 rounded ticket-container ticket-detail-page">
            <!-- left panel -->
            <div class="col-lg-3 col-md-4 search-tickets">
                <!-- table for larger and medium screeens -->
                <div class="table-responsive">
                    <table class="table table-hover table-bordered rounded shadow">
                        <thead>
                            <tr>
                                <th>
                                    <!-- Search Form -->
                                    <form class="input-group col-10 d-flex mb-3" id="search-form">
                                        <input type="text" id="search-tickets" class="form-control" placeholder="Search ticket"
                                            name="find" autofocus />
                                        <button type="button" class="input-group-text btn btn-primary" onclick="searchTickets()"
                                            aria-label="Search tickets">
                                            <i class="fa-solid fa-search" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="ticket-list">
                            <!-- Ticket List -->
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Right panel -->
            <div class="col-lg-9 col-md-8 col-sm-12 card p-1 chat-area">
                <div class="card-header" id="ticketChatHeader">
                    <div id="message-container row alert alert-success" style="display: none; color: green;"></div>
                    <strong>
                        <!-- Toggle Button -->
                        <button class="toggle-btn" onclick="toggleLeftPanel()">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <span>
                            <small class="">
                                <?php
                                $status = strtolower(trim($row->status ?? ''));
                                $badgeClass = match ($status) {
                                    'in progress' => 'badge bg-success',
                                    'open' => 'badge bg-primary',
                                    'closed' => 'badge bg-secondary text-white',
                                    default => 'badge bg-secondary',
                                };
                                ?>
                                <span class="<?= $badgeClass ?>"
                                    id="ticketStatusBadge"><?= esc($row->status ?? 'Unknown') ?></span>
                            </small>

                            <!-- Close Ticket -->
                            <a class="btn btn-outline-primary btn-sm" id="ticketEditLink"
                                href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/edit/<?= $row->id ?>">
                                <i class="fas fa-pencil-square me-1" aria-hidden="true"></i>Update ticket
                            </a>
                        </span>
                        <span class="mx-2" style="font-size: 10px;" id="ticketCreatorMeta"><code>From:</code> <img
                                class="image-thumbnail" style="width: 20px; height:20px; border-radius:50%;"
                                src="<?= get_image($row->user_image) ?>">
                            <?= $row->user_first_name . ' ' . $row->user_last_name ?? '' ?>
                            <?= tickets_online_indicator_html($row->created_by ?? 0, $onlineUserIds ?? []) ?></span>
                    </strong>
                </div>
                <div class="card-body">
                    <div class="ticket-message-list">
                        <div class="alert original-message" id="ticketOriginalMessage">
                            <strong class="py-2">
                                <label>Subject:</label>
                                <span id="ticketSubjectText"><?= $row->subject ?? '' ?></span>
                            </strong>
                            <div class="row p-2">
                                <span class="px-2 " id="ticketDescriptionText"><?= $row->description ?? '' ?></span>
                                <i class="badge float-end py-1 text-secondary fw-bolder"
                                    id="ticketDateText"><?= get_date_time($row->date_created) ?></i>
                            </div>
                        </div>
                        <?php if (!empty($responses)): ?>
                            <?php foreach ($responses as $response): ?>
                                <?php
                                $isMyReply = (int) $response->created_by === (int) $user_id;
                                $statusClass = match (true) {
                                    $response->seen ?? 'status' => 'status-seen',
                                    $response->received ?? 'status' => 'status-received',
                                    default => 'status-sent',
                                };
                                ?>
                                <div class="alert <?= $isMyReply ? 'my-reply' : 'feedback' ?>" data-response-id="<?= $response->id ?>">
                                    <strong class="response-author">
                                        <strong>
                                            <span class="image-thumbnail p-2">
                                                <img style="width: 20px; height: 20px; border-radius: 50%;"
                                                    src="<?= get_image($response->user_image) ?>" alt="User Image">
                                            </span>
                                            <?= tickets_online_indicator_html($response->created_by ?? 0, $onlineUserIds ?? []) ?>
                                            <?= esc($response->user_first_name . ' ' . $response->user_last_name ?? 'Unknown User') ?>
                                        </strong>
                                    </strong>
                                    <div class="response-content">
                                        <div class="response-text">
                                            <?php if (!empty($response->image)): ?>
                                                <div class="image-thumbnail response-image">
                                                    <!-- Image for full view -->
                                                    <img src='<?= get_image($response->image) ?>' class='response-attachment'
                                                        alt="Response Image">

                                                    <!-- Download icon -->
                                                    <a href="<?= get_image($response->image) ?>" download="response-image.jpg"
                                                        class="download-icon">
                                                        <i class="fas fa-arrow-down" aria-hidden="true"></i>
                                                    </a>
                                                </div>
                                            <?php endif ?>
                                            <?php if (trim((string)($response->response ?? '')) !== ''): ?>
                                                <div class="response-text-body"><?= esc($response->response) ?></div>
                                            <?php endif ?>
                                        </div>
                                        <strong class="response-meta">
                                            <span class="badge">
                                                <i class="" style="color: darkgray;">
                                                    <?= get_date_time($response->date_created) ?>
                                                </i>
                                                <i class="fas fa-check double-check response-status <?= $statusClass ?> "></i>
                                            </span>
                                        </strong>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">

                    <?php if ($status !== strtolower('')): ?>
                        <form onsubmit="page.submit(event)" method="post" enctype="multipart/form-data" id="ticket-form"
                            data-bo-allow-multi-submit="1">
                            <?= csrf() ?>
                            <input class="d-none" type="text" name="ticket_id" value="<?= $row->id ?>" id="ticketFormTicketId">
                            <div class="input-group" style="background: rgba(0, 0, 0, 0.02);">
                                <!-- Insert Image -->
                                <label class="btn btn-outline-secondary" style="display: block;cursor: pointer; border: none;"
                                    title="insert image">
                                    <i class="fa-solid fa-image"></i>
                                    <input onchange="page.displayImage(event)" type="file" class="image-response" name="image"
                                        style="display:none">

                                    <?php if (!empty($errors['image'])): ?>
                                        <small class="text-danger"><?= $errors['image'] ?></small>
                                    <?php endif ?>
                                    <img src="<?= get_image('') ?>" class="img-thumbnail image-preview d-none input-group-text"
                                        style="width:90%;height:100px;object-fit: cover">
                                </label>
                            </div>

                            <!-- Response Input -->
                            <div class="input-group response-composer">
                                <?= csrf() ?>
                                <textarea name="response" value="<?= old_value('response') ?>" class="form-control response-input"
                                    id="response" placeholder="Write a response here..."></textarea>
                                <button type="submit" class="btn bt-md btn-outline-primary input-group-text" title="Send Response">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </div>

                            <?php if (!empty($errors['response'])): ?>
                                <small class="text-danger"><?= $errors['response'] ?></small>
                            <?php endif ?>
                        </form>
                    <?php endif ?>
                </div>
                <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>">
                    <button class="btn btn-md btn-outline-danger btn-borderless fw-bolder" style="border:none;">
                        <i class="fa-solid fa-chevron-left"></i> Back
                    </button>
                </a>
            </div>
        </div>
        </div>

    <?php else: ?>
        <div class="alert alert-danger text-center">
            That record was not found!
        </div>

        <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>">
            <button class="btn btn-primary">Back</button>
        </a>
    <?php endif ?>

<?php else: ?>
    <div class="alert alert-danger text-center">
        Access denied. You don't have permission for this action.
    </div>
<?php endif ?>

<!-- include libraries(jQuery, bootstrap) -->
<script src="<?= plugin_http_path('assets/js/jquery-3.7.1.min.js') ?>"></script>
<!-- include summernote css/js -->
<link href="<?= plugin_http_path('summernote-0.8.18-dist/summernote-lite.min.css') ?>" rel="stylesheet">
<script src="<?= plugin_http_path('summernote-0.8.18-dist/summernote-lite.min.js') ?>"></script>

<script type="text/javascript">
    // Define WebSocket connection globally
    var conn = null;
    var currentTicketId = String(<?= json_encode((string)($row->id ?? '')) ?>);

    function parseTicketJsonResponse(responseText) {
        const response = String(responseText || '').trim();
        const jsonStart = response.search(/[\[{]/);
        if (jsonStart < 0) {
            throw new SyntaxError('Response did not contain a JSON object or array.');
        }
        return JSON.parse(response.slice(jsonStart));
    }

    function escapeTicketHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [char];
        });
    }

    function getActiveTicketId() {
        return currentTicketId || localStorage.getItem('activeTicketId') || '';
    }

    function highlightActiveTicketRow(scrollIntoView) {
        const activeTicketId = getActiveTicketId();
        const ticketRows = document.querySelectorAll('.ticket-row');
        ticketRows.forEach(r => r.classList.remove('active'));
        if (!activeTicketId) return;
        const activeRow = document.querySelector(`.ticket-row[data-id='${activeTicketId}']`);
        if (activeRow) {
            activeRow.classList.add('active');
            localStorage.setItem('activeTicketId', activeTicketId);
            if (scrollIntoView) {
                try {
                    activeRow.scrollIntoView({
                        block: 'nearest'
                    });
                } catch (e) {}
            }
        }
    }

    (function() {
        try {
            if (!('WebSocket' in window)) return;
            var wsScheme = (window.location && window.location.protocol === 'https:') ? 'wss' : 'ws';
            var wsHost = (window.location && window.location.hostname) ? window.location.hostname : 'localhost';
            var wsUrl = wsScheme + '://' + wsHost + ':8080';
            conn = new WebSocket(wsUrl);
        } catch (e) {
            conn = null;
        }
    })();

    (function() {
        var ticketId = parseInt(<?= json_encode($row->id) ?>);
        var adminRoute = <?= json_encode($admin_route) ?>;
        var pluginRoute = <?= json_encode($plugin_route) ?>;
        var pollMs = 2000;
        var polling = false;
        var ticketLoadSequence = 0;
        var ticketLoadController = null;
        var lastId = 0;
        var renderedResponseIds = new Set();

        try {
            var nodes = document.querySelectorAll('[data-response-id]');
            nodes.forEach(function(n) {
                var v = parseInt(n.getAttribute('data-response-id') || '0', 10) || 0;
                if (v > lastId) lastId = v;
                if (v > 0) renderedResponseIds.add(String(v));
            });
        } catch (e) {}

        function ticketStatusClass(status) {
            status = String(status || '').trim().toLowerCase();
            if (status === 'in progress') return 'badge bg-success';
            if (status === 'open') return 'badge bg-primary';
            if (status === 'closed') return 'badge bg-secondary text-white';
            return 'badge bg-secondary';
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [char];
            });
        }

        function appendResponse(data, options) {
            try {
                options = options || {};
                const responseId = parseInt(data.id || 0, 10) || 0;
                if (responseId > 0) {
                    if (renderedResponseIds.has(String(responseId)) || document.querySelector(
                            `[data-response-id="${responseId}"]`)) {
                        if (responseId > lastId) lastId = responseId;
                        return;
                    }
                    renderedResponseIds.add(String(responseId));
                    if (responseId > lastId) lastId = responseId;
                }
                const isMyReply = parseInt(data.sender_id) === parseInt(<?= json_encode($user_id) ?>);
                const responseContainer = document.createElement('div');
                responseContainer.className = `alert ${isMyReply ? 'my-reply' : 'feedback'}`;
                if (responseId > 0) responseContainer.setAttribute('data-response-id', String(responseId));

                const imageUrl = escapeTicketHtml(data.image || '');
                const responseImage = data.image ?
                    `<div class="image-thumbnail response-image">
							<img src="${imageUrl}" class="response-attachment" alt="Response image">
							<a href="${imageUrl}" download="response-image.jpg" class="download-icon">
								<i class="fas fa-arrow-down" aria-hidden="true"></i>
							</a>
						</div>` :
                    '';

                responseContainer.innerHTML = `
						<strong class="response-author">
							<img class="rounded-circle me-1" style="width:20px;height:20px;object-fit:cover" src="${escapeTicketHtml(data.user_image || '')}" alt="">
							${escapeTicketHtml(data.sender_name || 'Unknown User')}
						</strong>
						<div class="response-content">
							<div class="response-text">
								${responseImage}
								${String(data.response || '').trim() !== '' ? `<div class="response-text-body">${escapeTicketHtml(data.response)}</div>` : ''}
							</div>
							<strong class="response-meta">
								<span class="badge">${escapeTicketHtml(data.date_created || '')}</span>
							</strong>
						</div>
					`;
                document.querySelector('.ticket-message-list').appendChild(responseContainer);
                scrollChatToBottom();
                try {
                    if (typeof window.__ticketsObserveNewResponse === 'function')
                        window.__ticketsObserveNewResponse(responseContainer);
                } catch (e) {}
                if (!options.silent && !isMyReply && typeof window.ticketsPlayNotificationBeep === 'function') {
                    window.ticketsPlayNotificationBeep();
                }
                if (!options.silent && typeof window.ticketsMarkNotificationSeen === 'function') {
                    window.ticketsMarkNotificationSeen(data.ticket_id, data.id);
                }
                if (!options.silent) {
                    try {
                        updateUnseenBadge(data.ticket_id);
                    } catch (e) {}
                }
            } catch (e) {}
        }

        function scrollChatToBottom() {
            try {
                var body = document.querySelector('.chat-area .card-body');
                if (body) body.scrollTop = body.scrollHeight;
            } catch (e) {}
        }
        window.__ticketsAppendResponse = appendResponse;
        window.__ticketsScrollChatToBottom = scrollChatToBottom;
        window.__ticketsSetLastResponseId = function(id) {
            var v = parseInt(id || 0, 10) || 0;
            if (v > lastId) lastId = v;
        };

        function resetResponseState() {
            lastId = 0;
            renderedResponseIds = new Set();
        }

        function replaceResponses(responses) {
            const responseWrap = document.querySelector('.chat-area .ticket-message-list');
            const original = document.getElementById('ticketOriginalMessage');
            if (!responseWrap || !original) return;
            responseWrap.innerHTML = '';
            responseWrap.appendChild(original);
            resetResponseState();
            (Array.isArray(responses) ? responses : []).forEach(function(item) {
                appendResponse(item, {
                    silent: true
                });
            });
            try {
                initSeenObserver();
            } catch (e) {}
            scrollChatToBottom();
        }

        function updateTicketHeader(ticket) {
            ticket = ticket || {};
            const statusBadge = document.getElementById('ticketStatusBadge');
            if (statusBadge) {
                statusBadge.className = ticketStatusClass(ticket.status);
                statusBadge.textContent = ticket.status || 'Unknown';
            }
            const editLink = document.getElementById('ticketEditLink');
            if (editLink && ticket.id) {
                editLink.href = `<?= ROOT ?>/${adminRoute}/${pluginRoute}/edit/${ticket.id}`;
            }
            const creator = document.getElementById('ticketCreatorMeta');
            if (creator) {
                creator.innerHTML = '<code>From:</code> ' +
                    '<img class="image-thumbnail" style="width: 20px; height:20px; border-radius:50%;" src="' +
                    escapeHtml(ticket.creator_image || '') + '"> ' +
                    escapeHtml(ticket.creator_name || '');
            }
            const subject = document.getElementById('ticketSubjectText');
            if (subject) subject.textContent = ticket.subject || '';
            const description = document.getElementById('ticketDescriptionText');
            if (description) description.innerHTML = ticket.description || '';
            const dateText = document.getElementById('ticketDateText');
            if (dateText) dateText.textContent = ticket.date_created || '';
            const formTicketId = document.getElementById('ticketFormTicketId');
            if (formTicketId && ticket.id) formTicketId.value = ticket.id;
        }

        async function loadTicketChat(nextTicketId, nextUrl) {
            nextTicketId = parseInt(nextTicketId || 0, 10) || 0;
            if (!nextTicketId || nextTicketId === ticketId) return;
            const sequence = ++ticketLoadSequence;
            const body = document.querySelector('.chat-area .card-body');
            const detailUrl = `<?= ROOT ?>/${adminRoute}/${pluginRoute}/detail/${nextTicketId}`;
            if (ticketLoadController) {
                ticketLoadController.abort();
            }
            ticketLoadController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            try {
                if (body) body.classList.add('opacity-50');
                const res = await fetch(detailUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: ticketLoadController ? ticketLoadController.signal : undefined
                });
                const responseText = await res.text();
                let obj;
                try {
                    obj = parseTicketJsonResponse(responseText);
                } catch (parseError) {
                    parseError.response = {
                        status: res.status,
                        contentType: res.headers.get('Content-Type'),
                        body: responseText
                    };
                    throw parseError;
                }
                if (!res.ok || !obj || !obj.success || !obj.data || !obj.data.ticket) {
                    const requestError = new Error((obj && obj.message) ||
                        `Ticket detail request failed with HTTP ${res.status}.`);
                    requestError.response = {
                        status: res.status,
                        contentType: res.headers.get('Content-Type'),
                        body: obj
                    };
                    throw requestError;
                }
                if (sequence !== ticketLoadSequence) return;
                ticketId = nextTicketId;
                currentTicketId = String(nextTicketId);
                localStorage.setItem('activeTicketId', currentTicketId);
                updateTicketHeader(obj.data.ticket);
                replaceResponses(obj.data.responses || []);
                highlightActiveTicketRow(true);
                refreshCounts();
                if (nextUrl && window.history && window.history.pushState) {
                    window.history.pushState({
                        ticketId: nextTicketId
                    }, '', nextUrl);
                }
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                if (sequence !== ticketLoadSequence) return;
                console.error('Unable to load selected ticket; opening the full ticket page instead.', {
                    ticketId: nextTicketId,
                    url: detailUrl,
                    error: e,
                    response: e.response || null
                });
                window.location.assign(nextUrl || detailUrl.replace('/detail/', '/view/'));
            } finally {
                if (sequence === ticketLoadSequence && body) body.classList.remove('opacity-50');
            }
        }

        window.__ticketsLoadTicketChat = loadTicketChat;

        async function poll() {
            if (polling) return;
            polling = true;
            try {
                var u = `<?= ROOT ?>/${adminRoute}/${pluginRoute}/poll/${ticketId}?after=${lastId}`;
                var res = await fetch(u, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                var obj = await res.json();
                if (obj && obj.success && Array.isArray(obj.data)) {
                    obj.data.forEach(function(item) {
                        var rid = parseInt(item.id || 0, 10) || 0;
                        if (rid > lastId) lastId = rid;
                        appendResponse(item);
                    });
                    refreshCounts();
                }
            } catch (e) {}
            polling = false;
        }

        async function refreshCounts() {
            try {
                var u = `<?= ROOT ?>/${adminRoute}/${pluginRoute}/counts/${ticketId}`;
                var res = await fetch(u, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                var obj = await res.json();
                if (!obj || !obj.success || !obj.data) return;
                var ticketUnseen = parseInt(obj.data.ticket_unseen || 0, 10) || 0;
                var totalUnseen = parseInt(obj.data.total_unseen || 0, 10) || 0;

                var ticketRow = document.querySelector(`.ticket-row[data-id='${ticketId}']`);
                if (ticketRow) {
                    var badge = ticketRow.querySelector('.unseenCount');
                    if (badge) badge.textContent = String(ticketUnseen);
                }

                var navTickets = document.querySelector('#navItem_Tickets');
                if (navTickets) {
                    var navBadge = navTickets.querySelector('[data-notification-badge="view_tickets"]');
                    if (totalUnseen > 0) {
                        if (!navBadge) {
                            navBadge = document.createElement('span');
                            navBadge.className = 'badge fw-bolder rounded-pill';
                            navBadge.setAttribute('data-notification-badge', 'view_tickets');
                            navBadge.setAttribute('data-notification-urgency', 'serious');
                            var a = navTickets.querySelector('a.nav-link');
                            if (a) a.appendChild(navBadge);
                            if (window && window.tormorApplyPillStyle) {
                                try {
                                    window.tormorApplyPillStyle(navBadge);
                                } catch (e) {}
                            }
                        }
                        navBadge.textContent = String(totalUnseen);
                    } else {
                        if (navBadge) navBadge.remove();
                    }
                }
            } catch (e) {}
        }

        function initSeenObserver() {
            try {
                if (!('IntersectionObserver' in window)) return;
                if (window.__ticketsSeenObserver) {
                    try {
                        window.__ticketsSeenObserver.disconnect();
                    } catch (e) {}
                }
                var pendingMaxId = 0;
                var timer = null;
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) return;
                        var el = entry.target;
                        var rid = parseInt(el.getAttribute('data-response-id') || '0', 10) || 0;
                        if (!rid) return;
                        // only mark messages from other user as seen
                        if (el.classList.contains('my-reply')) return;
                        if (rid > pendingMaxId) pendingMaxId = rid;
                    });
                    if (pendingMaxId) {
                        if (timer) clearTimeout(timer);
                        timer = setTimeout(async function() {
                            var upto = pendingMaxId;
                            pendingMaxId = 0;
                            try {
                                await fetch(
                                    `<?= ROOT ?>/${adminRoute}/${pluginRoute}/seen/${ticketId}?upto=${upto}`, {
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        }
                                    });
                                refreshCounts();
                            } catch (e) {}
                        }, 250);
                    }
                }, {
                    root: null,
                    threshold: 0.6
                });

                window.__ticketsSeenObserver = observer;
                document.querySelectorAll('[data-response-id]').forEach(function(el) {
                    observer.observe(el);
                });
            } catch (e) {}
        }

        window.__ticketsObserveNewResponse = function(el) {
            try {
                if (window.__ticketsSeenObserver) window.__ticketsSeenObserver.observe(el);
            } catch (e) {}
        };

        initSeenObserver();
        refreshCounts();
        poll();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scrollChatToBottom);
        } else {
            scrollChatToBottom();
        }
        setInterval(function() {
            if (conn && conn.readyState === WebSocket.OPEN) return;
            poll();
        }, pollMs);
        setInterval(refreshCounts, 3000);
    })();

    if (conn) {
        conn.onopen = function(e) {
            // connected
        };

        conn.onmessage = function(e) {
            try {
                const data = JSON.parse(e.data);

                // Only proceed if the user is authorized to see this ticket
                const isAuthorized = parseInt(data.ticket_id) === parseInt(<?= json_encode($row->id) ?>)

                if (!isAuthorized) {
                    console.warn("Unauthorized ticket update received, ignoring...");
                    return; // Skip processing if the user is not authorized
                }

                if (typeof window.__ticketsAppendResponse === 'function') {
                    window.__ticketsAppendResponse(data);
                    if (typeof window.__ticketsSetLastResponseId === 'function')
                        window.__ticketsSetLastResponseId(data.id);
                    if (parseInt(data.sender_id) === parseInt(<?= json_encode($user_id) ?>)) {
                        const responseInput = document.querySelector('.response-input');
                        if (responseInput) responseInput.value = '';
                    }
                    return;
                }

                // Add the new response dynamically
                const isMyReply = parseInt(data.sender_id) === parseInt(<?= json_encode($user_id) ?>);
                const responseContainer = document.createElement('div');
                responseContainer.className = `alert ${isMyReply ? 'my-reply' : 'feedback'}`;

                // Check if image is available and make it downloadable
                const imageUrl = escapeTicketHtml(data.image || '');
                const responseImage = data.image ?
                    `<div class="image-thumbnail response-image">
                            <img src="${imageUrl}" class="response-attachment" alt="Response image">
                            <!-- Download icon -->
                            <a href="${imageUrl}" download="response-image.jpg" class="download-icon">
                                <i class="fas fa-arrow-down" aria-hidden="true"></i>
                            </a>
                       </div>
                       ` :
                    '';

                responseContainer.innerHTML = `
                    <strong class="response-author">
                        <img class="rounded-circle me-1" style="width:20px;height:20px;object-fit:cover" src="${escapeTicketHtml(data.user_image || '')}" alt="">
                        ${escapeTicketHtml(data.sender_name || 'Unknown User')}
                    </strong>
                    <div class="response-content">
                        <div class="response-text">
                            ${responseImage}
                            ${String(data.response || '').trim() !== '' ? `<div class="response-text-body">${escapeTicketHtml(data.response)}</div>` : ''}
                        </div>
                        <strong class="response-meta">
                            <span class="badge">${escapeTicketHtml(data.date_created || '')}</span>
                        </strong>
                    </div>
                `;

                document.querySelector('.ticket-message-list').appendChild(responseContainer);
                if (typeof window.__ticketsScrollChatToBottom === 'function') {
                    window.__ticketsScrollChatToBottom();
                }
                if (!isMyReply && typeof window.ticketsPlayNotificationBeep === 'function') {
                    window.ticketsPlayNotificationBeep();
                }
                if (typeof window.ticketsMarkNotificationSeen === 'function') {
                    window.ticketsMarkNotificationSeen(data.ticket_id, data.id);
                }

                // Dynamically update unseen responses for the corresponding ticket
                updateUnseenBadge(data.ticket_id);

                // Clear the input if the sender is the current user
                if (isMyReply) {
                    document.querySelector('.response-input').value = '';
                }
            } catch (error) {
                console.error("Error parsing WebSocket message:", error, e.data);
            }
        };

        conn.onerror = function(e) {
            // If ws server isn't running, avoid noisy console errors.
            try {
                console.debug('WebSocket unavailable');
            } catch (_e) {}
        };

        conn.onclose = function(e) {
            try {
                console.debug('WebSocket closed');
            } catch (_e) {}
        };
    }

    function ticketSwal(title, message, icon) {
        if (window.basicAdminSwalAlert) {
            return window.basicAdminSwalAlert(title, message, icon || 'info');
        }
        if (window.Swal && window.Swal.fire) {
            return window.Swal.fire({
                title: title,
                text: message,
                icon: icon || 'info'
            });
        }
        console[(icon === 'error') ? 'error' : 'log']((title ? title + ': ' : '') + (message || ''));
        return Promise.resolve();
    }

    function ticketSwalConfirm(title, message, icon) {
        if (window.Swal && window.Swal.fire) {
            return window.Swal.fire({
                title: title,
                text: message,
                icon: icon || 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                return !!result.isConfirmed;
            });
        }
        if (window.basicAdminSwalAlert) {
            return window.basicAdminSwalAlert(title, message, icon || 'question').then(function() {
                return true;
            });
        }
        console.log((title ? title + ': ' : '') + (message || ''));
        return Promise.resolve(false);
    }

    function setTicketSubmitState(form, isSubmitting) {
        try {
            if (!form) return;
            form.dataset.submitting = isSubmitting ? '1' : '0';
            const button = form.querySelector('button[type="submit"]');
            const input = form.querySelector('.response-input');
            if (button) button.disabled = !!isSubmitting;
            if (input) input.readOnly = !!isSubmitting;
        } catch (e) {}
    }

    function handleTicketClosure(ticketId) {
        ticketSwal('Ticket Closed', `Ticket #${ticketId} has been closed.`, 'info');
        // Optionally, disable inputs and indicate the ticket is closed
        document.querySelector('.response-input').disabled = true;
        const submitButton = document.querySelector('#ticket-form button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        // Update UI to reflect ticket closure
        const ticketRow = document.querySelector(`.ticket-row[data-id='${ticketId}']`);
        if (ticketRow) {
            ticketRow.classList.add('closed');
        }
    }

    // Function to close ticket
    function closeTicket(ticketId) {
        ticketSwalConfirm('Close Ticket?', 'Are you sure you want to close this ticket?', 'warning').then(function(
            confirmed) {
            if (!confirmed) return;
            const data = {
                action: 'close_ticket',
                ticket_id: ticketId,
                user_id: <?= json_encode($user_id) ?>,
            };

            // Send closure request via WebSocket
            if (conn && conn.readyState === WebSocket.OPEN) {
                conn.send(JSON.stringify(data));
            } else {
                // WebSocket unavailable
            }
        });
    }

    // Dynamically update the unseen responses badge for a specific ticket
    function updateUnseenBadge(ticketId) {
        const ticketRow = document.querySelector(`.ticket-row[data-id='${ticketId}']`);
        if (ticketRow) {
            const badge = ticketRow.querySelector('.unseenCount');
            if (badge) {
                let unseenCount = parseInt(badge.textContent) || 0;
                unseenCount++;
                badge.textContent = unseenCount; // Increment the unseen count
            }
        }
    }

    // Attach event listener for closing tickets
    function attachCloseButtonListeners() {
        const closeButtons = document.querySelectorAll('.close-ticket-button');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const ticketId = this.dataset.ticketId;
                closeTicket(ticketId);
            });
        });
    }

    // Attach row listeners after fetching tickets
    function attachRowClickListeners() {
        const ticketRows = document.querySelectorAll('.ticket-row');
        ticketRows.forEach(row => {
            row.addEventListener('click', function(event) {
                event.preventDefault();
                ticketRows.forEach(r => r.classList.remove('active'));
                this.classList.add('active');
                localStorage.setItem('activeTicketId', this.dataset.id);
                const link = this.querySelector('a');
                if (typeof window.__ticketsLoadTicketChat === 'function') {
                    window.__ticketsLoadTicketChat(this.dataset.id, link ? link.href : '');
                }
            });
        });
        highlightActiveTicketRow(false);
    }

    // Define the `page` object globally
    const page = {

        imageAdded: false,
        allowed: ['image/jpeg', 'image/png', 'image/webp'],
        uploading: false,

        submit: function(e) {
            e.preventDefault();

            const form = e.target; // Ensure we're referencing the correct form element

            if (page.uploading || form.dataset.submitting === '1') {
                return;
            }

            let inputs = form.querySelectorAll("input,select,textarea");
            let myform = new FormData();

            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].type == 'file') {
                    if (page.imageAdded) {
                        myform.append(inputs[i].name, inputs[i].files[0]);
                    }
                } else if (inputs[i].type == 'checkbox' || inputs[i].type == 'radio') {
                    if (inputs[i].checked) {
                        myform.append(inputs[i].name, inputs[i].value);
                    }
                } else {
                    if (inputs[i].name == 'title' && inputs[i].value.trim() == '') {
                        ticketSwal('Missing Title', 'A title is required!', 'error');
                        return;
                    }
                    myform.append(inputs[i].name, inputs[i].value);
                }
            }

            page.uploading = true;
            setTicketSubmitState(form, true);
            let xhr = new XMLHttpRequest();

            xhr.addEventListener('readystatechange', function(e) {
                if (xhr.readyState === 4) {
                    page.uploading = false;
                    setTicketSubmitState(form, false);
                    page.handleResult(xhr.responseText, form, xhr.status, xhr.getResponseHeader(
                        'Content-Type'));

                    if (xhr.status === 200) {
                        // Reset image-related elements after a successful submission
                        if (page.imageAdded) {
                            const imageInput = form.querySelector(".image-response");
                            const previewImage = form.querySelector(".image-preview");

                            if (imageInput) imageInput.value = ""; // Clear the file input value
                            if (previewImage) previewImage.classList.add(
                                'd-none'); // Hide the preview image
                            //console.log(previewImage);

                            page.imageAdded = false; // Reset the flag
                        }
                    } else {
                        ticketSwal('Unable to Send Response',
                            'The response could not be submitted. Please try again.', 'error');
                        console.error("XHR error:", xhr.statusText);
                    }
                }
            });

            xhr.open('post', '', true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(myform);
        },

        displayImage: function(e) {
            if (!page.validate(e.currentTarget.files[0])) {
                ticketSwal('Invalid File Type', "Only files of this type allowed: " + page.allowed.toString()
                    .replaceAll('image/', ''), 'error');
                page.imageAdded = false;
                return false;
            }

            e.currentTarget.parentNode.querySelector("img").src = URL.createObjectURL(e.currentTarget.files[0]);
            e.currentTarget.parentNode.querySelector("img").classList.remove('d-none');;
            page.imageAdded = true;
        },

        clearResponseForm: function(form) {
            try {
                const targetForm = form || document.getElementById('ticket-form');
                if (!targetForm) return;

                const responseInput = targetForm.querySelector('.response-input');
                if (responseInput) {
                    responseInput.value = '';
                    responseInput.textContent = '';
                }

                const imageInput = targetForm.querySelector('.image-response');
                const previewImage = targetForm.querySelector('.image-preview');
                if (imageInput) imageInput.value = '';
                if (previewImage) {
                    previewImage.src = '<?= get_image('') ?>';
                    previewImage.classList.add('d-none');
                }
                page.imageAdded = false;
            } catch (e) {}
        },

        handleResult: function(result, form, status, contentType) {
            try {
                let obj = parseTicketJsonResponse(result);

                if (typeof obj === 'object' && obj.success) {
                    const data = obj.data;
                    page.clearResponseForm(form);

                    // Display the message instantly for the sender
                    if (typeof window.__ticketsAppendResponse === 'function') {
                        window.__ticketsAppendResponse(data);
                        if (typeof window.__ticketsSetLastResponseId === 'function')
                            window.__ticketsSetLastResponseId(data.id);
                    } else if (conn && typeof conn.onmessage === 'function') {
                        conn.onmessage({
                            data: JSON.stringify(data)
                        });
                    }

                    // Send data through WebSocket
                    if (conn && conn.readyState === WebSocket.OPEN) {
                        conn.send(JSON.stringify(data));
                    } else {
                        // WebSocket unavailable
                    }
                } else if (obj.errors && Object.keys(obj.errors).length > 0) {
                    const messages = [];
                    for (const key in obj.errors) {
                        messages.push(obj.errors[key]);
                    }
                    const message = messages.join("\n") || obj.message ||
                        'Please check the response and try again.';
                    ticketSwal('Unable to Send Response', message, 'error');
                    console.error('Ticket response request failed:', {
                        status: status,
                        message: obj.message,
                        errors: obj.errors,
                        debug_message: obj.debug_message,
                        reference: obj.reference
                    });
                } else {
                    const message = obj.message || 'Invalid response from the server.';
                    ticketSwal('Unable to Send Response', message, 'error');
                    console.error('Ticket response request failed:', {
                        status: status,
                        message: obj.message,
                        debug_message: obj.debug_message,
                        reference: obj.reference,
                        response: obj
                    });
                }
            } catch (error) {
                ticketSwal('Unable to Send Response', 'The server response could not be read.', 'error');
                console.error('Error handling ticket response:', {
                    error: error,
                    status: status,
                    contentType: contentType,
                    response: result
                });
            }
        },

        validate: function(file) {

            if (page.allowed.includes(file.type))
                return true;

            return false;
        }
    };

    // Attach the submit event listener when the page loads
    window.addEventListener('load', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', page.submit);
        }

        const leftPanel = document.querySelector('.search-tickets');
        const chatArea = document.querySelector('.chat-area');
        if (leftPanel && chatArea && window.innerWidth <= 900) {
            leftPanel.classList.add('hidden');
            chatArea.classList.add('full-width');
        }

        highlightActiveTicketRow(true);
    });

    function toggleLeftPanel() {
        const leftPanel = document.querySelector('.search-tickets');
        const chatArea = document.querySelector('.chat-area');
        const isSmallScreen = window.innerWidth <= 900;

        if (isSmallScreen) {
            const isHidden = leftPanel.classList.toggle('hidden');
            chatArea.classList.toggle('full-width', isHidden);
        } else {
            // No action required for larger screens
            console.log('Toggle is only available on smaller screens.');
        }
    }

    // Ensure toggle works only for small screens
    window.addEventListener('resize', () => {
        const leftPanel = document.querySelector('.search-tickets');
        const chatArea = document.querySelector('.chat-area');
        if (!leftPanel || !chatArea) return;
        if (window.innerWidth > 900) {
            leftPanel.classList.remove('hidden');
            chatArea.classList.remove('full-width');
        } else {
            leftPanel.classList.add('hidden');
            chatArea.classList.add('full-width');
        }
    });

    // Function to fetch and display tickets
    function fetchTickets(searchInput = "") {
        const myform = new FormData();

        // Include the search query if provided
        if (searchInput.trim() !== "") {
            myform.append('find', searchInput);
        }

        page.uploading = true;
        let xhr = new XMLHttpRequest();

        xhr.addEventListener('readystatechange', function() {
            if (xhr.readyState === 4) {
                page.uploading = false;
                try {
                    const data = parseTicketJsonResponse(xhr.responseText);
                    if (xhr.status < 200 || xhr.status >= 300 || data.success === false) {
                        console.error('Ticket list request failed:', {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            message: data.message,
                            debug_message: data.debug_message,
                            reference: data.reference,
                            response: data
                        });
                        return;
                    }
                    const ticketTableBody = document.getElementById('ticket-list');
                    ticketTableBody.innerHTML = ""; // Clear the ticket list

                    if (data.success && data.data.length > 0) {
                        // Populate tickets if available
                        data.data.forEach((ticket) => {
                            const isActive = String(ticket.id) === getActiveTicketId();
                            const row = `
                                    <tr>
                                        <td class='wrap ticket-row ${isActive ? 'active' : ''}' data-id="${ticket.id}">
                                            <a class='dropdown-item' href='<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/view/${ticket.id}'>
                                                <i class='fa fa-ticket fw-bolder'></i> <strong>${ticket.id} </strong>
                                                <span class='badge fw-bolder rounded-pill unseenCount' data-notification-urgency="serious" style='font-weight: bolder;'>${ticket.unseen_responses}</span><br>
                                                <span><img src='${ticket.user_image}' style='width: 20px; height:20px; border-radius: 50px;'></span>             
                                                <span class='badge text-muted'>${ticket.user_full_name_short}</span>
                                            </a>
                                        </td>
                                    </tr>`;
                            ticketTableBody.insertAdjacentHTML('beforeend', row);
                            // Apply configured pill styles to the newly inserted badge
                            try {
                                const lastRow = ticketTableBody.lastElementChild;
                                if (lastRow) {
                                    const badge = lastRow.querySelector('.unseenCount');
                                    if (badge && window && window.tormorApplyPillStyle) {
                                        try {
                                            window.tormorApplyPillStyle(badge);
                                        } catch (e) {}
                                    }
                                }
                            } catch (e) {}
                        });

                        attachRowClickListeners();
                        highlightActiveTicketRow(true);
                    } else if (searchInput.trim() === "") {
                        // Initial load: no tickets found
                        ticketTableBody.innerHTML =
                            "<tr><td class='text-center'>No tickets assigned or created by you.</td></tr>";
                    } else {
                        // Search-specific: no results found
                        ticketTableBody.innerHTML =
                            "<tr><td class='text-center'>No tickets match your search.</td></tr>";
                    }
                } catch (error) {
                    console.error('Error parsing ticket list response:', {
                        error: error,
                        status: xhr.status,
                        statusText: xhr.statusText,
                        contentType: xhr.getResponseHeader('Content-Type'),
                        response: xhr.responseText
                    });
                }
            }
        });

        xhr.open("POST", "", true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(myform);
    }

    // Attach click event listeners to ticket rows
    function attachRowClickListeners() {
        const ticketRows = document.querySelectorAll('.ticket-row');
        ticketRows.forEach(row => {
            row.addEventListener('click', function(event) {
                event.preventDefault();
                // Remove active class from all rows
                ticketRows.forEach(r => r.classList.remove('active'));
                // Add active class to the clicked row
                this.classList.add('active');

                // Store the active ticket ID in localStorage
                localStorage.setItem('activeTicketId', this.dataset.id);

                // Allow navigation to the ticket view page
                const link = this.querySelector('a');
                if (typeof window.__ticketsLoadTicketChat === 'function') {
                    window.__ticketsLoadTicketChat(this.dataset.id, link ? link.href : '');
                }
            });
        });

        highlightActiveTicketRow(false);
    }

    // Search function
    function searchTickets() {
        const searchInput = document.getElementById('search-tickets').value.trim();
        fetchTickets(searchInput); // Call fetchTickets with the search input
    }

    // Attach the search function to the input field
    document.getElementById('search-tickets').addEventListener('input', searchTickets);

    // Populate the tickets on page load
    window.addEventListener('load', function() {
        fetchTickets(); // Call fetchTickets with no arguments to load all tickets
    });

    function openFullImage(imageSrc) {
        let modal = document.getElementById('image-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'image-modal';
            modal.className = 'ticket-image-modal';
            modal.innerHTML =
                '<img id="full-image" alt="Full-size ticket attachment"><button type="button" class="ticket-image-modal-close" aria-label="Close image" onclick="closeImageModal()"><i class="fas fa-times" aria-hidden="true"></i></button>';
            modal.addEventListener('click', function(event) {
                if (event.target === modal) closeImageModal();
            });
            document.body.appendChild(modal);
        }
        const fullImage = modal.querySelector('#full-image');
        if (!fullImage) return;
        fullImage.src = imageSrc;
        modal.style.display = 'flex';
    }

    function closeImageModal() {
        const modal = document.getElementById('image-modal');
        if (modal) modal.style.display = 'none';
    }

    document.addEventListener('click', function(event) {
        const image = event.target.closest('.ticket-detail-page .response-attachment');
        if (image) openFullImage(image.currentSrc || image.src);
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') closeImageModal();
    });
</script>
<?= tickets_notification_script((int)($user_id ?? 0), $admin_route, $plugin_route) ?>