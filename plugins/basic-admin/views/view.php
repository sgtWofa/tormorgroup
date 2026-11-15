<?php
  $featherJsVersion = @filemtime(__DIR__ . '/../../../../assets/js/feather.min.js') ?: '1';
  $featherJsUrl = app_http_path('assets/js/feather.min.js') . '?v=' . rawurlencode((string)$featherJsVersion);
  $__mobileDocumentScrollPage = in_array((string)(URL(1) ?? ''), ['agencybanking', 'airtimebundle', 'attendance', 'cashpickup', 'cfa', 'ezwich', 'logs', 'momoremit', 'payment_services', 'payments', 'pettycash', 'sims', 'tickets', 'users'], true);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto" class="<?=$__mobileDocumentScrollPage ? 'mobile-document-scroll-page' : ''?>">
<head>
  <script src="<?=plugin_http_path('assets/js/color-modes.js')?>"></script>
  <script src="<?=plugin_http_path('assets/js/export-progress.js')?>?v=<?=rawurlencode((string)(@filemtime(__DIR__ . '/../assets/js/export-progress.js') ?: '1'))?>" defer></script>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="">
  <title>Admin | <?=APP_NAME?></title>

  <!-- Favicons -->
  <link href="<?=APP_LOGO2?>" rel="icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@docsearch/css@3">
  <link href="<?=plugin_http_path('assets/css/bootstrap.min.css')?>" rel="stylesheet">
  <link href="<?=plugin_http_path('assets/css/all.min.css')?>" rel="stylesheet">
  <link href="<?=plugin_http_path('assets/css/custom.css')?>" rel="stylesheet" />
  <script src="<?=esc($featherJsUrl)?>" defer></script>
  <link href="<?=plugin_http_path('assets/css/virtual-select.min.css')?>" rel="stylesheet" rel="stylesheet">
  <link href="<?=plugin_http_path('assets/css/jquery.dataTables.min.css')?>">
  <script src="<?=plugin_http_path('assets/js/jquery-3.6.0.min.js')?>"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>

    .bd-placeholder-img {
      font-size: 1.125rem;
      text-anchor: middle;
      -webkit-user-select: none;
      -moz-user-select: none;
      user-select: none;
    }

    a{
      text-decoration: none;
    }

    .app-page-skeleton {
      position: fixed;
      inset: 0;
      z-index: 2147483000;
      display: none;
      grid-template-columns: 250px minmax(0, 1fr);
      overflow: hidden;
      background: #f8fafc;
      color: #0f172a;
    }

    html.app-page-loading .app-page-skeleton {
      display: grid;
    }

    .app-page-skeleton__sidebar {
      padding: 1.25rem 1rem;
      background: #fff;
      border-right: 1px solid #e2e8f0;
    }

    .app-page-skeleton__main {
      min-width: 0;
      padding: 1.5rem clamp(1rem, 3vw, 2.5rem);
    }

    .app-page-skeleton__topbar,
    .app-page-skeleton__heading,
    .app-page-skeleton__cards,
    .app-page-skeleton__rows {
      display: flex;
      gap: 1rem;
    }

    .app-page-skeleton__topbar {
      justify-content: flex-end;
      margin-bottom: 2rem;
    }

    .app-page-skeleton__heading {
      flex-direction: column;
      gap: 0.65rem;
      margin-bottom: 1.5rem;
    }

    .app-page-skeleton__cards {
      margin-bottom: 1.5rem;
    }

    .app-page-skeleton__card,
    .app-page-skeleton__panel {
      padding: 1rem;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
    }

    .app-page-skeleton__card {
      flex: 1;
      min-width: 0;
      height: 6.5rem;
    }

    .app-page-skeleton__panel {
      min-height: 15rem;
    }

    .app-page-skeleton__nav {
      display: grid;
      gap: 1.1rem;
      margin-top: 2rem;
    }

    .app-page-skeleton__shimmer {
      display: block;
      height: 0.8rem;
      border-radius: 999px;
      background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
      background-size: 200% 100%;
      animation: app-skeleton-shimmer 1.35s ease-in-out infinite;
    }

    .app-page-skeleton__nav .app-page-skeleton__shimmer {
      width: 85%;
    }

    .app-page-skeleton__shimmer--brand { width: 70%; height: 1.1rem; }
    .app-page-skeleton__shimmer--short { width: 7rem; }
    .app-page-skeleton__shimmer--title { width: min(22rem, 70%); height: 1.5rem; }
    .app-page-skeleton__shimmer--subtitle { width: min(30rem, 85%); }
    .app-page-skeleton__shimmer--line { width: 100%; margin: 1.2rem 0; }
    .app-page-skeleton__shimmer--line:nth-child(3n) { width: 76%; }

    @keyframes app-skeleton-shimmer {
      to { background-position: -200% 0; }
    }

    html[data-bs-theme="dark"] .app-page-skeleton {
      background: #111827;
      color: #e5e7eb;
    }

    html[data-bs-theme="dark"] .app-page-skeleton__sidebar,
    html[data-bs-theme="dark"] .app-page-skeleton__card,
    html[data-bs-theme="dark"] .app-page-skeleton__panel {
      background: #1f2937;
      border-color: #374151;
    }

    html[data-bs-theme="dark"] .app-page-skeleton__shimmer {
      background: linear-gradient(90deg, #374151 25%, #4b5563 50%, #374151 75%);
      background-size: 200% 100%;
    }

    @media (max-width: 767.98px) {
      .app-page-skeleton {
        grid-template-columns: 1fr;
      }
      .app-page-skeleton__sidebar {
        display: none;
      }
      .app-page-skeleton__main {
        padding: 1rem;
      }
      .app-page-skeleton__cards {
        flex-wrap: wrap;
      }
      .app-page-skeleton__card {
        flex-basis: calc(50% - 0.5rem);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .app-page-skeleton__shimmer {
        animation: none;
      }
    }

    .transaction-item {
        border-left: 4px solid #ffc107;
        padding: 15px;
        margin-bottom: 12px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .transaction-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .status-draft {
        background: #f3e5f5;
        color: #7b1fa2 !important;
    }

    .status-pending {
        background: #fff3e0;
        color: #e65100 !important;
    }

    .status-opening {
        background: #e8f5e9;
        color: #2e7d32 !important;
    }

    .transaction-details {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .cash-balance {
        color: var(--bs-modal-color, var(--bs-body-color, #212529)) !important;
    }

    .transaction-amount {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    html,
    body{
      height: 100%;
      overflow-x: hidden;
      overflow-y: hidden;
    }

    @media (max-width: 767px) {

      /* Uniform dark admin navbar on small & medium screens */
      header.navbar{
      flex-wrap: wrap;
      width: 100%;
      background-color: #020617 !important;
      color: #f9fafb !important;
      border-bottom: 1px solid #0f172a;
      }

      header.navbar .navbar-brand{ order: 0; }
      header.navbar .navbar-nav{ order: 1; }
      header.navbar .navbar-search{ order: 2; }
      header.navbar #headerQuickActions{ order: 3; }

      header.navbar #headerQuickActions{
      width: 100%;
      padding: 0.35rem 0.75rem;
      justify-content: flex-end;
      margin-right: 0 !important;
      gap: 0.4rem;
      }
      header.navbar .navbar-brand,
      header.navbar .navbar-nav,
      header.navbar .navbar-search{
        background-color: #020617 !important;
        color: #f9fafb !important;
      }

      header.navbar .navbar-search .form-control{
        background-color: #020617 !important;
        color: #f9fafb !important;
        border-color: #1f2937 !important;
      }

      header.navbar .navbar-nav .nav-link,
      header.navbar .navbar-nav button.nav-link,
      header.navbar .navbar-nav .nav-link.btn {
        -webkit-appearance: none;
        appearance: none;
        background-color: transparent !important;
        background-image: none !important;
        border: none !important;
        box-shadow: none !important;
        color: #f9fafb !important;
      }
      header.navbar .navbar-nav .nav-link svg,
      header.navbar .navbar-nav .nav-link i {
        color: #f9fafb !important;
        stroke: #f9fafb !important;
        fill: #f9fafb !important;
      }

      main{
        padding-bottom: calc(6rem + env(safe-area-inset-bottom));
      }
    }

    @media (max-width: 767px) {
      .minimize-toggle{
        display: none;
      }

      .sidebar.minimized,
      .sidebar.minimized .offcanvas-body,
      .sidebar.minimized .subnav,
      .sidebar.minimized + main,
      .sidebar-layout-row > .sidebar.minimized + main {
        width: auto !important;
        max-width: 100% !important;
        margin: 0 !important;
        position: static !important;
        left: auto !important;
        top: auto !important;
      }

      .sidebar.minimized .nav-link span,
      .sidebar.minimized .nav-link .badge,
      .sidebar.minimized .nav-link .fa-caret-down {
        display: inline !important;
      }

      .offcanvas.offcanvas-start,
      #sidebarMenu.offcanvas-start {
        left: 0 !important;
        right: auto !important;
        /* Use viewport-friendly width but cap to a reasonable max so sidebar
           does not cover the whole screen on small devices */
        width: 85vw !important;
        max-width: 420px !important;
      }

      /* Stronger override for the actual sidebar element to prevent any
         centering transforms from other stylesheets (high specificity). */
      #sidebarMenu.offcanvas-start {
        position: fixed !important;
        top: 56px !important; /* sit under the header */
        left: 0 !important;
        right: auto !important;
        width: 85vw !important;
        max-width: 420px !important;
        margin: 0 !important;
        transform: translateX(-105%) !important; /* hidden by default */
      }

      /* Keep hidden offcanvas off-screen to the left until shown */
      .offcanvas.offcanvas-start:not(.show) {
        transform: translateX(-105%) !important;
      }

      #sidebarMenu.offcanvas-start.show,
      .offcanvas.offcanvas-start.show {
        transform: translateX(0) !important;
        left: 0 !important;
        right: auto !important;
      }

      .nav-link .badge:empty,
      .nav-link span.d-none {
        display: none !important;
      }
    }

    .header-quick-actions{
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .header-quick-actions .cashdrawer-toggle{
      margin-right: 0 !important;
    }

    .header-quick-actions .bd-mode-toggle .btn,
    .header-quick-actions .cashdrawer-toggle .btn,
    .header-quick-actions .ai-assist-toggle button{
      padding: 0.25rem 0.5rem !important;
      font-size: 0.85rem;
      line-height: 1.2;
    }

    .header-quick-actions .bd-mode-toggle .btn{
      padding-top: 0.25rem !important;
      padding-bottom: 0.25rem !important;
    }

    .header-quick-actions .ai-assist-toggle button{
      border-radius: 0.5rem;
      box-shadow: none;
      font-weight: 600;
    }

    @media (max-width: 1024px) {
      body{
        font-size: 14px !important;
      }

      .form-control,
      .form-select,
      .btn{
        font-size: 14px !important;
      }

      .form-label,
      label,
      small,
      .text-muted{
        font-size: 12px !important;
      }

      h1{
        font-size: 18px !important;
      }

      h2{
        font-size: 16px !important;
      }

      h3{
        font-size: 15px !important;
      }
    }

    @media (min-width: 768px) {
      .bd-placeholder-img-lg {
        font-size: 3.5rem;
      }

      header.navbar{
        opacity: 1;
      }
    }

    .b-example-divider {
      width: 100%;
      height: 3rem;
      background-color: rgba(0, 0, 0, .1);
      border: solid rgba(0, 0, 0, .15);
      border-width: 1px 0;
      box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
    }

    .b-example-vr {
      flex-shrink: 0;
      width: 1.5rem;
      height: 100vh;
    }

    .bi, .fa {
      vertical-align: -.125em;
      fill: currentColor;
    }

    .nav-scroller {      position: relative;
      z-index: 2;
      height: 2.75rem;
      overflow-y: hidden;
    }

    .nav-scroller .nav {
      display: flex;
      flex-wrap: nowrap;
      padding-bottom: 1rem;
      margin-top: -1px;
      overflow-x: auto;
      text-align: center;
      white-space: nowrap;
      -webkit-overflow-scrolling: touch;
    }

    .btn-bd-primary {
      --bd-violet-bg: #712cf9;
      --bd-violet-rgb: 112.520718, 44.062154, 249.437846;

      --bs-btn-font-weight: 600;
      --bs-btn-color: var(--bs-white);
      --bs-btn-bg: var(--bd-violet-bg);
      --bs-btn-border-color: var(--bd-violet-bg);
      --bs-btn-hover-color: var(--bs-white);
      --bs-btn-hover-bg: #6528e0;
      --bs-btn-hover-border-color: #6528e0;
      --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
      --bs-btn-active-color: var(--bs-btn-hover-color);
      --bs-btn-active-bg: rgb(255, 192, 6) !important;
      --bs-btn-active-border-color: #5a23c8;
    }

    .bd-mode-toggle {
      z-index: 1500;
    }

    .cashdrawer-toggle{
      margin-right: 5rem;
    }

    .nav-item {
      background-color: transparent;
      transition: 0.3s ease-in;
    }

    .nav-item:hover {
      background-color: rgba(173, 216, 230, 0.3) !important;
      color: darkgray !important;
      transition: 0.3s ease-in;
    }

    /* Scoped active state: avoid unscoped `.active` affecting non-navigation content */
    header .active,
    .navbar .active,
    .nav .active,
    .sidebar .nav-link.active,
    .sidebar .nav-item > .active {
      background-color: rgba(255, 192, 6, 0.6) !important;
      color: darkblue !important;
    }

    /* Sidebar styles - Modern & Scrollable */
    .sidebar {
      transition: width 0.3s ease;
      display: flex;
      flex-direction: column;
      max-height: calc(100vh - 56px); /* match area below sticky header */
      overflow: hidden;
    }

    .sidebar .offcanvas-md {
      /* Let offcanvas fill its container without locking viewport height */
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
      flex: 1;
      min-height: 0;
    }

    .sidebar .offcanvas-header {
      flex-shrink: 0;
      padding: 0.5rem 0.75rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar .offcanvas-body {
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
      flex: 1;
      min-height: 0;
    }

    .sidebar .offcanvas-body::-webkit-scrollbar {
      width: 6px;
    }

    .sidebar .offcanvas-body::-webkit-scrollbar-track {
      background: transparent;
    }

    .sidebar .offcanvas-body::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 3px;
    }

    .sidebar .offcanvas-body::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }

    .sidebar i .nav-link:hover{
      color:white !important;
    }

    .sidebar.minimized {
      width: 70px !important;
      min-width: 70px !important;
      max-width: 70px !important;
      padding: 0 !important;
      transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
      overflow: visible;
      flex: 0 0 70px !important;
    }

    .sidebar.minimized .offcanvas-body {
      width: 70px !important;
      min-width: 70px !important;
      max-width: 70px !important;
      overflow-x: visible;
      overflow-y: auto;
    }

    .sidebar.minimized .nav-link {
      justify-content: center;
      padding: 0.75rem 0.25rem;
      text-align: center;
      position: relative;
    }

    .sidebar.minimized .nav-link i {
      margin: 0;
      font-size: 1.2rem;
    }

    .sidebar.minimized .nav-link > .badge {
      display: inline-flex !important;
      position: absolute;
      top: 0.2rem;
      right: 0.25rem;
      min-width: 1.1rem;
      height: 1.1rem;
      padding: 0.15rem 0.3rem;
      font-size: 0.62rem;
      line-height: 1;
      border-radius: 999px;
      z-index: 2;
      align-items: center;
      justify-content: center;
    }

    .sidebar.minimized .nav-link span,
    .sidebar.minimized .nav-link .fa-caret-down {
      display: none !important;
    }

    .sidebar.minimized .nav-link .badge:empty {
      display: none !important;
    }

    .sidebar.minimized .subnav .nav-link [data-notification-badge]:empty,
    .sidebar.minimized .subnav .nav-link [data-notification-badge].d-none {
      display: none !important;
    }

    .sidebar.minimized .subnav .nav-link i {
      margin-right: 12px;
    }

    .sidebar.minimized .subnav {
      position: fixed;
      left: 70px;
      width: 280px;
      min-width: 280px;
      max-width: 320px;
      background: var(--bs-body-bg, #ffffff);
      color: var(--bs-body-color, #111111);
      border-radius: 0.75rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
      padding: 0.5rem 0;
      z-index: 1100;
      display: none;
      overflow: visible;
      border: 1px solid rgba(0, 0, 0, 0.08);
    }

    .sidebar.minimized .subnav.show,
    .sidebar.minimized .subnav.collapsing {
      display: block;
    }

    .sidebar.minimized .subnav .nav-link {
      justify-content: flex-start;
      padding: 0.5rem 1rem;
      text-align: left;
    }

    .sidebar.minimized .subnav .nav-link span {
      display: inline !important;
    }

    .sidebar.minimized .subnav .nav-link i {
      flex: 0 0 auto;
    }

    .sidebar.minimized .subnav .nav-link span {
      display: inline-block !important;
      white-space: nowrap;
    }

    .sidebar.minimized + main,
    .sidebar-layout-row > .sidebar.minimized + main {
      width: calc(100% - 70px) !important;
      max-width: none !important;
      margin-left: 0 !important;
      flex: 1 1 auto !important;
    }

    @media (max-width: 767px) {
      .sidebar.minimized,
      .sidebar.minimized .offcanvas-body,
      .sidebar.minimized .subnav,
      .sidebar.minimized + main,
      .sidebar-layout-row > .sidebar.minimized + main {
        width: auto !important;
        max-width: 100% !important;
        margin: 0 !important;
        position: static !important;
        left: auto !important;
        top: auto !important;
      }

      .sidebar.minimized .nav-link span,
      .sidebar.minimized .nav-link .badge,
      .sidebar.minimized .nav-link .fa-caret-down {
        display: inline !important;
      }
    }

    main{
      height: calc(100vh - 56px); /* scrollable content area under header */
      overflow-y: auto;
      padding-bottom: calc(6rem + env(safe-area-inset-bottom));
    }

    @media (max-width: 767px) {
      html.mobile-document-scroll-page,
      body.mobile-document-scroll-page {
        height: auto;
        min-height: 100%;
        overflow-x: hidden;
        overflow-y: auto;
      }

      body.mobile-document-scroll-page main#mainBody {
        height: auto;
        min-height: calc(100dvh - 56px);
        overflow: visible;
      }
    }

    main.maximized{
      width: 85%;
    }

    .sidebar .nav-link {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      padding: 0.4rem 0.75rem;
      border-radius: 0.5rem;
      margin: 0.1rem 0.35rem;
      transition: all 0.2s ease;
      color: var(--bs-body-color);
      font-size: 0.95rem;
    }

    .sidebar .nav-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
      transform: translateX(4px);
    }

    .sidebar.minimized .nav-link:hover,
    .sidebar.minimized .subnav .nav-link:hover {
      transform: none;
      background-color: rgba(255, 255, 255, 0.08);
    }

    .sidebar .nav-link.active {
      background-color: rgba(255, 192, 6, 0.2);
      border-left: 3px solid #ffc006;
      padding-left: calc(0.75rem - 3px);
    }

    .sidebar .nav-item {
      margin: 0.05rem 0;
    }

    .sidebar .nav-link i {
      margin-right: 8px;
      transition: all 0.2s ease;
    }

    .sidebar .nav-link:hover i {
      color: #ffc006;
    }

    .sidebar .subnav {
      padding-left: 0.75rem;
      margin: 0.05rem 0;
    }

    .sidebar .subnav .nav-link {
      padding: 0.3rem 0.6rem;
      font-size: 0.85rem;
    }

    .sidebar.minimized .nav-link{
      text-align: right;
      transition: 0.2s ease;
    }

    .sidebar.minimized .nav-link i {
      font-size: 15px;
    }

    .sidebar.minimized .nav-link:hover {
      transform: none;
      margin-right: 0;
      color: inherit;
      background-color: rgba(255, 255, 255, 0.08);
    }

    .sidebar .badge {
      font-size: 0.7rem;
      padding: 0.35rem 0.6rem;
    }

    .nav-link .badge:empty {
      display: none !important;
    }

    .minimize-toggle {
      cursor: pointer;
      padding-left: 1rem;
    }

    header.navbar .navbar-brand {
      min-width: 0;
      transition: width 0.2s ease, max-width 0.2s ease, padding 0.2s ease;
    }

    header.navbar .navbar-brand.is-logo-only {
      width: 72px !important;
      max-width: 72px !important;
      flex: 0 0 72px !important;
      justify-content: center !important;
      padding-left: 0.5rem !important;
      padding-right: 0.5rem !important;
    }

    header.navbar .navbar-brand.is-logo-only .navbar-brand-title {
      gap: 0 !important;
    }

    header.navbar .navbar-brand.is-logo-only .app-name {
      display: none !important;
    }

    /* Target the badge inside the active ticket row */
    .nav-link.active .rounded-pill {
        background-color: black !important;
        color: white !important;
    }

   #multipleSelect{
        max-width: 100%;
        width: 100%;
      }

      .vscomp-toggle-button{
        padding: 10px 30px 10px 10px;
        border-radius: 5px;
      }

      .nowrap{
        white-space: nowrap;
    }

    /* ðŸ”¹ Panel (Hidden Initially, Slides in from Bottom) */
    .drawer-panel {
        position: fixed;
        left: 50%;
        bottom: -100%;
        transform: translate(-50%, 0);
        -webkit-transform: translate(-50%, 0);
        width: 80%;
        max-width: 900px;
        box-sizing: border-box;
        height: calc(100vh - 120px);
        max-height: calc(100vh - 120px);
        background-color: var(--bs-body-bg) !important;
        color: var(--bs-body-color) !important;
        box-shadow: 0px -2px 10px rgba(0, 0, 0, 0.3);
        transition: bottom 0.3s ease-in-out;
        overflow-y: auto;
        padding: 20px;
        border-radius: 12px 12px 0 0;
        z-index: 1000;
    }

    /* âœ… Safari-compatible: when panel is shown (bottom: 12px), add top spacing */
    .drawer-panel[style*="bottom: 12px"] {
        top: 70px;
        bottom: 12px !important;
        height: auto;
        max-height: none;
    }

    #resultTable {
        height: 70%;
        overflow-y: auto;
        z-index: 1000;
    }

    /* ðŸ”¹ Panel Header */
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #ddd;
        padding-bottom: 10px;
    }

    /* ðŸ”¹ Panel Body */
    .panel-body {
        max-height: 85%;
        overflow-y: auto;
        overflow-x: hidden;
        min-width: 0;
        color: var(--bs-body-color) !important;
    }
    #resultTable{
        max-height: 80%;
        overflow-y: auto;
        color: inherit;
    }

    /* ðŸ”¹ Overlay Effect (Optional) */
    .panel-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: none;
        z-index: 999;
    }

    /* âœ… Let panel content follow theme colors, but keep badges readable */
    .drawer-panel, 
    .drawer-panel * {
        color: inherit;
    }

    /* âœ… Reduce scrollbar width & gutter */
    .drawer-panel::-webkit-scrollbar, 
    .panel-body::-webkit-scrollbar,
    #resultTable::-webkit-scrollbar {
        width: 4px; /* Minimized scrollbar width */

    }

    .drawer-panel::-webkit-scrollbar-track, 
    .panel-body::-webkit-scrollbar-track,
    #resultTable::-webkit-scrollbar-track, {
        background: transparent; /* Remove unnecessary gutter space */
    }

    .drawer-panel::-webkit-scrollbar-thumb, 
    .panel-body::-webkit-scrollbar-thumb,
    #resultTable::-webkit-scrollbar-thumb, {
        background: rgba(0, 0, 0, 0.3); /* Subtle scrollbar */
        border-radius: 4px;
    }

    .drawer-panel::-webkit-scrollbar-thumb:hover, 
    .panel-body::-webkit-scrollbar-thumb:hover,
    #resultTable::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.5); /* Darker on hover */
    }

    #drawerContent .cashdrawer-channel-table tbody tr.cashdrawer-channel-row {
        border-left: 4px solid transparent !important;
        transition: background-color 0.16s ease, box-shadow 0.16s ease;
    }

    #drawerContent .cashdrawer-channel-table tbody tr.cashdrawer-channel-row--ghs {
        border-left-color: #159a63 !important;
        background: linear-gradient(90deg, rgba(21,154,99,.12), rgba(21,154,99,.025)) !important;
    }

    #drawerContent .cashdrawer-channel-table tbody tr.cashdrawer-channel-row--cfa {
        border-left-color: #7854c8 !important;
        background: linear-gradient(90deg, rgba(120,84,200,.14), rgba(120,84,200,.03)) !important;
    }

    #drawerContent .cashdrawer-channel-table tbody tr.cashdrawer-channel-row--ecash {
        border-left-color: #2874c8 !important;
        background: linear-gradient(90deg, rgba(40,116,200,.12), rgba(40,116,200,.025)) !important;
    }

    #drawerContent .cashdrawer-channel-table tbody tr.cashdrawer-channel-row--shared {
        border-left: 5px solid #d6a313 !important;
        background: linear-gradient(90deg, rgba(246,195,67,.25), rgba(246,195,67,.07)) !important;
        box-shadow: inset 0 0 0 1px rgba(214,163,19,.2);
    }

    #drawerContent .cashdrawer-channel-table tbody tr.cashdrawer-channel-row:hover {
        box-shadow: inset 0 0 0 1px rgba(33,37,41,.12);
    }

    #drawerContent .cashdrawer-channel-identity {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    #drawerContent .cashdrawer-channel-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.035em;
        line-height: 1.25;
        white-space: nowrap;
    }

    #drawerContent .cashdrawer-channel-badge--ghs {
        background: #d7f4e5;
        color: #08633c;
    }

    #drawerContent .cashdrawer-channel-badge--cfa {
        background: #ede6ff;
        color: #56339f;
    }

    #drawerContent .cashdrawer-channel-badge--ecash {
        background: #dcecff;
        color: #15539b;
    }

    #drawerContent .cashdrawer-channel-badge--shared {
        background: #ffe9a8;
        color: #684800;
        border: 1px solid rgba(164,117,0,.32);
        justify-content: center;
        width: 1.9rem;
        height: 1.9rem;
        padding: 0;
        font-size: 0.9rem;
    }

    #drawerContent .cashdrawer-channel-label {
        color: var(--bs-body-color);
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    #drawerContent .cashdrawer-summary-header {
        position: relative;
        overflow: hidden;
        margin: 0.2rem 0 0.75rem;
        padding: 0.85rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 0.9rem;
        background: var(--bs-body-bg);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
        color: var(--bs-body-color);
    }

    #drawerContent .cashdrawer-summary-topline,
    #drawerContent .cashdrawer-summary-brand,
    #drawerContent .cashdrawer-summary-created {
        display: flex;
        align-items: center;
    }

    #drawerContent .cashdrawer-summary-topline {
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    #drawerContent .cashdrawer-summary-brand {
        min-width: 0;
        gap: 0.65rem;
    }

    #drawerContent .cashdrawer-summary-logo {
        display: inline-flex;
        flex: 0 0 2rem;
        width: 2rem;
        height: 2rem;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid var(--bs-border-color);
        border-radius: 0.65rem;
        background: #fff;
    }

    #drawerContent .cashdrawer-summary-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    #drawerContent .cashdrawer-summary-app-name {
        color: var(--bs-secondary-color);
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }

    #drawerContent .cashdrawer-summary-created {
        gap: 0.35rem;
        padding: 0.3rem 0.55rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 999px;
        background: var(--bs-secondary-bg);
        color: var(--bs-body-color);
        font-size: 0.72rem;
        white-space: nowrap;
    }

    #drawerContent .cashdrawer-summary-created i {
        color: var(--bs-warning-text-emphasis, #997404);
    }

    #drawerContent .cashdrawer-summary-title {
        margin: 0.65rem 0 0.7rem;
    }

    #drawerContent .cashdrawer-summary-kicker {
        color: var(--bs-primary);
        font-size: 0.67rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    #drawerContent .cashdrawer-summary-title h3 {
        margin: 0.18rem 0 0;
        color: var(--bs-emphasis-color, var(--bs-body-color));
        font-size: clamp(1.05rem, 2.6vw, 1.35rem);
        font-weight: 800;
        letter-spacing: -0.02em;
        overflow-wrap: anywhere;
    }

    #drawerContent .cashdrawer-summary-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(132px, 1fr));
        gap: 0.45rem;
    }

    #drawerContent .cashdrawer-summary-metric {
        display: flex;
        min-width: 0;
        min-height: 3.5rem;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.6rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 0.7rem;
        background: var(--bs-secondary-bg);
    }

    #drawerContent .cashdrawer-summary-metric-icon {
        display: inline-flex;
        flex: 0 0 1.7rem;
        width: 1.7rem;
        height: 1.7rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.55rem;
        background: var(--bs-tertiary-bg, var(--bs-body-bg));
        color: var(--bs-primary);
    }

    #drawerContent .cashdrawer-summary-metric > div {
        min-width: 0;
    }

    #drawerContent .cashdrawer-summary-metric-label {
        display: block;
        margin-bottom: 0.12rem;
        color: var(--bs-secondary-color);
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.045em;
        text-transform: uppercase;
    }

    #drawerContent .cashdrawer-summary-metric strong {
        display: block;
        color: var(--bs-emphasis-color, var(--bs-body-color));
        font-size: clamp(0.86rem, 2.4vw, 1rem);
        font-weight: 800;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    #drawerContent .cashdrawer-summary-metric--ghs .cashdrawer-summary-metric-icon {
        background: var(--bs-success-bg-subtle, rgba(25, 135, 84, 0.12));
        color: var(--bs-success-text-emphasis, #146c43);
    }

    #drawerContent .cashdrawer-summary-metric--count .cashdrawer-summary-metric-icon,
    #drawerContent .cashdrawer-summary-metric--cfa-count .cashdrawer-summary-metric-icon {
        background: var(--bs-info-bg-subtle, rgba(13, 202, 240, 0.12));
        color: var(--bs-info-text-emphasis, #087990);
    }

    #drawerContent .cashdrawer-summary-metric--cfa .cashdrawer-summary-metric-icon {
        background: var(--bs-primary-bg-subtle, rgba(13, 110, 253, 0.12));
        color: var(--bs-primary-text-emphasis, #084298);
    }

    #drawerContent .cashdrawer-summary-metric--buy .cashdrawer-summary-metric-icon {
        background: var(--bs-warning-bg-subtle, rgba(255, 193, 7, 0.16));
        color: var(--bs-warning-text-emphasis, #997404);
    }

    #drawerContent .cashdrawer-summary-metric--sell .cashdrawer-summary-metric-icon {
        background: var(--bs-danger-bg-subtle, rgba(220, 53, 69, 0.12));
        color: var(--bs-danger-text-emphasis, #b02a37);
    }

    #drawerContent .cashdrawer-denomination-section {
        --cashdrawer-denomination-accent: #16834a;
        --cashdrawer-denomination-soft: rgba(22, 131, 74, 0.1);
        margin-top: 1rem;
        overflow: hidden;
        border: 1px solid rgba(22, 131, 74, 0.24);
        border-radius: 14px;
        background: var(--bs-body-bg);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07);
    }

    #drawerContent .cashdrawer-denomination-section--cfa {
        --cashdrawer-denomination-accent: #7250b5;
        --cashdrawer-denomination-soft: rgba(114, 80, 181, 0.11);
        border-color: rgba(114, 80, 181, 0.26);
    }

    #drawerContent .cashdrawer-denomination-heading {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        background: linear-gradient(110deg, var(--cashdrawer-denomination-soft), transparent 78%);
        border-bottom: 1px solid var(--bs-border-color);
    }

    #drawerContent .cashdrawer-denomination-heading-icon {
        display: inline-flex;
        flex: 0 0 2.3rem;
        width: 2.3rem;
        height: 2.3rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        background: var(--cashdrawer-denomination-accent);
        color: #fff;
    }

    #drawerContent .cashdrawer-denomination-heading h6 {
        margin: 0;
        color: var(--bs-body-color);
        font-size: 0.92rem;
        font-weight: 800;
        letter-spacing: 0.01em;
    }

    #drawerContent .cashdrawer-denomination-heading small {
        display: block;
        margin-top: 0.1rem;
        color: var(--bs-secondary-color);
        font-size: 0.72rem;
    }

    #drawerContent .cashdrawer-denomination-currency {
        margin-left: auto;
        padding: 0.3rem 0.65rem;
        border: 1px solid var(--cashdrawer-denomination-accent);
        border-radius: 999px;
        color: var(--cashdrawer-denomination-accent);
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.06em;
    }

    #drawerContent .cashdrawer-denomination-table-wrap {
        overflow-x: auto;
    }

    #drawerContent .cashdrawer-denomination-table {
        --bs-table-bg: transparent;
        margin: 0;
    }

    #drawerContent .cashdrawer-denomination-table thead th {
        padding: 0.65rem 0.75rem !important;
        background: var(--cashdrawer-denomination-soft);
        border-bottom: 1px solid rgba(127, 127, 127, 0.18);
        color: var(--cashdrawer-denomination-accent);
        font-size: 0.66rem;
        letter-spacing: 0.07em;
    }

    #drawerContent .cashdrawer-denomination-table tbody tr {
        border-bottom: 1px solid rgba(127, 127, 127, 0.12);
        transition: background-color 0.15s ease;
    }

    #drawerContent .cashdrawer-denomination-table tbody tr:nth-child(even) > * {
        background-color: rgba(127, 127, 127, 0.035);
    }

    #drawerContent .cashdrawer-denomination-table tbody tr:hover > * {
        background-color: var(--cashdrawer-denomination-soft);
    }

    #drawerContent .cashdrawer-denomination-index {
        display: inline-flex;
        width: 1.45rem;
        height: 1.45rem;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--cashdrawer-denomination-soft);
        color: var(--cashdrawer-denomination-accent);
        font-size: 0.68rem;
        font-weight: 700;
    }

    #drawerContent .cashdrawer-denomination-value {
        display: inline-block;
        padding: 0.22rem 0.5rem;
        border: 1px solid rgba(127, 127, 127, 0.18);
        border-radius: 0.45rem;
        background: var(--bs-body-bg);
        color: var(--bs-body-color);
        font-weight: 700;
    }

    #drawerContent .cashdrawer-denomination-count {
        display: inline-block;
        min-width: 2.1rem;
        padding: 0.2rem 0.45rem;
        border-radius: 999px;
        background: var(--cashdrawer-denomination-soft);
        color: var(--cashdrawer-denomination-accent);
        font-weight: 800;
        text-align: center;
    }

    #drawerContent .cashdrawer-denomination-volume {
        color: var(--cashdrawer-denomination-accent);
        font-weight: 800;
        white-space: nowrap;
    }

    #drawerContent .cashdrawer-denomination-total th {
        padding: 0.7rem 0.75rem !important;
        border-top: 2px solid var(--cashdrawer-denomination-accent);
        background: var(--cashdrawer-denomination-soft);
        color: var(--cashdrawer-denomination-accent);
        font-weight: 800;
    }

    @media (max-width: 767.98px) {
        .panel-overlay {
            z-index: 1025;
        }

        .drawer-panel,
        .drawer-panel[style*="bottom: 12px"] {
            left: 50%;
            width: calc(100vw - 16px);
            max-width: none;
            z-index: 1030;
            box-sizing: border-box;
            top: 76px;
            bottom: 8px !important;
            transform: translate(-50%, calc(100% + 16px));
            -webkit-transform: translate(-50%, calc(100% + 16px));
            transition: transform 0.3s ease-in-out;
            -webkit-transition: -webkit-transform 0.3s ease-in-out;
            height: auto;
            max-height: none;
            padding: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .drawer-panel.drawer-open {
            transform: translate(-50%, 0);
            -webkit-transform: translate(-50%, 0);
        }

        .panel-header {
            flex: 0 0 auto;
            padding-bottom: 8px;
        }

        .panel-header > div {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 4px;
            min-width: 0;
        }

        .panel-header .btn {
            margin: 0 !important;
            padding: 0.25rem 0.4rem !important;
        }

        #drawerContent.panel-body {
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
        }

        #drawerContent .reportDiv {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        #drawerContent .reportDiv > div,
        #drawerContent .reportDiv .row {
            min-width: 0;
            max-width: 100%;
        }

        #drawerContent .cashdrawer-summary-header {
            padding: 0.7rem;
            border-radius: 0.8rem;
        }

        #drawerContent .cashdrawer-summary-created {
            max-width: 100%;
            white-space: normal;
        }

        #drawerContent .cashdrawer-summary-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.45rem;
        }

        #drawerContent .cashdrawer-summary-metric {
            min-height: 3.2rem;
            gap: 0.35rem;
            padding: 0.45rem 0.4rem;
        }

        #drawerContent .cashdrawer-summary-metric-icon {
            flex-basis: 1.5rem;
            width: 1.5rem;
            height: 1.5rem;
            font-size: 0.78rem;
        }

        #drawerContent .cashdrawer-summary-metric-label {
            font-size: 0.57rem;
        }

        #drawerContent table {
            width: 100% !important;
            max-width: 100%;
            table-layout: fixed;
        }

        #drawerContent th,
        #drawerContent td {
            padding: 0.3rem 0.2rem !important;
            font-size: clamp(0.58rem, 2.4vw, 0.72rem);
            line-height: 1.25;
            white-space: normal !important;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }

        #drawerContent .cashdrawer-denomination-table th:nth-child(1),
        #drawerContent .cashdrawer-denomination-table td:nth-child(1) { width: 8%; }
        #drawerContent .cashdrawer-denomination-table th:nth-child(2),
        #drawerContent .cashdrawer-denomination-table td:nth-child(2) { width: 34%; }
        #drawerContent .cashdrawer-denomination-table th:nth-child(3),
        #drawerContent .cashdrawer-denomination-table td:nth-child(3) { width: 20%; }
        #drawerContent .cashdrawer-denomination-table th:nth-child(4),
        #drawerContent .cashdrawer-denomination-table td:nth-child(4) { width: 38%; }

        #drawerContent .cashdrawer-denomination-table th,
        #drawerContent .cashdrawer-denomination-table td {
            font-size: 0.62rem;
        }

        #drawerContent .cashdrawer-channel-table thead {
            position: absolute !important;
            top: 0 !important;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        #drawerContent .cashdrawer-channel-table {
            display: block;
        }

        #drawerContent .cashdrawer-channel-table tbody tr {
            display: grid;
            width: 100% !important;
            box-sizing: border-box;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.35rem 0.65rem;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border: 1px solid var(--bs-border-color) !important;
            border-radius: 0.55rem;
            background: var(--bs-tertiary-bg);
        }

        #drawerContent .cashdrawer-channel-table tbody {
            display: block;
            width: 100%;
            max-width: 100%;
        }

        #drawerContent .cashdrawer-channel-table tbody td {
            display: flex;
            width: auto !important;
            min-width: 0;
            flex-direction: column;
            gap: 0.1rem;
            padding: 0.2rem !important;
            border: 0 !important;
            text-align: left !important;
            overflow-wrap: anywhere;
        }

        #drawerContent .cashdrawer-channel-table tbody td:not(:first-child)::before {
            content: attr(data-label);
            color: var(--bs-secondary-color);
            font-size: 0.58rem;
            font-weight: 600;
            letter-spacing: 0.035em;
            text-transform: uppercase;
        }

        #drawerContent .cashdrawer-channel-table tbody td:first-child {
            grid-column: 1 / -1;
            padding-bottom: 0.45rem !important;
            border-bottom: 1px solid var(--bs-border-color) !important;
            font-weight: 700;
        }

        #drawerContent .cashdrawer-channel-table td span {
            display: inline-block;
            align-self: flex-start;
            max-width: 100%;
            padding: 2px 5px !important;
            font-size: 0.68rem !important;
            overflow-wrap: anywhere;
        }

        #drawerContent h4,
        #drawerContent h5,
        #drawerContent h6 {
            overflow-wrap: anywhere;
        }
    }

    .select2-container--default .select2-selection--multiple{
      background: transparent !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
      background: transparent !important;
    }

    /* Ops Chat / Ask Assistant widget (global) */
    .ai-assist-toggle {
      position: static;
      z-index: 1100;
    }

    .ai-assist-toggle button {
      border-radius: 999px;
      border: 1px solid rgba(148,163,184,0.8);
      background: radial-gradient(circle at 0 0, rgba(251,191,36,0.9), rgba(70, 75, 86, 0.98));
      color: #ffffffff;
      font-weight: 600;
      padding: 0.55rem 1.1rem;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      box-shadow: 0 12px 30px rgba(15,23,42,0.9);
    }

    .ai-assist-panel {
      position: fixed;
      right: 1rem;
      top: 4.25rem;
      width: 320px;
      max-width: calc(100% - 2rem);
      max-height: 440px;
      display: none;
      flex-direction: column;
      border-radius: 14px;
      border: 1px solid rgba(148,163,184,0.7);
      background-color: var(--bs-body-bg);
      box-shadow: 0 22px 65px rgba(15,23,42,0.35);
      overflow: hidden;
      z-index: 1100;
    }

    .ai-assist-header {
      padding: 0.6rem 0.8rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(148,163,184,0.5);
      background: linear-gradient(90deg, rgba(15,23,42,0.98), rgba(30,64,175,0.9));
    }

    .ai-assist-title {
      font-size: 0.8rem;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: #e5e7eb;
    }

    .ai-assist-status {
      font-size: 0.7rem;
      color: #a5b4fc;
    }

    .ai-assist-body {
      flex: 1 1 auto;
      padding: 0.6rem 0.7rem;
      overflow-y: auto;
      font-size: 0.82rem;
    }

    .ai-assist-message {
      margin-bottom: 0.5rem;
      display: flex;
    }

    .ai-assist-message.ai-assist-user {
      justify-content: flex-end;
    }

    .ai-assist-message.ai-assist-assistant {
      justify-content: flex-start;
    }

    .ai-assist-bubble {
      max-width: 90%;
      padding: 0.35rem 0.6rem;
      border-radius: 0.75rem;
      border: 1px solid rgba(148,163,184,0.4);
      box-shadow: 0 4px 10px rgba(15,23,42,0.25);
    }

    [data-bs-theme="light"] .ai-assist-user .ai-assist-bubble {
      background: linear-gradient(135deg, #0ea5e9, #22c55e);
      color: #0b1120;
    }

    [data-bs-theme="dark"] .ai-assist-user .ai-assist-bubble {
      background: linear-gradient(135deg, rgba(15,23,42,0.98), rgba(30,41,59,0.96));
      color: #ffffff;
    }

    [data-bs-theme="light"] .ai-assist-assistant .ai-assist-bubble {
      background: #f3f4f6;
      color: #111827;
      border-color: rgba(148,163,184,0.6);
    }

    [data-bs-theme="dark"] .ai-assist-assistant .ai-assist-bubble {
      background: rgba(15,23,42,0.72);
      color: #e5e7eb;
      border-color: rgba(148,163,184,0.7);
    }

    .ai-assist-footer {
      padding: 0.45rem 0.55rem 0.55rem;
      border-top: 1px solid rgba(148,163,184,0.45);
    }

    [data-bs-theme="light"] .ai-assist-footer {
      background: #f9fafb;
    }

    [data-bs-theme="dark"] .ai-assist-footer {
      background: rgba(15,23,42,0.96);
    }

    .ai-assist-input-wrap {
      display: flex;
      gap: 0.35rem;
    }

    .ai-assist-input-wrap textarea {
      resize: none;
      height: 40px;
      max-height: 64px;
      font-size: 0.8rem;
    }

    .ai-assist-send {
      border-radius: 999px;
      border: 1px solid rgba(56,189,248,0.8);
      background: linear-gradient(135deg, #22c55e, #0ea5e9);
      color: #0b1120;
      font-size: 0.8rem;
      font-weight: 600;
      padding: 0 0.7rem;
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
    }

    .ai-assist-attach-btn {
      border-radius: 999px;
      border: 1px dashed rgba(148,163,184,0.8);
      padding: 0.1rem 0.55rem;
      font-size: 0.7rem;
    }

    .ai-assist-preview-strip {
      display: flex;
      gap: 0.35rem;
      margin-bottom: 0.35rem;
      overflow-x: auto;
      padding-bottom: 0.15rem;
    }

    .ai-assist-preview-thumb {
      position: relative;
      width: 42px;
      height: 42px;
      flex: 0 0 auto;
      border-radius: 0.6rem;
      overflow: hidden;
      border: 1px solid rgba(148,163,184,0.6);
      box-shadow: 0 3px 6px rgba(15,23,42,0.35);
    }

    .ai-assist-preview-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .ai-assist-preview-remove {
      position: absolute;
      top: 1px;
      right: 1px;
      border: none;
      border-radius: 999px;
      width: 14px;
      height: 14px;
      font-size: 0.55rem;
      line-height: 14px;
      text-align: center;
      background: rgba(15,23,42,0.9);
      color: #e5e7eb;
      cursor: pointer;
    }

    .ai-assist-images-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
      gap: 0.35rem;
      margin-bottom: 0.3rem;
    }

    .ai-assist-images-grid img {
      width: 100%;
      max-height: 100px;
      border-radius: 0.6rem;
      object-fit: cover;
      display: block;
    }

    .ai-assist-image-viewer-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.85);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1400;
    }

    .ai-assist-image-viewer-backdrop.is-open {
      display: flex;
    }

    .ai-assist-image-viewer {
      max-width: 90vw;
      max-height: 90vh;
      background: rgba(15,23,42,0.95);
      border-radius: 0.75rem;
      padding: 0.75rem;
      box-shadow: 0 30px 80px rgba(0,0,0,0.8);
      border: 1px solid rgba(148,163,184,0.6);
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .ai-assist-image-viewer img {
      max-width: 80vw;
      max-height: 70vh;
      border-radius: 0.6rem;
      object-fit: contain;
      background: #020617;
    }

    .ai-assist-image-viewer-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.8rem;
    }

    .ai-assist-image-viewer-actions {
      display: flex;
      gap: 0.4rem;
    }

    .admin-submit-progress {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      z-index: 2147482500;
      pointer-events: none;
      opacity: 0;
      transform: translateY(-100%);
      transition: opacity 0.18s ease, transform 0.18s ease;
      background: rgba(148, 163, 184, 0.22);
    }

    .admin-submit-progress.is-active {
      opacity: 1;
      transform: translateY(0);
    }

    .admin-submit-progress__bar {
      width: 0%;
      height: 100%;
      background: linear-gradient(90deg, #fbbf24, #0ea5e9, #2563eb);
      box-shadow: 0 0 18px rgba(37, 99, 235, 0.42);
      transition: width 0.28s ease;
    }

    .admin-submit-progress__label {
      position: fixed;
      top: 10px;
      left: 50%;
      transform: translateX(-50%);
      display: none;
      align-items: center;
      gap: 0.45rem;
      min-width: 160px;
      justify-content: center;
      padding: 0.42rem 0.7rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      line-height: 1;
      color: #0f172a;
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(148, 163, 184, 0.35);
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.16);
      z-index: 2147482501;
      pointer-events: none;
    }

    .admin-submit-progress.is-active .admin-submit-progress__label {
      display: flex;
    }

    [data-bs-theme="dark"] .admin-submit-progress__label,
    html[data-bs-theme="dark"] .admin-submit-progress__label {
      color: #e5e7eb;
      background: rgba(15, 23, 42, 0.96);
      border-color: rgba(148, 163, 184, 0.28);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
    }

    .admin-submit-progress__spinner,
    .admin-submit-button-spinner {
      width: 0.9rem;
      height: 0.9rem;
      border: 2px solid currentColor;
      border-right-color: transparent;
      border-radius: 999px;
      animation: admin-submit-spin 0.7s linear infinite;
      flex: 0 0 auto;
    }

    .admin-submit-button-spinner {
      display: inline-block;
      margin-right: 0.4rem;
      vertical-align: -0.12em;
    }

    .admin-submit-busy {
      cursor: wait !important;
      opacity: 0.88;
    }

    @keyframes admin-submit-spin {
      to { transform: rotate(360deg); }
    }
  </style>

  <!-- Custom styles for this template -->
  <link href="<?=plugin_http_path('assets/css/dashboard.css')?>" rel="stylesheet">

<script>
  (function(){
    const root = document.documentElement;
    const hidePageSkeleton = function(){
      root.classList.remove('app-page-loading');
      const skeleton = document.getElementById('appPageSkeleton');
      if(skeleton) skeleton.setAttribute('aria-hidden', 'true');
    };
    const showPageSkeleton = function(){
      root.classList.add('app-page-loading');
      const skeleton = document.getElementById('appPageSkeleton');
      if(skeleton) skeleton.setAttribute('aria-hidden', 'false');
    };

    if(document.readyState !== 'complete'){
      showPageSkeleton();
      window.addEventListener('load', hidePageSkeleton, {once: true});
    }else{
      hidePageSkeleton();
    }

    window.addEventListener('pageshow', hidePageSkeleton);
    document.addEventListener('click', function(event){
      if(event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const target = event.target instanceof Element ? event.target : null;
      const link = target ? target.closest('a[href]') : null;
      if(!link || (link.target && link.target.toLowerCase() !== '_self') || link.hasAttribute('download')) return;
      let destination;
      try{
        destination = new URL(link.href, window.location.href);
      }catch(error){
        return;
      }
      if(destination.origin !== window.location.origin) return;
      if(destination.pathname === window.location.pathname && destination.search === window.location.search) return;
      showPageSkeleton();
    }, true);
    document.addEventListener('submit', function(event){
      const form = event.target;
      if(!(form instanceof HTMLFormElement) || (form.target && form.target.toLowerCase() !== '_self')) return;
      window.setTimeout(function(){
        if(!event.defaultPrevented) showPageSkeleton();
      }, 0);
    }, true);
  })();
</script>
<body class="<?=$__mobileDocumentScrollPage ? 'mobile-document-scroll-page' : ''?>">
  <div id="appPageSkeleton" class="app-page-skeleton" role="status" aria-label="Loading page" aria-hidden="false">
    <aside class="app-page-skeleton__sidebar" aria-hidden="true">
      <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--brand"></span>
      <div class="app-page-skeleton__nav">
        <span class="app-page-skeleton__shimmer"></span>
        <span class="app-page-skeleton__shimmer"></span>
        <span class="app-page-skeleton__shimmer"></span>
        <span class="app-page-skeleton__shimmer"></span>
        <span class="app-page-skeleton__shimmer"></span>
        <span class="app-page-skeleton__shimmer"></span>
        <span class="app-page-skeleton__shimmer"></span>
      </div>
    </aside>
    <div class="app-page-skeleton__main" aria-hidden="true">
      <div class="app-page-skeleton__topbar">
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--short"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--short"></span>
      </div>
      <div class="app-page-skeleton__heading">
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--title"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--subtitle"></span>
      </div>
      <div class="app-page-skeleton__cards">
        <div class="app-page-skeleton__card"><span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--short"></span><span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span></div>
        <div class="app-page-skeleton__card"><span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--short"></span><span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span></div>
        <div class="app-page-skeleton__card"><span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--short"></span><span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span></div>
      </div>
      <div class="app-page-skeleton__panel">
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--short"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span>
        <span class="app-page-skeleton__shimmer app-page-skeleton__shimmer--line"></span>
      </div>
    </div>
  </div>
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none">
      <symbol id="check2" viewBox="0 0 16 16">
        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
      </symbol>
      <symbol id="circle-half" viewBox="0 0 16 16">
        <path d="M8 15A7 7 0 1 0 8 1v14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/>
      </symbol>
      <symbol id="moon-stars-fill" viewBox="0 0 16 16">
        <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
        <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
      </symbol>
      <symbol id="sun-fill" viewBox="0 0 16 16">
        <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
      </symbol>
    </svg>

    <div class="ai-assist-panel" id="aiAssistPanel" aria-hidden="true">
      <div class="ai-assist-header">
        <div>
          <div class="ai-assist-title">OPS CHAT</div>
          <div class="ai-assist-status">All online users â€¢ Shared rooms</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-light" id="aiAssistCloseBtn">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div class="px-2 pt-1">
        <select id="aiChatRoom" class="form-select form-select-sm">
          <option value="global">Global room</option>
          <option value="transactions">Transactions</option>
          <option value="commissions">Commissions</option>
          <option value="attendance">Attendance</option>
          <option value="activities">Activities</option>
          <?php if (!empty($session->roles) && is_array($session->roles)): ?>
            <?php foreach ($session->roles as $r): ?>
              <?php $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/','-', $r)); ?>
              <option value="role:<?=$slug?>">Role: <?=htmlspecialchars($r)?></option>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (!empty($session->branch_name ?? $session->branch ?? null)): ?>
            <?php $branchKey = $session->branch_name ?? $session->branch; ?>
            <?php $bslug = strtolower(preg_replace('/[^a-zA-Z0-9]+/','-', $branchKey)); ?>
            <option value="branch:<?=$bslug?>">Branch: <?=htmlspecialchars($branchKey)?></option>
          <?php endif; ?>
        </select>
      </div>
      <div class="ai-assist-body" id="aiAssistMessages"></div>

      <div class="ai-assist-footer">
        <div class="mb-1 d-flex align-items-center justify-content-between gap-2">
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary ai-assist-attach-btn" id="aiAssistAttachBtn">
              <i class="fa fa-paperclip"></i>
            </button>
            <small class="text-muted" style="font-size: 0.7rem;">Attach up to 5 images</small>
          </div>
          <input type="file" id="aiAssistImages" accept="image/*" multiple style="display:none;">
        </div>
        <div id="aiAssistPreviewStrip" class="ai-assist-preview-strip"></div>
        <div class="ai-assist-input-wrap">
          <textarea id="aiAssistInput" class="form-control" placeholder="Type a message or attach an image..."></textarea>
          <button type="button" class="ai-assist-send" id="aiAssistSendBtn">
            <span>Send</span>
            <i class="fa fa-paper-plane"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Chat image viewer overlay -->
    <div id="aiAssistImageViewerBackdrop" class="ai-assist-image-viewer-backdrop" aria-hidden="true">
      <div class="ai-assist-image-viewer">
        <img id="aiAssistImageViewerImg" src="" alt="" />
        <div class="ai-assist-image-viewer-footer">
          <div id="aiAssistImageViewerCaption" class="text-truncate"></div>
          <div class="ai-assist-image-viewer-actions">
            <a id="aiAssistImageViewerDownload" class="btn btn-sm btn-outline-light" href="#" download>
              <i class="fa fa-download"></i>
            </a>
            <button type="button" id="aiAssistImageViewerClose" class="btn btn-sm btn-outline-secondary">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <svg xmlns="http://www.w3.org/2000/svg" class="d-none">   
      <symbol id="list" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
      </symbol>
   
      <symbol id="search" viewBox="0 0 16 16">
        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
      </symbol>
    </svg>

  <header class="navbar sticky-top flex-md-nowrap p-0 shadow" data-bs-theme="dark">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 fw-bolder d-flex align-items-center justify-content-between" href="#">
      <div class="navbar-brand-title d-flex align-items-center gap-2">
        <img style="width:30px; height: 25px;" src="<?=APP_LOGO2?>">
        <span class="app-name"><?=APP_NAME?></span>
      </div>
      <div class="minimize-toggle">
        <i class="fas fa-bars text-white p-1" style="background: rgba(0, 0, 0, 0.1);"></i>
      </div>
    </a>

    <ul class="navbar-nav flex-row d-md-none">
      <li class="nav-item text-nowrap">
        <button class="nav-link px-3 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSearch" aria-controls="navbarSearch" aria-expanded="false" aria-label="Toggle search">
          <svg class="bi"><use xlink:href="#search"/></svg>
        </button>
      </li>
      <li class="nav-item text-nowrap">
        <button class="nav-link px-3 text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
          <svg class="bi"><use xlink:href="#list"/></svg>
        </button>
      </li>
    </ul>

    <div id="navbarSearch" class="navbar-search w-100 collapse">
      <input class="form-control w-100 rounded-0 border-0" type="text" placeholder="Search" aria-label="Search">
    </div>

    <div id="headerQuickActions" class="ms-auto me-2 d-flex align-items-center header-quick-actions" style="gap: 6px;">
      <div id="idleCountdownWrap" class="d-flex align-items-center" style="gap: 6px;">
        <span class="text-white-50" style="font-size: 0.75rem;">Auto logout in</span>
        <span class="badge bg-warning text-dark" id="idleCountdown" style="font-size: 0.8rem;">--:--</span>
      </div>

      <div class="dropdown bd-mode-toggle">
        <button class="btn btn-bd-primary py-2 dropdown-toggle d-flex align-items-center"
                id="bd-theme"
                type="button"
                aria-expanded="false"
                data-bs-toggle="dropdown"
                aria-label="Toggle theme (auto)">
          <svg class="bi my-1 theme-icon-active" width="1em" height="1em"><use href="#circle-half"></use></svg>
          <span class="visually-hidden" id="bd-theme-text">Toggle theme</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bd-theme-text">
          <li>
            <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
              <svg class="bi me-2 opacity-50 theme-icon" width="1em" height="1em"><use href="#sun-fill"></use></svg>
              Light
              <svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
            </button>
          </li>
          <li>
            <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
              <svg class="bi me-2 opacity-50 theme-icon" width="1em" height="1em"><use href="#moon-stars-fill"></use></svg>
              Dark
              <svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
            </button>
          </li>
          <li>
            <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
              <svg class="bi me-2 opacity-50 theme-icon" width="1em" height="1em"><use href="#circle-half"></use></svg>
              Auto
              <svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
            </button>
          </li>
        </ul>
      </div>

      <?php $__isAirtimeReconPage = ((URL(1) ?? '') === 'back_office' && in_array((URL(2) ?? ''), ['reconciliation','view_reconciliation'], true)); ?>
    <div class="cashdrawer-toggle" style="z-index: 1001;">
    <?php if ($__isAirtimeReconPage): ?>
      <button class="btn btn-sm btn-primary" type="button" disabled title="Cash Drawer is disabled on reconciliation page">
        <i class="fa fa-briefcase"></i> Cash Drawer
      </button>
    <?php else: ?>
      <button class="btn btn-sm btn-primary view-drawer-btn" data-id="<?=$user_id ?? 0?>"><i class="fa fa-briefcase"></i> Cash Drawer</button>
    <?php endif; ?>
    </div>

      <!-- <div class="ai-assist-toggle">
        <button type="button" id="aiAssistToggleBtn" onclick="window.__toggleOpsChat && window.__toggleOpsChat()">
          <i class="fa fa-robot"></i>
          <span>Ask</span>
        </button>
      </div> -->
    </div>
  </header>

  <div class="container-fluid">
    <div class="row">
      <div id="mySideBar" class="sidebar border border-right col-md-3 col-lg-2 p-0 bg-body-tertiary">
        <div class="offcanvas-md offcanvas-start bg-body-tertiary" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel" style="left:0; right:auto; width:85vw; max-width:420px;">
            <div class="offcanvas-header">
                <div class="my-auto d-flex fw-bolder">
                    <img style="width:30px; height: 25px;" src="<?= APP_LOGO2 ?>">
                    <h5 class="offcanvas-title my-auto" id="sidebarMenuLabel"><?= APP_NAME ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-md-flex flex-column p-0 overflow-y-auto">

                <ul class="nav flex-column" style="margin-bottom: 0.25rem;">
                    <?php 
                    $admin = get_session();
                    $username = $admin->last_name ?? 'Admin';
                    $notificationPillColorConfig = function_exists('admin_settings_notification_pill_color_config') ? admin_settings_notification_pill_color_config() : [];
                    $notificationPillPluginMap = function_exists('admin_settings_notification_pill_plugin_map_config') ? admin_settings_notification_pill_plugin_map_config() : ['serious'=>[],'mild'=>[],'regular'=>[]];
                    $notificationPillUrgency = static function(string $id) use ($notificationPillPluginMap): string {
                      $id = strtolower(trim((string)$id));

                      // explicit mapping from settings (match id or substring)
                      foreach(['serious','mild','regular'] as $cat){
                        if(!empty($notificationPillPluginMap[$cat]) && is_array($notificationPillPluginMap[$cat])){
                          foreach($notificationPillPluginMap[$cat] as $pid){
                            $pid = strtolower(trim((string)$pid));
                            if($pid === '') continue;
                            if($id === $pid || strpos($id, $pid) !== false){
                              return $cat;
                            }
                          }
                        }
                      }

                      // Specific serious (urgent) - backoffice: payments, requests, to&from bank
                      $serious_specific = ['backoffice_payment', 'backoffice payment', 'backoffice_request', 'backoffice request', 'to&from bank', 'to_from_bank', 'bank_transfer'];
                      foreach ($serious_specific as $kw) {
                        if (strpos($id, $kw) !== false) {
                          return 'serious';
                        }
                      }

                      // Specific mild - backoffice: commissions, reconciliations
                      $mild_specific = ['backoffice_commission', 'backoffice commission', 'backoffice_reconciliation', 'backoffice reconciliation'];
                      foreach ($mild_specific as $kw) {
                        if (strpos($id, $kw) !== false) {
                          return 'mild';
                        }
                      }

                      // General serious plugins (all pages within these)
                      $serious = ['agencybanking', 'airtimebundle', 'cfa', 'momoremit', 'payment_service', 'ezwich', 'sim', 'cashpickup'];
                      foreach ($serious as $kw) {
                        if (strpos($id, $kw) !== false) {
                          return 'serious';
                        }
                      }

                      // General mild plugins
                      $mild = ['reversal', 'pettycash'];
                      foreach ($mild as $kw) {
                        if (strpos($id, $kw) !== false) {
                          return 'mild';
                        }
                      }

                      // Regular (default)
                      return 'regular';
                    };
                    $notificationPillBadgeAttributes = static function(string $urgency, array $config, int $count): string {
                        $value = trim((string)($config[$urgency] ?? ''));
                        $classes = ['badge', 'fw-bolder', 'rounded-pill'];
                        $styles = [];
                        if(preg_match('/^#[0-9A-Fa-f]{6}$/', $value)){
                            $classes[] = 'text-white';
                            $styles[] = 'background-color:' . $value;
                            $r = hexdec(substr($value,1,2));
                            $g = hexdec(substr($value,3,2));
                            $b = hexdec(substr($value,5,2));
                            $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
                            $styles[] = 'color:' . ($brightness < 150 ? '#ffffff' : '#000000');
                        } elseif($value !== ''){
                            $classes = array_merge($classes, preg_split('/\s+/', $value));
                        } else {
                            $classes[] = 'bg-warning';
                            $classes[] = 'text-black';
                        }
                        if($count <= 0){
                            $classes[] = 'd-none';
                        }
                        $attr = 'class="' . esc(implode(' ', $classes)) . '"';
                        if(!empty($styles)){
                            $attr .= ' style="' . esc(implode(';', $styles)) . '"';
                        }
                        return $attr;
                    };
                    ?>
                    <?php if (!empty($admin)): ?>
                        <li id="navItem_<?= esc($admin->first_name) ?>" class="nav-item" title="<?= esc($admin->first_name) ?>">
                            <a class="nav-link d-flex align-items-center gap-2" href="<?= ROOT ?>/admin/profile/view/<?= $admin->id ?>">
                                <img src="<?= get_image($admin->image, $admin->gender) ?>" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover; background-color: transparent;">
                                <span class="fw-bolder"><?= esc($username) ?> <i class="fa-solid fa-circle-check text-muted"></i></span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <hr class="my-1">

                <!-- Navigation Links -->
                <ul id="sidebar-nav" class="nav flex-column">
                  <?php foreach ($links as $obj): ?>
                    <?php
                      $showLink = false;
                      if (user_can($obj->id ?? '')) {
                        $showLink = true;
                      } elseif (!empty($obj->sublinks) && is_array($obj->sublinks)) {
                        foreach ($obj->sublinks as $sub) {
                          if (user_can($sub->id ?? '') || (!empty($sub->count) && (int)$sub->count > 0)) {
                            $showLink = true;
                            break;
                          }
                          if (!empty($sub->minorlinks) && is_array($sub->minorlinks)) {
                            foreach ($sub->minorlinks as $minor) {
                              if (user_can($minor->id ?? '') || (!empty($minor->count) && (int)$minor->count > 0)) {
                                $showLink = true;
                                break 2;
                              }
                            }
                          }
                        }
                      }
                    ?>
                    <?php if ($showLink): ?>
                      <li id="navItem_<?= esc($obj->title) ?>" class="nav-item" title="<?= esc($obj->title) ?>">
                        <?php if (empty($obj->sublinks)): ?>
                          <a class="nav-link d-flex align-items-center gap-2" href="<?= esc($obj->link) ?>">
                            <i class="<?= esc($obj->icon) ?>"></i>
                            <span><?= esc($obj->title) ?></span>
                            <span <?= $notificationPillBadgeAttributes($notificationPillUrgency($obj->id ?? ''), $notificationPillColorConfig, (int)($obj->count ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency($obj->id ?? '')) ?>" data-notification-badge="<?= esc($obj->id ?? '') ?>"><?= !empty($obj->count) && $obj->count > 0 ? esc($obj->count) : '' ?></span>
                          </a>
                        <?php else: ?>
                          <a class="nav-link d-flex align-items-center gap-2 collapsed"
                             data-bs-target="#icons-nav<?= esc($obj->id) ?>"
                             data-bs-toggle="collapse" href="#">
                            <i class="<?= esc($obj->icon) ?>"></i>
                            <span><?= esc($obj->title) ?></span>
                            <span <?= $notificationPillBadgeAttributes($notificationPillUrgency($obj->id ?? ''), $notificationPillColorConfig, (int)($obj->count ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency($obj->id ?? '')) ?>" data-notification-badge="<?= esc($obj->id ?? '') ?>"><?= !empty($obj->count) && $obj->count > 0 ? esc($obj->count) : '' ?></span>
                            <i class="fa-solid fa-caret-down float-end"></i>
                          </a>
                          <ul id="icons-nav<?= esc($obj->id) ?>" class="subnav nav-content collapse" data-dropdown-id="dropdown-<?= esc($obj->id) ?>" data-bs-parent="#sidebar-nav">
                            <?php foreach ($obj->sublinks as $sub): ?>
                              <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2 <?= !empty($sub->minorlinks) ? 'collapsed' : '' ?>"
                                   data-bs-target="#icons-nav-sub<?= esc($sub->id) ?>"
                                   data-bs-toggle="<?= !empty($sub->minorlinks) ? 'collapse' : '' ?>"
                                   href="<?= esc($sub->link) ?>">
                                  <i class="<?= esc($sub->icon) ?>"></i>
                                  <span><?= esc($sub->title) ?></span>
                                  <span <?= $notificationPillBadgeAttributes($notificationPillUrgency($sub->id ?? ''), $notificationPillColorConfig, (int)($sub->count ?? 0)) ?> data-notification-badge="<?= esc($sub->id ?? '') ?>"><?= !empty($sub->count) && $sub->count > 0 ? esc($sub->count) : '' ?></span>
                                </a>
                                <?php if (!empty($sub->minorlinks)): ?>
                                  <ul id="icons-nav-sub<?= esc($sub->id) ?>" class="subnav nav-content collapse" data-dropdown-id="dropdown-sub-<?= esc($sub->id) ?>" data-bs-parent="#icons-nav<?= esc($obj->id) ?>">
                                    <?php foreach ($sub->minorlinks as $minor): ?>
                                      <li class="nav-item">
                                        <a class="nav-link" href="<?= esc($minor->link) ?>">
                                          <i class="<?= esc($minor->icon) ?>"></i>
                                          <span><?= esc($minor->title) ?></span>
                                          <span <?= $notificationPillBadgeAttributes($notificationPillUrgency($minor->id ?? ''), $notificationPillColorConfig) ?> data-notification-urgency="<?= esc($notificationPillUrgency($minor->id ?? '')) ?>" <?= empty($minor->count) || $minor->count <= 0 ? 'class="d-none"' : '' ?> data-notification-badge="<?= esc($minor->id ?? '') ?>"><?= !empty($minor->count) && $minor->count > 0 ? esc($minor->count) : '' ?></span>
                                        </a>
                                      </li>
                                    <?php endforeach; ?>
                                  </ul>
                                <?php endif; ?>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        <?php endif; ?>
                      </li>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </ul>
                <hr class="my-1">

                <!-- Bottom Links -->
                <ul class="nav flex-column mb-auto">
                    <?php foreach ($bottom_links as $obj): ?>
                        <li id="navItem_<?= esc($obj->title) ?>" class="nav-item" title="<?= esc($obj->title) ?>">
                            <a class="nav-link d-flex align-items-center gap-2" href="<?= esc($obj->link) ?>">
                                <i class="<?= esc($obj->icon) ?>"></i>
                                <span><?= esc($obj->title) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
      <?php
      $__reconGateActive = false;
      $__reconRedirectUrl = ROOT . '/admin/back_office/reconciliation';
      $__reconIsReconPage = ((URL(1) ?? '') === 'back_office' && (URL(2) ?? '') === 'reconciliation');
      $__idleTimeoutSeconds = 300;
      $__logoutUrl = ROOT . '/logout';
      $__userId = 0;

      try {
        $__dbSettings = new \Core\Database;
        if ($__dbSettings->table_exists(['settings'])) {
          $__settingsRow = ($__dbSettings->query("SELECT inactivity_limit_minutes FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
          $__mins = $__settingsRow ? (int)($__settingsRow->inactivity_limit_minutes ?? 0) : 0;
          if ($__mins > 0) {
            $__idleTimeoutSeconds = $__mins * 60;
          }
        }
      } catch (\Throwable $e) {
        $__idleTimeoutSeconds = 300;
      }

      try {
        $__session = get_session();
        $__userId = (int)($__session->id ?? 0);
        if ($__userId > 0) {
          $__dbReconGate = new \Core\Database;
          if ($__dbReconGate->table_exists(['settings', 'reconciliations_summary'])) {
            $__settings = ($__dbReconGate->query("SELECT work_close FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
            $__workClose = $__settings ? trim((string)($__settings->work_close ?? '')) : '';
            if ($__workClose !== '') {
              $__tz = new \DateTimeZone('Africa/Accra');
              $__now = new \DateTime('now', $__tz);
              $__closeToday = new \DateTime($__now->format('Y-m-d') . ' ' . $__workClose, $__tz);
              if ($__now >= $__closeToday) {
                try {
                  if (!$__reconIsReconPage && $__dbReconGate->table_exists(['attendance', 'users', 'cashdrawers'])) {
                    $__attDate = $__now->format('Y-m-d');
                    $__attTime = $__workClose !== '' ? $__workClose : $__now->format('H:i:s');
                    $__dc = $__now->format('Y-m-d H:i:s');

                    // 1) Insert ABSENT for users with no cashdrawer today and no attendance row yet
                    $__dbReconGate->query(
                      "INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created)
                       SELECT :d, u.id, :t, 'ABSENT', 'system', 0, :dc
                       FROM users u
                       WHERE u.deleted = 0
                       AND NOT EXISTS (
                        SELECT 1 FROM cashdrawers c
                        WHERE c.user_id = u.id
                        AND DATE(c.date_created) = :d
                        AND c.deleted = 0
                       )
                       AND NOT EXISTS (
                        SELECT 1 FROM attendance a
                        WHERE a.user_id = u.id
                        AND a.date = :d
                        AND a.deleted = 0
                       )",
                      ['d' => $__attDate, 't' => $__attTime, 'dc' => $__dc]
                    );

                    // 2) Ensure existing non-PRESENT rows become ABSENT if they also have no cashdrawer today
                    $__dbReconGate->query(
                      "UPDATE attendance a
                       JOIN users u ON u.id = a.user_id
                       SET a.remarks = 'ABSENT', a.updated_by = 'system', a.date_updated = :dc
                       WHERE a.deleted = 0
                       AND a.date = :d
                       AND UPPER(COALESCE(a.remarks, '')) <> 'ABSENT'
                       AND NOT EXISTS (
                        SELECT 1 FROM cashdrawers c
                        WHERE c.user_id = u.id
                        AND DATE(c.date_created) = :d
                        AND c.deleted = 0
                       )",
                      ['d' => $__attDate, 'dc' => $__dc]
                    );
                  }
                } catch (\Throwable $e) {
                  // fail silently
                }

                $__start = $__now->format('Y-m-d 00:00:00');
                $__end = (clone $__now)->modify('+1 day')->format('Y-m-d 00:00:00');
                $__rows = $__dbReconGate->query(
                  "SELECT id, date_created FROM reconciliations_summary
                   WHERE deleted = 0
                   AND created_by = :uid
                   AND date_created >= :start
                   AND date_created < :end
                   AND LOWER(COALESCE(NULLIF(TRIM(transaction_status), ''), 'submitted')) NOT IN ('rejected', 'declined')
                   ORDER BY date_created DESC, id DESC
                   LIMIT 1",
                  ['uid' => (string)$__userId, 'start' => $__start, 'end' => $__end]
                ) ?: [];
                $__reconGateActive = empty($__rows);

                // If reconciliation exists but drawer activity happened after reconciliation, require reconciliation again.
                if (!$__reconGateActive && !empty($__rows) && $__dbReconGate->table_exists(['cashdrawers', 'drawer_balances'])) {
                  $__reconTs = (string)($__rows[0]->date_created ?? '');
                  if ($__reconTs !== '') {
                    try {
                      $__drawerRow = $__dbReconGate->query(
                        "SELECT id FROM cashdrawers WHERE user_id = :uid AND DATE(date_created) = :d ORDER BY id DESC LIMIT 1",
                        ['uid' => $__userId, 'd' => $__now->format('Y-m-d')]
                      ) ?: [];
                      $__drawerId = !empty($__drawerRow) ? (int)($__drawerRow[0]->id ?? 0) : 0;
                      if ($__drawerId > 0) {
                        $__lastActRows = $__dbReconGate->query(
                          "SELECT MAX(COALESCE(date_updated, date_created)) AS last_ts
                           FROM drawer_balances
                           WHERE drawer_id = :drawer_id AND user_id = :uid",
                          ['drawer_id' => $__drawerId, 'uid' => $__userId]
                        ) ?: [];
                        $__lastTs = (string)($__lastActRows[0]->last_ts ?? '');
                        if ($__lastTs !== '' && strtotime($__lastTs) !== false && strtotime($__reconTs) !== false) {
                          if (strtotime($__lastTs) > strtotime($__reconTs) + 3) {
                            $__reconGateActive = true;
                          }
                        }
                      }
                    } catch (\Throwable $e) {
                      // fail silently
                    }
                  }
                }
              }
            }
          }
        }
      } catch (\Throwable $e) {
        $__reconGateActive = false;
      }
    ?>
      <main id="mainBody" class="col-md-8 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">

        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a href="<?=ROOT?>/<?=URL(0)?>/<?=URL(1)?>">
                <?php if($section_title =='Dashboard') {
                    foreach($session->roles as $role)
                    echo(esc($role . "'s| "));
                  }?> 
                  <?=esc($section_title)?>
                </a>
            </li>
            <?php if (!empty($section_id)): ?>
              <li class="breadcrumb-item rounded-pill " aria-current="page"><?=esc($section_page ?? '')?></li>
              <li class="breadcrumb-item active rounded-pill px-1 py-0" aria-current="page"><?=esc($section_id ?? '')?></li>            
            <?php else: ?>
              <li class="breadcrumb-item active rounded-pill px-1 py-0" aria-current="page"><?=esc($section_page ?? '')?></li>
            <?php endif ?>
          </ol>
        </nav>
      </div>
        <?php if($__reconGateActive):?>
          <div id="reconGateBanner" class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 <?= $__reconIsReconPage ? '' : 'd-none' ?>" role="alert">
            <div class="fw-semibold">Reconciliation Required</div>
            <div class="flex-grow-1">Closing time has been reached. Please run reconciliation for today to continue.</div>
            <?php if(!$__reconIsReconPage):?>
              <button type="button" class="btn btn-sm btn-primary" id="reconGateProceedBtn">Proceed</button>
            <?php endif?>
          </div>
        <?php endif?>
        <?php $successMsg = message_success('', true); ?>
        <?php
          $whatsappStatus = null;
          try {
            $whatsappSession = new \Core\Session;
            $whatsappStatus = $whatsappSession->pop('backoffice_whatsapp_status');
          } catch (\Throwable $e) {
            $whatsappStatus = null;
          }
          $whatsappSent = (int)($whatsappStatus['sent'] ?? 0);
          $whatsappReady = (int)($whatsappStatus['ready'] ?? 0);
          $whatsappFailed = (int)($whatsappStatus['failed'] ?? 0);
          $whatsappSkipped = (int)($whatsappStatus['skipped'] ?? 0);
          $whatsappReasons = is_array($whatsappStatus['reasons'] ?? null) ? $whatsappStatus['reasons'] : [];
          $whatsappLinks = is_array($whatsappStatus['links'] ?? null) ? $whatsappStatus['links'] : [];
          $whatsappMessage = $whatsappReady > 0
            ? 'WhatsApp message ready for the assigned user. Open the link and press Send.'
            : 'No WhatsApp message could be prepared.';
          if ($whatsappFailed > 0 || $whatsappSkipped > 0) {
            $whatsappMessage .= ' ' . implode(' ', $whatsappReasons);
          }
        ?>
        <?php if(!empty($successMsg)): ?>
          <div id="js-message-success" class="d-none"><?=esc($successMsg)?></div>
        <?php endif; ?>
        <?php
          $failMsg = message_fail('', true);
          $isCashdrawerCreateRoute = ((URL(1) ?? '') === 'operations_accounts' && in_array((string)(URL(2) ?? ''), ['', 'add_cashdrawer'], true));
          if ($isCashdrawerCreateRoute) {
            $cashdrawerDeniedNeedles = [
              'Transaction denied',
              'Please approve or reject your pending transactions first:',
              'Process all pending transactions before reconciliation.',
              'Unable to validate pending transactions.',
            ];
            foreach ($cashdrawerDeniedNeedles as $needle) {
              if (stripos((string)$failMsg, $needle) !== false) {
                $failMsg = '';
                break;
              }
            }
          }
        ?>

        <?php if(!empty($failMsg) && strpos($failMsg, 'Transaction denied.') !== 0): ?>
          <div id="js-message-fail" class="d-none"><?=esc($failMsg)?></div>
        <?php endif; ?>

        <?php if(!empty($successMsg) || !empty($failMsg) || $whatsappReady > 0 || $whatsappFailed > 0 || $whatsappSkipped > 0): ?>
          <script>
            document.addEventListener('DOMContentLoaded', function(){
              var success = document.getElementById('js-message-success');
              var failure = document.getElementById('js-message-fail');
              var successText = success ? (success.textContent || '').trim() : '';
              var failureText = failure ? (failure.textContent || '').trim() : '';
              var whatsappSent = <?=json_encode($whatsappSent)?>;
              var whatsappReady = <?=json_encode($whatsappReady)?>;
              var whatsappFailed = <?=json_encode($whatsappFailed)?>;
              var whatsappSkipped = <?=json_encode($whatsappSkipped)?>;
              var whatsappText = <?=json_encode($whatsappMessage)?>;
              var whatsappLinks = <?=json_encode($whatsappLinks)?>;
              var escapeWhatsappHtml = function(value){
                return String(value || '').replace(/[&<>"']/g, function(character){
                  return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[character];
                });
              };
              function showMessage(icon, title, text) {
                if (!text) return;
                if (typeof window.basicAdminSwalAlert === 'function') {
                  window.basicAdminSwalAlert(title, text, icon);
                } else if (window.Swal && typeof window.Swal.fire === 'function') {
                  window.Swal.fire({ icon: icon, title: title, text: text, confirmButtonText: 'OK' });
                } else {
                  window.alert(text);
                }
              }
              showMessage('success', 'Success', successText);
              showMessage('error', 'Error', failureText);
              if (whatsappReady > 0 || whatsappFailed > 0 || whatsappSkipped > 0) {
                if (whatsappReady > 0 && window.Swal && typeof window.Swal.fire === 'function') {
                  var linksHtml = whatsappLinks.map(function(item, index){
                    return '<a class="btn btn-success btn-sm m-1" target="_blank" rel="noopener" href="' + item.url + '">Open WhatsApp</a>';
                  }).join('');
                  window.Swal.fire({ icon: 'info', title: 'WhatsApp message ready', html: '<p>' + escapeWhatsappHtml(whatsappText) + '</p><div>' + linksHtml + '</div>' });
                } else {
                  showMessage(whatsappFailed > 0 ? 'error' : 'warning', 'WhatsApp notification', whatsappText);
                }
              }
            });
          </script>
        <?php endif; ?>

        <?php if(!empty($failMsg)):

      $isTxDenied = (strpos($failMsg, 'Transaction denied.') === 0);

    ?>

      <?php if($isTxDenied):?>

        <div id="js-tx-denied-msg" class="d-none"><?=esc($failMsg)?></div>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>

          (function(){

            function showFallback(msg){

              try{ window.alert(msg); }catch(e){}

            }

            document.addEventListener('DOMContentLoaded', function(){

              var el = document.getElementById('js-tx-denied-msg');

              if(!el) return;

              var msg = (el.textContent || el.innerText || '').trim();

              if(!msg) return;

              if(typeof Swal === 'undefined' || !Swal || !Swal.fire){

                showFallback(msg);

                return;

              }

              var swalTheme = typeof window.basicAdminSwalTheme === 'function'
                ? window.basicAdminSwalTheme()
                : { background: '#ffffff', color: '#212529' };

              Swal.fire({

                icon: 'warning',

                title: 'Transaction denied',

                html: msg.replace(/^Transaction denied\.\s*/i,''),

                confirmButtonText: 'OK',

                allowOutsideClick: true,

                buttonsStyling: false,

                customClass: {

                  confirmButton: 'btn btn-primary',

                  popup: (function(){

                    try{

                      var theme = (document.documentElement && document.documentElement.getAttribute('data-bs-theme')) || '';

                      if(!theme && document.body){ theme = document.body.getAttribute('data-bs-theme') || ''; }

                      theme = String(theme || '').toLowerCase();

                      var isDark = (theme === 'dark');

                      return isDark ? 'bg-dark text-light' : 'bg-body text-body';

                    }catch(e){

                      return 'bg-body text-body';

                    }

                  })(),

                },

                background: swalTheme.background,
                color: swalTheme.color,

              });

            });

          })();

        </script>

      <?php else:?>
      <?php endif?>
        <?php endif?>

        <?php if (false) {
      $__reconGateActive = false;
      $__reconRedirectUrl = ROOT . '/admin/back_office/reconciliation';
      $__reconIsReconPage = ((URL(1) ?? '') === 'back_office' && (URL(2) ?? '') === 'reconciliation');
      $__idleTimeoutSeconds = 300;
      $__logoutUrl = ROOT . '/logout';
      $__userId = 0;

      try {
        $__dbSettings = new \Core\Database;
        if ($__dbSettings->table_exists(['settings'])) {
          $__settingsRow = ($__dbSettings->query("SELECT inactivity_limit_minutes FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
          $__mins = $__settingsRow ? (int)($__settingsRow->inactivity_limit_minutes ?? 0) : 0;
          if ($__mins > 0) {
            $__idleTimeoutSeconds = $__mins * 60;
          }
        }
      } catch (\Throwable $e) {
        $__idleTimeoutSeconds = 300;
      }

      try {
        $__session = get_session();
        $__userId = (int)($__session->id ?? 0);
        if ($__userId > 0) {
          $__dbReconGate = new \Core\Database;
          if ($__dbReconGate->table_exists(['settings', 'reconciliations_summary'])) {
            $__settings = ($__dbReconGate->query("SELECT work_close FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
            $__workClose = $__settings ? trim((string)($__settings->work_close ?? '')) : '';
            if ($__workClose !== '') {
              $__tz = new \DateTimeZone('Africa/Accra');
              $__now = new \DateTime('now', $__tz);
              $__closeToday = new \DateTime($__now->format('Y-m-d') . ' ' . $__workClose, $__tz);
              if ($__now >= $__closeToday) {
                try {
                  if (!$__reconIsReconPage && $__dbReconGate->table_exists(['attendance', 'users', 'cashdrawers'])) {
                    $__attDate = $__now->format('Y-m-d');
                    $__attTime = $__workClose !== '' ? $__workClose : $__now->format('H:i:s');
                    $__dc = $__now->format('Y-m-d H:i:s');

                    // 1) Insert ABSENT for users with no cashdrawer today and no attendance row yet
                    $__dbReconGate->query(
                      "INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created)
                       SELECT :d, u.id, :t, 'ABSENT', 'system', 0, :dc
                       FROM users u
                       WHERE u.deleted = 0
                       AND NOT EXISTS (
                        SELECT 1 FROM cashdrawers c
                        WHERE c.user_id = u.id AND DATE(c.date_created) = :d
                       )
                       AND NOT EXISTS (
                        SELECT 1 FROM attendance a
                        WHERE a.deleted = 0 AND a.user_id = u.id AND a.date = :d
                       )",
                      ['d' => $__attDate, 't' => $__attTime, 'dc' => $__dc]
                    );

                    // 2) Ensure existing non-PRESENT rows become ABSENT if they also have no cashdrawer today
                    $__dbReconGate->query(
                      "UPDATE attendance a
                       JOIN users u ON u.id = a.user_id
                       SET a.remarks = 'ABSENT', a.updated_by = 'system', a.date_updated = :dc
                       WHERE a.deleted = 0
                       AND a.date = :d
                       AND UPPER(COALESCE(a.remarks, '')) <> 'ABSENT'
                       AND u.deleted = 0
                       AND NOT EXISTS (
                        SELECT 1 FROM cashdrawers c
                        WHERE c.user_id = a.user_id AND DATE(c.date_created) = :d
                       )",
                      ['d' => $__attDate, 'dc' => $__dc]
                    );
                  }
                } catch (\Throwable $e) {
                  // fail silently; attendance automation should not block page load
                }

                $__start = $__now->format('Y-m-d 00:00:00');
                $__end = (clone $__now)->modify('+1 day')->format('Y-m-d 00:00:00');
                $__rows = $__dbReconGate->query(
                  "SELECT id, date_created FROM reconciliations_summary WHERE deleted = 0 AND created_by = :uid AND date_created >= :start AND date_created < :end ORDER BY date_created DESC, id DESC LIMIT 1",
                  ['uid' => (string)$__userId, 'start' => $__start, 'end' => $__end]
                ) ?: [];
                $__reconGateActive = empty($__rows);

                // If reconciliation exists but drawer activity happened after reconciliation, require reconciliation again.
                if (!$__reconGateActive && !empty($__rows) && $__dbReconGate->table_exists(['cashdrawers', 'drawer_balances'])) {
                  $__reconTs = (string)($__rows[0]->date_created ?? '');
                  if ($__reconTs !== '') {
                    try {
                      $__drawerRow = $__dbReconGate->query(
                        "SELECT id FROM cashdrawers WHERE user_id = :uid AND DATE(date_created) = :d ORDER BY id DESC LIMIT 1",
                        ['uid' => $__userId, 'd' => $__now->format('Y-m-d')]
                      ) ?: [];
                      $__drawerId = !empty($__drawerRow) ? (int)($__drawerRow[0]->id ?? 0) : 0;
                      if ($__drawerId > 0) {
                        $__lastActRows = $__dbReconGate->query(
                          "SELECT MAX(COALESCE(date_updated, date_created)) AS last_ts
                           FROM drawer_balances
                           WHERE drawer_id = :drawer_id AND user_id = :uid",
                          ['drawer_id' => $__drawerId, 'uid' => $__userId]
                        ) ?: [];
                        $__lastTs = (string)($__lastActRows[0]->last_ts ?? '');
                        if ($__lastTs !== '' && strtotime($__lastTs) !== false && strtotime($__reconTs) !== false) {
                          if (strtotime($__lastTs) > strtotime($__reconTs) + 3) {
                            $__reconGateActive = true;
                          }
                        }
                      }
                    } catch (\Throwable $e) {
                      // fail silently
                    }
                  }
                }
              }
            }
          }
        }
      } catch (\Throwable $e) {
        $__reconGateActive = false;
      }
    } ?>

    <script>
      (function(){
        var limitSeconds = <?= (int)$__idleTimeoutSeconds ?>;
        var logoutUrl = <?= json_encode($__logoutUrl) ?>;
        var reconGateActive = <?= $__reconGateActive ? 'true' : 'false' ?>;
        var reconRedirectUrl = <?= json_encode($__reconRedirectUrl) ?>;
        var isReconPage = <?= $__reconIsReconPage ? 'true' : 'false' ?>;
        var userId = <?= (int)($__userId ?? 0) ?>;
        var storageKey = 'recon_gate_autoshow_ack_' + String(<?= (int)($__userId ?? 0) ?>) + '_' + String(<?= json_encode(date('Y-m-d')) ?>);
        var idleStorageKey = 'idle_last_activity_' + String(userId || 0);
        var idleTickHandle = null;
        var idleLastActivityMs = 0;

        function nowMs(){ return Date.now ? Date.now() : (new Date()).getTime(); }
        function loadLastActivity(){
          try {
            var v = localStorage.getItem(idleStorageKey);
            var n = parseInt(v || '0', 10);
            if(!isNaN(n) && n > 0){
              // If the stored timestamp is already beyond the limit (e.g. after re-login),
              // reset it so the user doesn't get stuck in an immediate logout loop.
              var now = nowMs();
              var maxAgeMs = (limitSeconds && limitSeconds > 0) ? (limitSeconds * 1000) : 0;
              if(maxAgeMs > 0 && (now - n) >= maxAgeMs){
                idleLastActivityMs = now;
                try { localStorage.setItem(idleStorageKey, String(now)); } catch(e2) {}
                return;
              }
              idleLastActivityMs = n;
              return;
            }
          } catch(e) {}
          idleLastActivityMs = nowMs();
        }

        function saveLastActivity(ms){
          idleLastActivityMs = ms;
          try { localStorage.setItem(idleStorageKey, String(ms)); } catch(e) {}
        }
        function bumpActivity(){
          saveLastActivity(nowMs());
        }
        function formatMMSS(totalSeconds){
          var s = Math.max(0, Math.floor(totalSeconds));
          var m = Math.floor(s / 60);
          s = s % 60;
          var mm = (m < 10 ? '0' : '') + String(m);
          var ss = (s < 10 ? '0' : '') + String(s);
          return mm + ':' + ss;
        }
        function renderCountdown(){
          var el = document.getElementById('idleCountdown');
          if(!el) return;
          if(!limitSeconds || limitSeconds <= 0){
            el.textContent = '--:--';
            return;
          }
          var elapsed = (nowMs() - (idleLastActivityMs || 0)) / 1000;
          var remaining = Math.max(0, limitSeconds - elapsed);
          el.textContent = formatMMSS(remaining);
          if(remaining <= 0.5){
            if(reconGateActive && reconRedirectUrl){
              window.location.href = reconRedirectUrl;
            } else {
              window.location.href = logoutUrl;
            }
          }
        }
        function startIdleCountdown(){
          loadLastActivity();
          // Treat initial page load as activity (prevents logout loops after a fresh login)
          bumpActivity();
          renderCountdown();
          if(idleTickHandle){
            clearInterval(idleTickHandle);
            idleTickHandle = null;
          }

          idleTickHandle = setInterval(renderCountdown, 1000);
          var events = ['mousemove','mousedown','keydown','scroll','touchstart','click'];
          events.forEach(function(ev){
            window.addEventListener(ev, bumpActivity, {passive:true});
          });
          // also bump once on focus (switching tabs can otherwise expire unexpectedly)
          window.addEventListener('focus', bumpActivity);
        }
        function hasAck(){
          try { return localStorage.getItem(storageKey) === '1'; } catch(e) { return false; }
        }

        function setAck(){
          try { localStorage.setItem(storageKey, '1'); } catch(e) {}
        }

        function showModal(force){
          var bannerEl = document.getElementById('reconGateBanner');
          if(!bannerEl) return;
          if(!force && hasAck()) return;
          var btn = document.getElementById('reconGateProceedBtn');
          if(btn && !btn.__reconBound){
            btn.__reconBound = true;
            btn.addEventListener('click', function(){
              setAck();
              window.location.href = <?=json_encode($__reconRedirectUrl)?>;
            });
          }
          bannerEl.classList.remove('d-none');
          try {
            if(force && bannerEl.scrollIntoView){
              bannerEl.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
          } catch(e) {}
        }

        function interceptLogoutClicks(){
          var links = document.querySelectorAll('a[href]');
          if(!links || !links.length) return;
          links.forEach(function(a){
            var href = a.getAttribute('href') || '';
            if(!href) return;
            if(href === logoutUrl || href.endsWith('/logout')){
              a.addEventListener('click', function(e){
                if(reconGateActive){
                  var bannerEl = document.getElementById('reconGateBanner');
                  if(bannerEl){
                    e.preventDefault();
                    showModal(true);
                    return;
                  }
                }
                // Allow normal logout navigation when reconciliation gate is not active
              });
            }
          });
        }

        function bindReconSubmitAck(){
          if(!isReconPage) return;
          try {
            var forms = document.querySelectorAll('form');
            if(!forms || !forms.length) return;
            forms.forEach(function(f){
              f.addEventListener('submit', function(){
                setAck();
              });
            });
          } catch(e) {}
        }

        if(!isReconPage){
          if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', function(){ showModal(false); });
          }else{
            showModal(false);
          }
        }
        if(document.readyState === 'loading'){
          document.addEventListener('DOMContentLoaded', startIdleCountdown);
        }else{
          startIdleCountdown();
        }
        if(document.readyState === 'loading'){
          document.addEventListener('DOMContentLoaded', interceptLogoutClicks);
        }else{
          interceptLogoutClicks();
        }
        if(document.readyState === 'loading'){
          document.addEventListener('DOMContentLoaded', bindReconSubmitAck);
        }else{
          bindReconSubmitAck();
        }
      })();
    </script>

        <!-- Slide-in Report Panel -->
        <div id="drawerPanel" class="drawer-panel">
          <div class="panel-header">
            <h4 class="d-none">Cash Drawer - <span id="userName"></span></h4>
            <div class='text-center mt-3'>
              <button class='btn btn-secondary d-none' id='printReport' style="color:white !important;"><i class='fa fa-print'></i> Print</button>
              <button type="button" class="btn btn-outline-warning btn-sm mx-1" title="Denomination Exchange" onclick="openExchangeModal(getCurrentCashdrawerId())">
                <i class="fa fa-exchange-alt"></i> DE
              </button>
              <button type="button" class="btn btn-outline-success btn-sm mx-1" title="Channel Exchange" onclick="basicAdminOpenEcashExchange()">
                <i class="fa fa-right-left"></i> CE
              </button>
              <button type="button" class="btn btn-outline-danger btn-sm mx-1" title="PDF Export" onclick="exportCashdrawerPDF(getCurrentCashdrawerId())">
                <i class="fa fa-file-pdf"></i>
              </button>
              <button type="button" class="btn btn-outline-primary btn-sm mx-1" onclick="toggleDrawerDenoHistory()" title="Denomination History" aria-label="Denomination History">
                <i class="fa fa-history"></i>
              </button>
            <button type="button" class="btn btn-danger close-panel"><i class="fa fa-times"  style="color:white !important"></i></button>
            </div>
          </div>
          <div id="drawerContent" class="panel-body">
              <p class="text-center">Loading drawer...</p>
          </div>
        </div>

        <script>
          (function(){
            function clearStaleDenoDraft(){
              try{
                var denoField = document.querySelector('input[name="denos"], textarea[name="denos"], input#denos');
                var denoValue = denoField && typeof denoField.value === 'string' ? denoField.value.trim() : '';
                var hasServerSeed = denoValue !== '' && denoValue !== '[]';
                if(!hasServerSeed && window.localStorage){
                  localStorage.removeItem('denosData');
                }
              }catch(e){}
            }

            if(document.readyState === 'loading'){
              document.addEventListener('DOMContentLoaded', clearStaleDenoDraft);
            }else{
              clearStaleDenoDraft();
            }
          })();
        </script>

        <div>
          <?=do_action(plugin_id().'_main_content')?>
        </div>
      </main>
    </div>
  </div>
  <script src="<?=plugin_http_path('assets/js/bootstrap.bundle.min.js')?>"></script>
  <script src="<?=plugin_http_path('assets/js/dashboard.js')?>"></script>
  <!-- <script src="<?=plugin_http_path('assets/js/virtual-select.min.js')?>"></script> -->
<script src="<?=plugin_http_path('assets/js/select2.min.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/chart.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/jquery.dataTables.min.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/dataTables.bootstrap5.min.js')?>"></script>
<!-- Load Operations Account Plugin Scripts -->
<script src="<?=ROOT?>/plugins/operations_account/assets/js/plugin.js?v=20260706-1"></script>

  <script>
  window.tormorAccountAccessGuard = {
    statusUrl: <?=json_encode(ROOT . '/index.php?url=account-status')?>,
    loginUrl: <?=json_encode(ROOT . '/login')?>,
    intervalMs: 15000
  };
  </script>
  <script src="<?=ROOT?>/assets/js/account-access-guard.js?v=20260516-1"></script>

  <script>
  // Global Ops Chat (Ask Assistant) behaviour
  const CHAT_ME_ID   = <?= isset($admin->id) ? (int)$admin->id : 0 ?>;
  const CHAT_ME_NAME = <?= isset($username) ? json_encode($username) : '"User"' ?>;
  const MAX_CHAT_IMAGES = 5;

  (function () {
    const toggleBtn   = document.getElementById('aiAssistToggleBtn');
    const panel       = document.getElementById('aiAssistPanel');
    const closeBtn    = document.getElementById('aiAssistCloseBtn');
    const input       = document.getElementById('aiAssistInput');
    const sendBtn     = document.getElementById('aiAssistSendBtn');
    const messagesBox = document.getElementById('aiAssistMessages');
    const roomSelect  = document.getElementById('aiChatRoom');
    const imagesInput = document.getElementById('aiAssistImages');
    const attachBtn   = document.getElementById('aiAssistAttachBtn');
    const previewStrip = document.getElementById('aiAssistPreviewStrip');
    const viewerBackdrop = document.getElementById('aiAssistImageViewerBackdrop');
    const viewerImg      = document.getElementById('aiAssistImageViewerImg');
    const viewerCaption  = document.getElementById('aiAssistImageViewerCaption');
    const viewerDownload = document.getElementById('aiAssistImageViewerDownload');
    const viewerClose    = document.getElementById('aiAssistImageViewerClose');

    if (!toggleBtn || !panel || !closeBtn || !input || !sendBtn || !messagesBox || !roomSelect) {
      return;
    }

    let lastMessageId = 0;
    let selectedFiles = [];

    function openImageViewer(src, caption) {
      if (!viewerBackdrop || !viewerImg) return;
      viewerImg.src = src;
      viewerImg.alt = caption || '';
      if (viewerCaption) {
        viewerCaption.textContent = caption || '';
      }
      if (viewerDownload) {
        viewerDownload.href = src;
      }
      viewerBackdrop.classList.add('is-open');
      viewerBackdrop.setAttribute('aria-hidden', 'false');
    }

    function closeImageViewer() {
      if (!viewerBackdrop || !viewerImg) return;
      viewerBackdrop.classList.remove('is-open');
      viewerBackdrop.setAttribute('aria-hidden', 'true');
      viewerImg.src = '';
    }

    function togglePanel(forceOpen) {
      const shouldOpen = typeof forceOpen === 'boolean'
        ? forceOpen
        : !panel.classList.contains('is-open');

      if (shouldOpen) {
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        input.focus();
      } else {
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
      }
    }

    // expose for inline onclick fallback
    window.__toggleOpsChat = function () {
      togglePanel();
    };

    function appendMessage(obj, whoOverride) {
      const who = whoOverride || (obj.user_id === CHAT_ME_ID ? 'user' : 'assistant');
      const text = obj.message ?? obj;

      const wrapper = document.createElement('div');
      wrapper.className = 'ai-assist-message ' + (who === 'user'
        ? 'ai-assist-user'
        : 'ai-assist-assistant');

      const bubble = document.createElement('div');
      bubble.className = 'ai-assist-bubble';

      const imageList = [];
      if (obj.image_paths) {
        if (Array.isArray(obj.image_paths)) {
          obj.image_paths.forEach(function (p) { if (p) imageList.push(p); });
        } else if (typeof obj.image_paths === 'string') {
          try {
            const parsed = JSON.parse(obj.image_paths);
            if (Array.isArray(parsed)) {
              parsed.forEach(function (p) { if (p) imageList.push(p); });
            }
          } catch (e) {}
        }
      }
      if (!imageList.length && obj.image_path) {
        imageList.push(obj.image_path);
      }

      if (imageList.length) {
        const grid = document.createElement('div');
        grid.className = 'ai-assist-images-grid';
        imageList.forEach(function (path) {
          const fullSrc = '<?=ROOT?>/' + String(path).replace(/^\/+/, '');
          const img = document.createElement('img');
          img.src = fullSrc;
          img.alt = obj.message || 'attached image';
          img.style.cursor = 'pointer';
          img.addEventListener('click', function (e) {
            e.preventDefault();
            openImageViewer(fullSrc, obj.message || 'attached image');
          });
          grid.appendChild(img);
        });
        bubble.appendChild(grid);
      }

      if (text) {
        const span = document.createElement('div');
        span.textContent = text;
        bubble.appendChild(span);
      }

      if (obj.user_name) {
        const meta = document.createElement('div');
        meta.style.fontSize = '0.7rem';
        meta.style.opacity  = '0.7';
        meta.textContent    = (obj.user_name || 'User') + ' Â· ' + (obj.created_at || '');
        bubble.appendChild(document.createElement('br'));
        bubble.appendChild(meta);
      }

      wrapper.appendChild(bubble);
      messagesBox.appendChild(wrapper);
      messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function loadMessages(initial) {
      const room = roomSelect.value || 'global';
      const url  = new URL('<?=ROOT?>/admin/dashboard-chat/messages', window.location.origin);
      url.searchParams.set('room', room);
      if (!initial && lastMessageId > 0) {
        url.searchParams.set('since_id', String(lastMessageId));
      }

      fetch(url.toString())
        .then(r => r.json())
        .then(list => {
          if (!Array.isArray(list)) return;
          if (initial) {
            messagesBox.innerHTML = '';
          }
          list.forEach(m => {
            const obj = {
              user_id:     m.user_id,
              message:     m.message,
              image_path:  m.image_path || null,
              image_paths: m.image_paths || null,
              user_name:   (m.first_name || '') + ' ' + (m.last_name || ''),
              created_at:  m.created_at
            };
            appendMessage(obj);
            if (m.id && m.id > lastMessageId) lastMessageId = m.id;
          });

        })
        .catch(() => {});
    }

    function handleSend() {
      const value = input.value.trim();
      if (!value && (!selectedFiles || !selectedFiles.length)) return;
      const room = roomSelect.value || 'global';

      // Use FormData so we can send image + text
      const form = new FormData();
      form.append('room', room);
      form.append('message', value);
      if (selectedFiles && selectedFiles.length) {
        selectedFiles.forEach(function (file) {
          form.append('chat_images[]', file);
        });
      }

      fetch('<?=ROOT?>/admin/dashboard-chat/send?room=' + encodeURIComponent(room), {
        method: 'POST',
        body: form
      }).then(r => r.json()).then(res => {
        if (!res || res.error) return;
        input.value = '';
        selectedFiles = [];
        if (imagesInput) {
          imagesInput.value = '';
        }
        if (previewStrip) {
          previewStrip.innerHTML = '';
        }
      }).catch(() => {});
    }

    function renderPreviews() {
      if (!previewStrip) return;
      previewStrip.innerHTML = '';
      if (!selectedFiles || !selectedFiles.length) return;

      selectedFiles.forEach(function (file, index) {
        const thumb = document.createElement('div');
        thumb.className = 'ai-assist-preview-thumb';

        const img = document.createElement('img');
        const reader = new FileReader();
        reader.onload = function (e) {
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'ai-assist-preview-remove';
        removeBtn.textContent = 'Ã—';
        removeBtn.addEventListener('click', function () {
          selectedFiles.splice(index, 1);
          renderPreviews();
        });

        thumb.appendChild(img);
        thumb.appendChild(removeBtn);
        previewStrip.appendChild(thumb);
      });
    }

    if (attachBtn && imagesInput) {
      attachBtn.addEventListener('click', function () {
        imagesInput.click();
      });

      imagesInput.addEventListener('change', function (e) {
        const incoming = Array.from(e.target.files || []);
        if (!incoming.length) return;

        const remainingSlots = MAX_CHAT_IMAGES - selectedFiles.length;
        if (remainingSlots <= 0) {
          imagesInput.value = '';
          return;
        }

        const toAdd = incoming.slice(0, remainingSlots);
        selectedFiles = selectedFiles.concat(toAdd);
        imagesInput.value = '';
        renderPreviews();
      });
    }

    if (viewerBackdrop) {
      viewerBackdrop.addEventListener('click', function (e) {
        if (e.target === viewerBackdrop) {
          closeImageViewer();
        }
      });
    }

    if (viewerClose) {
      viewerClose.addEventListener('click', function () {
        closeImageViewer();
      });
    }

    toggleBtn.addEventListener('click', function () {
      togglePanel();
    });

    closeBtn.addEventListener('click', function () {
      togglePanel(false);
    });

    sendBtn.addEventListener('click', function () {
      handleSend();
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
      }
    });

    // when room changes, clear UI & reload only that room's history
    roomSelect.addEventListener('change', function () {
      lastMessageId = 0;
      messagesBox.innerHTML = '';
      loadMessages(true);
    });

    // initial load
    loadMessages(true);
    // simple polling for new messages in the currently selected room
    setInterval(function(){ loadMessages(false); }, 4000);
  })();

  document.addEventListener("DOMContentLoaded", function() {
    const navLinks = document.querySelectorAll('.nav-item .nav-link');
    const sidebar = document.getElementById('mySideBar');
    const mainBody = document.getElementById('mainBody');

  // Restore the state of expanded/collapsed dropdowns
    const dropdowns = document.querySelectorAll('[data-dropdown-id]');
    dropdowns.forEach(dropdown => {
      const dropdownId = dropdown.getAttribute('data-dropdown-id');
      const isExpanded = localStorage.getItem(dropdownId);

      if (isExpanded === 'true') {
        dropdown.classList.add('show');
        const toggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
      }

      dropdown.addEventListener('shown.bs.collapse', function () {
        localStorage.setItem(dropdownId, 'true');
        closeOtherCollapsedSubnavs(this);
        updateCollapsedSubnavPosition(this);
      });

      dropdown.addEventListener('hidden.bs.collapse', function () {
        localStorage.setItem(dropdownId, 'false');
      });
    });

    function updateCollapsedSubnavPosition(dropdown) {
      if (!sidebar.classList.contains('minimized')) return;
      const toggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
      if (!toggle) return;
      const rect = toggle.getBoundingClientRect();
      const headerHeight = document.querySelector('header.navbar')?.offsetHeight || 56;
      const top = Math.max(rect.top + window.scrollY, headerHeight + 10);
      const sidebarWidth = Math.round(sidebar.getBoundingClientRect().width);

      dropdown.style.top = `${top}px`;
      dropdown.style.left = `${sidebarWidth}px`;
      dropdown.style.width = '280px';
      dropdown.style.minWidth = '280px';
      dropdown.style.maxWidth = '320px';
    }

    function closeOtherCollapsedSubnavs(currentDropdown) {
      if (!sidebar.classList.contains('minimized')) return;
      document.querySelectorAll('.sidebar.minimized .subnav.show').forEach(dropdown => {
        if (dropdown !== currentDropdown) {
          const bsCollapse = bootstrap.Collapse.getInstance(dropdown);
          if (bsCollapse) {
            bsCollapse.hide();
          } else {
            new bootstrap.Collapse(dropdown, { toggle: false }).hide();
          }
        }
      });
    }

    document.querySelectorAll('[data-notification-badge]').forEach(badge => {
      if (badge.textContent.trim() === '') {
        badge.classList.add('d-none');
      }
    });

    document.addEventListener('scroll', function() {
      document.querySelectorAll('.sidebar.minimized .subnav.show').forEach(updateCollapsedSubnavPosition);
    }, true);

    window.addEventListener('resize', function() {
      document.querySelectorAll('.sidebar.minimized .subnav.show').forEach(updateCollapsedSubnavPosition);
    });

    function hideAppNameInCollapsedSidebar() {
      const sidebar = document.getElementById('mySideBar');
      const appName = document.querySelector('.app-name');
      const navbarBrand = document.querySelector('header.navbar .navbar-brand');
      const shouldHide = !!sidebar && sidebar.classList.contains('minimized') && window.innerWidth > 767;

      if (shouldHide) {
        appName?.classList.add('d-none');
        navbarBrand?.classList.add('is-logo-only');
      } else {
        appName?.classList.remove('d-none');
        navbarBrand?.classList.remove('is-logo-only');
      }
    }

    hideAppNameInCollapsedSidebar();
    window.addEventListener('resize', hideAppNameInCollapsedSidebar);

    // Enforce left-aligned, capped-width offcanvas on small/medium screens
    (function enforceOffcanvasMobileBehavior(){
      const offcanvas = document.getElementById('sidebarMenu');
      if(!offcanvas) return;

      function applyStyles(){
        if(window.innerWidth <= 767){
          offcanvas.style.left = '0';
          offcanvas.style.right = 'auto';
          offcanvas.style.width = '85vw';
          offcanvas.style.maxWidth = '420px';
          // keep it off-screen until shown
          if(!offcanvas.classList.contains('show')){
            offcanvas.style.transform = 'translateX(-105%)';
          }
        } else {
          offcanvas.style.left = '';
          offcanvas.style.right = '';
          offcanvas.style.width = '';
          offcanvas.style.maxWidth = '';
          offcanvas.style.transform = '';
        }
      }

      // When bootstrap shows/hides the offcanvas, ensure transform is correct
      offcanvas.addEventListener('show.bs.offcanvas', function(){
        if(window.innerWidth <= 767) offcanvas.style.transform = 'translateX(0)';
      });
      offcanvas.addEventListener('hidden.bs.offcanvas', function(){
        if(window.innerWidth <= 767) offcanvas.style.transform = 'translateX(-105%)';
      });

      window.addEventListener('resize', applyStyles);
      applyStyles();
    })();

    // Function to handle link click
    function handleNavLinkClick(event) {
      navLinks.forEach(link => link.classList.remove('active'));
      this.classList.add('active');
      localStorage.setItem('activeNavLink', this.parentElement.id);
    }

    // Function to toggle sidebar and store its state
    function toggleSidebar() {
      sidebar.classList.toggle('minimized');
      mainBody.classList.toggle('maximized');
      const isMinimized = sidebar.classList.contains('minimized');
      localStorage.setItem('sidebarMinimized', isMinimized);
      hideAppNameInCollapsedSidebar();
      document.querySelectorAll('.sidebar.minimized .subnav.show').forEach(updateCollapsedSubnavPosition);
    }

    // Add click event listeners to nav links
    navLinks.forEach(link => {
      link.addEventListener('click', handleNavLinkClick);
    });

    // Apply the active class to the nav link corresponding to the current page URL
    const currentPage = window.location.href;
    navLinks.forEach(link => {
      if (link.href === currentPage) {
        link.classList.add('active');
        localStorage.setItem('activeNavLink', link.parentElement.id);
      }
    });

    // Retrieve the stored active nav link from localStorage
    const activeNavLinkId = localStorage.getItem('activeNavLink');
    if (activeNavLinkId) {
      const activeNavLink = document.getElementById(activeNavLinkId).querySelector('.nav-link');
      if (activeNavLink) {
        activeNavLink.classList.add('active');
      }
    }

    // Retrieve the stored sidebar state from localStorage and apply it
    const isSidebarMinimized = localStorage.getItem('sidebarMinimized') === 'true';
    if (isSidebarMinimized) {
      sidebar.classList.add('minimized');
      mainBody.classList.add('maximized');
    }

    // Add click event listener to the minimize toggle button
    document.querySelector('.minimize-toggle').addEventListener('click', toggleSidebar);

    //handle cashdrawer
    let panel = document.getElementById("drawerPanel");
    let overlay = document.createElement("div");
    overlay.classList.add("panel-overlay");
    document.body.appendChild(overlay);

    let closePanel = document.querySelector(".close-panel");

    document.querySelectorAll('.view-drawer-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let userId = this.dataset.id;
            let drawerBody = document.getElementById('drawerContent');
            let userNameDiv = document.getElementById('userName');
            let userName = '';

            // Show loading text before fetching data
            drawerBody.innerHTML = "<p class='text-center'>Loading drawer...</p>";
            panel.style.bottom = "12px"; // Slide in from bottom
            panel.classList.add("drawer-open");
            overlay.style.display = "block"; // Show overlay

            let xhr = new XMLHttpRequest();

            // Always post to the admin root so basic-admin handles the request
            // even when we are on /admin/dashboard or any other subpage
            xhr.open("POST", "<?=ROOT?>/admin", true);

            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.setRequestHeader("Accept", "application/json");
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    //console.log(xhr.responseText);
                    try {
                        let response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            drawerBody.innerHTML = response.html;
                            userNameDiv.innerHTML = response.user_name;
                            userName = response.user_name;
            window.__cashdrawerCtx = {
              userId: parseInt(userId || '0'),
              drawerId: parseInt(response.drawer_id || document.querySelector('#drawerContent .reportDiv')?.getAttribute('data-drawer-id') || '0'),
              ecashKeys: Array.isArray(response.ecash_keys) ? response.ecash_keys : []
            };
                        } else {
                            drawerBody.innerHTML = `<p class="text-danger">${response.message}</p>`;
                        }
                    } catch (error) {
                        console.error("JSON Parse Error:", error);
            drawerBody.innerHTML = "<p class='text-danger'>Error loading cash drawer.</p>";
                    }
                }
            };
            let requestData = JSON.stringify({action: 'view-drawer', userId: userId});
            xhr.send(requestData);
        });
    });

  window.basicAdminLoadDrawer = function(uid){
    uid = parseInt(uid || '0');
    if(!uid) return;
    let drawerBody = document.getElementById('drawerContent');
    let userNameDiv = document.getElementById('userName');

    if (!drawerBody) return;
    let xhr = new XMLHttpRequest();
    xhr.open('POST', "<?=ROOT?>/admin", true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function() {
      if (xhr.readyState !== 4) return;
      try {
        let response = JSON.parse(xhr.responseText);
        if (response.success) {
          drawerBody.innerHTML = response.html;
          if (userNameDiv) userNameDiv.innerHTML = response.user_name;
          window.__cashdrawerCtx = {
            userId: uid,
            drawerId: parseInt(response.drawer_id || document.querySelector('#drawerContent .reportDiv')?.getAttribute('data-drawer-id') || '0'),
            ecashKeys: Array.isArray(response.ecash_keys) ? response.ecash_keys : []
          };
        } else {
          drawerBody.innerHTML = `<p class="text-danger">${response.message || 'Failed to load drawer.'}</p>`;
        }
      } catch (e) {
        drawerBody.innerHTML = "<p class='text-danger'>Error loading cash drawer.</p>";
      }
    };
    xhr.send(JSON.stringify({action: 'view-drawer', userId: uid}));
  };

  window.basicAdminSwalTheme = function(){
    const root = document.documentElement;
    const theme = String(root.getAttribute('data-bs-theme') || '').toLowerCase();
    const isDark = theme === 'dark';
    const styles = window.getComputedStyle(root);
    const cssColor = function(name, fallback){
      const value = styles.getPropertyValue(name).trim();
      return value || fallback;
    };
    return {
      background: cssColor('--bg-color', cssColor('--bs-body-bg', isDark ? '#212529' : '#ffffff')),
      color: cssColor('--text-color', cssColor('--bs-body-color', isDark ? '#f8f9fa' : '#212529')),
      confirmButtonColor: '#2563eb',
      cancelButtonColor: isDark ? '#6c757d' : '#6b7280'
    };
  };
  window.basicAdminSwalAlert = function(title, text, icon){
    if(!window.Swal) return alert(String(text || title || ''));
    const theme = window.basicAdminSwalTheme ? window.basicAdminSwalTheme() : {};
    return Swal.fire(Object.assign({
      title: title || '',
      text: text || '',
      icon: icon || 'info',
      didOpen: function(){
        try{
          const c = Swal.getContainer && Swal.getContainer();
          if(c) c.style.zIndex = '20000';
        }catch(e){}
      }
    }, theme));
  };
  window.basicAdminSwalConfirm = function(text, title){
    if(!window.Swal) return Promise.resolve(confirm(String(text || 'Are you sure?')));
    const theme = window.basicAdminSwalTheme ? window.basicAdminSwalTheme() : {};
    return Swal.fire(Object.assign({
      title: title || 'Confirm',
      text: text || 'Are you sure?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'Cancel',
      didOpen: function(){
        try{
          const c = Swal.getContainer && Swal.getContainer();
          if(c) c.style.zIndex = '20000';
        }catch(e){}
      }
    }, theme)).then(function(r){
      return !!(r && r.isConfirmed);
    });
  };

  (function(){
    if (window.basicAdminSubmitProgress) return;

    const progressSelectors = [
      '[data-admin-progress="manual"]',
      '[data-submit-progress="off"]',
      '[data-submit-progress="manual"]',
      '[data-progress-owner]',
      '[data-loading-indicator]',
      '.submit-progress',
      '.js-submit-progress',
      '.loading-indicator',
      '.spinner-border',
      '.spinner-grow',
      '.progress',
      'progress',
      '[role="progressbar"]',
      '[aria-busy="true"]'
    ].join(',');

    let root = null;
    let bar = null;
    let labelText = null;
    let activeButton = null;
    let activeButtonSnapshot = null;
    let percent = 0;
    let timer = null;
    let submitWatchdog = null;
    let clickWatchdog = null;
    let completeWatchdog = null;
    let lastSubmitter = null;
    let startedFromSubmit = false;

    function ensureProgressNode(){
      if (root) return;

      root = document.createElement('div');
      root.className = 'admin-submit-progress';
      root.setAttribute('aria-hidden', 'true');
      root.innerHTML = '<div class="admin-submit-progress__bar"></div><div class="admin-submit-progress__label"><span class="admin-submit-progress__spinner"></span><span class="admin-submit-progress__label-text">Processing 0%</span></div>';
      document.body.appendChild(root);
      bar = root.querySelector('.admin-submit-progress__bar');
      labelText = root.querySelector('.admin-submit-progress__label-text');
    }

    function submitterFromEventTarget(target){
      if (!target || !target.closest) return null;
      const candidate = target.closest('button, input[type="submit"], input[type="image"]');
      if (!candidate) return null;

      if (candidate.tagName === 'BUTTON') {
        const type = String(candidate.getAttribute('type') || 'submit').toLowerCase();
        if (type !== 'submit') return null;
      }

      return candidate;
    }

    function buttonForm(button){
      if (!button) return null;
      if (button.form) return button.form;
      return button.closest ? button.closest('form') : null;
    }

    function hasOwnProgress(form, button){
      if (button && button.closest && button.closest('[data-admin-progress="manual"],[data-submit-progress="off"],[data-submit-progress="manual"],[data-progress-owner]')) {
        return true;
      }

      if (form && form.matches && form.matches('[data-admin-progress="manual"],[data-submit-progress="off"],[data-submit-progress="manual"],[data-progress-owner]')) {
        return true;
      }

      if (button && button.querySelector && button.querySelector(progressSelectors)) {
        return true;
      }

      if (form && form.querySelector && form.querySelector(progressSelectors)) {
        return true;
      }

      return false;
    }

    function formCanSubmit(form){
      if (!form || form.noValidate || typeof form.checkValidity !== 'function') {
        return true;
      }
      return form.checkValidity();
    }

    function setPercent(nextPercent){
      percent = Math.max(percent, Math.min(95, nextPercent));
      if (bar) bar.style.width = percent + '%';
      if (labelText) labelText.textContent = 'Processing ' + percent + '%';

      if (activeButton && activeButtonSnapshot) {
        const buttonLabel = 'Processing ' + percent + '%';
        if (activeButton.tagName === 'INPUT') {
          activeButton.value = buttonLabel;
        } else {
          activeButton.innerHTML = '<span class="admin-submit-button-spinner"></span>' + buttonLabel;
        }
      }
    }

    function tick(){
      const step = percent < 35 ? 11 : (percent < 70 ? 7 : 3);
      setPercent(percent + step);
    }

    function snapshotButton(button){
      if (!button || activeButtonSnapshot) return;
      activeButton = button;
      activeButtonSnapshot = {
        html: button.innerHTML,
        value: button.value,
        width: button.style.width,
        disabled: button.disabled
      };

      const rect = button.getBoundingClientRect ? button.getBoundingClientRect() : null;
      if (rect && rect.width > 0) {
        button.style.width = Math.ceil(rect.width) + 'px';
      }
      button.classList.add('admin-submit-busy');
      button.setAttribute('aria-busy', 'true');
    }

    function start(button, source){
      const form = buttonForm(button);
      if (hasOwnProgress(form, button) || !formCanSubmit(form)) {
        return false;
      }

      ensureProgressNode();
      snapshotButton(button);
      startedFromSubmit = source === 'submit';
      percent = 0;
      root.classList.add('is-active');
      setPercent(source === 'submit' ? 18 : 8);

      clearInterval(timer);
      clearTimeout(submitWatchdog);
      clearTimeout(clickWatchdog);
      timer = setInterval(tick, 420);

      if (source === 'click') {
        clickWatchdog = window.setTimeout(function(){
          if (!startedFromSubmit) {
            stop();
          }
        }, 1400);
      }

      return true;
    }

    function complete(){
      if (!root || !root.classList.contains('is-active')) return;
      clearInterval(timer);
      clearTimeout(submitWatchdog);
      clearTimeout(clickWatchdog);
      clearTimeout(completeWatchdog);
      timer = null;
      submitWatchdog = null;
      clickWatchdog = null;
      completeWatchdog = null;
      setPercent(100);
      completeWatchdog = window.setTimeout(function(){
        if (document.visibilityState !== 'hidden') {
          stop();
        }
      }, 1200);
    }

    function stop(){
      clearInterval(timer);
      clearTimeout(submitWatchdog);
      clearTimeout(clickWatchdog);
      clearTimeout(completeWatchdog);
      timer = null;
      submitWatchdog = null;
      clickWatchdog = null;
      completeWatchdog = null;
      if (root) {
        root.classList.remove('is-active');
        if (bar) bar.style.width = '0%';
      }

      if (activeButton && activeButtonSnapshot) {
        activeButton.classList.remove('admin-submit-busy');
        activeButton.removeAttribute('aria-busy');
        activeButton.style.width = activeButtonSnapshot.width;
        activeButton.disabled = activeButtonSnapshot.disabled;
        if (activeButton.tagName === 'INPUT') {
          activeButton.value = activeButtonSnapshot.value;
        } else {
          activeButton.innerHTML = activeButtonSnapshot.html;
        }
      }

      activeButton = null;
      activeButtonSnapshot = null;
      percent = 0;
      startedFromSubmit = false;
    }

    document.addEventListener('click', function(event){
      const button = submitterFromEventTarget(event.target);
      if (!button) return;

      lastSubmitter = button;
      start(button, 'click');

      window.setTimeout(function(){
        if (event.defaultPrevented) {
          stop();
          return;
        }

        if (!startedFromSubmit && document.body && document.body.contains(button)) {
          const form = buttonForm(button);
          if (form && !form.matches(':valid')) {
            stop();
          }
        }
      }, 650);
    }, true);

    document.addEventListener('submit', function(event){
      const button = event.submitter || lastSubmitter || (event.target && event.target.querySelector ? event.target.querySelector('button[type="submit"], input[type="submit"], button:not([type])') : null);
      window.setTimeout(function(){
        if (event.defaultPrevented) {
          stop();
          return;
        }

        clearTimeout(clickWatchdog);
        clickWatchdog = null;

        if (button && (!root || !root.classList.contains('is-active'))) {
          start(button, 'submit');
        } else {
          startedFromSubmit = true;
          setPercent(Math.max(percent, 18));
        }

        if (activeButton) {
          activeButton.disabled = true;
        }

        clearTimeout(submitWatchdog);
        submitWatchdog = window.setTimeout(function(){
          if (document.visibilityState !== 'hidden') {
            stop();
          }
        }, 30000);
      }, 0);
    }, true);

    window.addEventListener('beforeunload', complete);
    window.addEventListener('pagehide', complete);
    window.addEventListener('pageshow', stop);

    window.basicAdminSubmitProgress = {
      start: start,
      stop: stop,
      complete: complete
    };
  })();

  window.basicAdminNormalizeEcashChannels = function(){
    const ctx = window.__cashdrawerCtx || {};
    const drawerId = parseInt(ctx.drawerId || '0');
    const userId = parseInt(ctx.userId || '0');
    if(!drawerId || !userId){
      window.basicAdminSwalAlert('Error', 'Open a cashdrawer first.');
      return;
    }
    window.basicAdminSwalConfirm('Normalize bank e-cash channels for this drawer? This will merge duplicates and cannot be undone.', 'Normalize channels').then(function(ok){
      if(!ok) return;
      let xhr = new XMLHttpRequest();
      xhr.open('POST', "<?=ROOT?>/admin", true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onreadystatechange = function(){
        if(xhr.readyState !== 4) return;
        try{
          const res = JSON.parse(xhr.responseText);
          if(res && res.success){
            let msg = res.message || 'Normalization complete.';
            if(res.groups_processed){
              msg += `\nGroups processed: ${res.groups_processed}`;
            }
            if(res.total_deleted){
              msg += `\nDuplicates deleted: ${res.total_deleted}`;
            }
            window.basicAdminSwalAlert('Done', msg, 'success');
            if(typeof window.basicAdminLoadDrawer === 'function'){
              window.basicAdminLoadDrawer(userId);
            }
          }else{
            window.basicAdminSwalAlert('Error', (res && res.message) ? res.message : 'Normalization failed', 'error');
          }
        }catch(e){
          window.basicAdminSwalAlert('Error', 'Normalization failed', 'error');
        }
      };
      xhr.send(JSON.stringify({
        action: 'normalize-ecash-channels',
        drawerId: drawerId
      }));
    });
  };

  window.basicAdminChannelExchangePolicy = (function(){
    const bankAliases = {
      ADB: 'ADB', ADB_BANK: 'ADB',
      ABSA: 'ABSA', ABSA_BANK: 'ABSA', ABSA_FORMERLY_BARCLAYS: 'ABSA', ABSA_FORMERLY_BARCLAYS_BANK: 'ABSA', BARCLAYS: 'ABSA', BARCLAYS_BANK: 'ABSA',
      FIDELITY: 'FIDELITY', FIDELITY_BANK: 'FIDELITY',
      CAL: 'CAL', CAL_BANK: 'CAL',
      ACCESS: 'ACCESS', ACCESS_BANK: 'ACCESS',
      ECOBANK: 'ECOBANK', ECOBANK_BANK: 'ECOBANK',
      GCB: 'GCB', GCB_BANK: 'GCB',
      GT: 'GTBANK', GT_BANK: 'GTBANK', GTBANK: 'GTBANK', GTBANK_BANK: 'GTBANK',
      STANBIC: 'STANBIC', STANBIC_BANK: 'STANBIC',
      FNB: 'FNB', FNB_BANK: 'FNB', FIRST_NATIONAL_BANK: 'FNB', FIRST_NATIONAL_BANK_FNB: 'FNB',
      FBN: 'FBN', FBN_BANK: 'FBN', FIRST_BANK_NIGERIA: 'FBN',
      FBG: 'FBG', FBG_BANK: 'FBG', FIRST_BANK_GHANA: 'FBG', FIRST_BANK_GHANA_FBG: 'FBG',
      APEX_NAFANA: 'APEX_NAFANA', APEX_NAFANA_BANK: 'APEX_NAFANA', NAFANA_RURAL_BANK: 'APEX_NAFANA', NAFANA_BANK: 'APEX_NAFANA',
      APEX_SUMA: 'APEX_SUMA', APEX_SUMA_BANK: 'APEX_SUMA', SUMA_RURAL_BANK: 'APEX_SUMA', SUMA_BANK: 'APEX_SUMA'
    };
    const bankChannels = ['ADB', 'ABSA', 'FIDELITY', 'CAL', 'ACCESS', 'ECOBANK', 'GCB', 'GTBANK', 'STANBIC', 'FNB', 'FBN', 'FBG', 'APEX_NAFANA', 'APEX_SUMA'];
    function canonical(key){
      let value = String(key || '').trim().toUpperCase().replace(/^ECASH[_-]*/, '');
      value = value.replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
      if(['MTNMOMOPAY', 'MTNMOMOPAYOUT'].includes(value.replace(/[^A-Z0-9]/g, ''))) return 'MTN_MOMOPAY';
      const aliases = {
        MTN: 'MTN', MTN_EVD: 'MTN_EVD',
        TELECEL: 'TELECEL', TELECEL_EVD: 'TELECEL_EVD',
        AT: 'AT', AT_EVD: 'AT_EVD', AIRTELTIGO: 'AT', AIRTEL_TIGO: 'AT',
        GHANAPAY: 'GHANAPAY', GHANA_PAY: 'GHANAPAY'
      };
      return aliases[value] || bankAliases[value] || value;
    }
    function isBank(key){
      return bankChannels.includes(canonical(key));
    }
    function isAdminOnlyExchange(fromKey, toKey){
      const from = canonical(fromKey);
      const to = canonical(toKey);
      if(from === 'GHANAPAY') return isBank(toKey);
      if(from === 'GCB') return isBank(toKey);
      if(from === 'MTN') return ['ABSA', 'FIDELITY', 'CAL', 'ACCESS', 'STANBIC', 'GCB', 'FNB', 'FBN', 'GTBANK', 'MTN_EVD'].includes(to);
      if(from === 'TELECEL') return ['ABSA', 'FIDELITY'].includes(to);
      if(from === 'FIDELITY') return ['TELECEL', 'MTN', 'AT'].includes(to);
      if(from === 'AT') return to === 'FIDELITY';
      if(from === 'STANBIC') return ['GHANAPAY', 'MTN', 'TELECEL'].includes(to);
      if(from === 'APEX_NAFANA' || from === 'APEX_SUMA') return to === 'GHANAPAY';
      return false;
    }
    function allows(fromKey, toKey, isAdmin){
      const from = canonical(fromKey);
      const to = canonical(toKey);
      if(!from || !to || from === to) return false;
      if(from === 'TELECEL' && to === 'TELECEL_EVD') return false;
      return !!isAdmin || !isAdminOnlyExchange(fromKey, toKey);
    }
    return {canonical, isBank, isAdminOnlyExchange, allows};
  })();

  window.basicAdminOpenEcashExchange = function(){
    const ctx = window.__cashdrawerCtx || {};
    const drawerId = parseInt(ctx.drawerId || '0');
    const userId = parseInt(ctx.userId || '0');
    const isAdmin = <?= user_can('is_admin') ? 'true' : 'false' ?>;

    const storedKeys = Array.isArray(ctx.ecashKeys) ? ctx.ecashKeys.slice() : [];
    const standardNetworkKeys = [
      'ECASH_MTN',
      'ECASH_TELECEL',
      'ECASH_AT',
      'ECASH_MTN_EVD',
      'ECASH_TELECEL_EVD',
      'ECASH_AT_EVD'
    ];
    function uniqueChannelKeys(channelKeys){
      const keysByNormalizedName = new Map();
      channelKeys.forEach(function(key){
        const cleanKey = String(key || '').trim();
        const normalized = window.basicAdminChannelExchangePolicy.canonical(cleanKey);
        if(!cleanKey || !normalized) return;
        if(!keysByNormalizedName.has(normalized)){
          keysByNormalizedName.set(normalized, cleanKey);
        }
      });
      return Array.from(keysByNormalizedName.values());
    }
    const sourceKeys = uniqueChannelKeys(storedKeys);
    const destinationKeys = uniqueChannelKeys(storedKeys.concat(standardNetworkKeys));
    if(!drawerId || !userId){
      window.basicAdminSwalAlert('Error', 'Open a cashdrawer first.', 'error');
      return;
    }
    function buildToOptions(fromKey){
      return destinationKeys.filter(function(key){
        return window.basicAdminChannelExchangePolicy.allows(fromKey, key, isAdmin);
      });
    }

    const fromSel = document.getElementById('ecashFromChannel');
    const toSel = document.getElementById('ecashToChannel');
    const amtEl = document.getElementById('ecashExchangeAmount');
    function prettyChannelLabel(key){
      const canonical = window.basicAdminChannelExchangePolicy.canonical(key);
      const labels = {
        APEX_NAFANA: 'Nafana Rural Bank (Apex)',
        APEX_SUMA: 'Suma Rural Bank (Apex)',
        GHANAPAY: 'GhanaPay',
        MTN_MOMOPAY: 'MTN MoMoPay',
        MTN_EVD: 'MTN EVD',
        GTBANK: 'GTBank',
        FNB: 'FNB',
        FBN: 'FBN'
      };
      return labels[canonical] || canonical.replace(/_/g, ' ');
    }
    function populateOptions(select, keys, placeholder){
      if(!select) return;
      select.replaceChildren();
      const option = document.createElement('option');
      option.value = '';
      option.textContent = placeholder;
      select.appendChild(option);
      keys.forEach(function(key){
        const item = document.createElement('option');
        item.value = key;
        item.textContent = prettyChannelLabel(key);
        select.appendChild(item);
      });
    }
    if(fromSel){
      populateOptions(fromSel, sourceKeys, 'Select source');
    }
    function renderTo(fromKey){
      if(!toSel) return;
      populateOptions(toSel, buildToOptions(fromKey), 'Select destination');
    }
    if(toSel){
      renderTo(fromSel ? fromSel.value : '');
    }
    if(fromSel && !fromSel.dataset.ecashExchangeWired){
      fromSel.dataset.ecashExchangeWired = '1';
      fromSel.addEventListener('change', function(){
        renderTo(fromSel.value);
      });
    }
    if(amtEl){
      amtEl.value = '';
    }
    document.getElementById('ecashExchangeDrawerId').value = String(drawerId);
    document.getElementById('ecashExchangeUserId').value = String(userId);

    const modalEl = document.getElementById('ecashExchangeModal');
    if(window.bootstrap && modalEl){
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  };

  window.toggleDrawerDenoHistory = function() {
    const wrap = document.querySelector('#drawerContent #drawerDenoHistoryWrapper');
    if (!wrap) return;
    const current = (wrap.style.display || '').toLowerCase();
    wrap.style.display = current === 'none' ? '' : 'none';
  };

    // âœ… Close Panel When Clicking the Close Button
    closePanel.addEventListener("click", function() {
        panel.classList.remove("drawer-open");
        panel.style.bottom = "-100%"; // Slide out
        overlay.style.display = "none"; // Hide overlay
    });

    // âœ… Close Panel When Clicking Outside It
    overlay.addEventListener("click", function() {
        panel.classList.remove("drawer-open");
        panel.style.bottom = "-100%"; // Slide out
        overlay.style.display = "none"; // Hide overlay
    });

    // âœ… Close Panel When Clicking the Close Button
  });

</script>

<!-- E-cash Channel Exchange Modal -->
<div class="modal fade" id="ecashExchangeModal" tabindex="-1" aria-labelledby="ecashExchangeLabel" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="ecashExchangeLabel">
          <i class="fa fa-right-left me-2"></i> Channel Exchange
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ecashExchangeDrawerId" value="">
        <input type="hidden" id="ecashExchangeUserId" value="">
        <div class="d-none"><?= csrf() ?></div>
        <div class="mb-3">
          <label class="form-label">From channel</label>
          <select class="form-select" id="ecashFromChannel"></select>
        </div>
        <div class="mb-3">
          <label class="form-label">To channel</label>
          <select class="form-select" id="ecashToChannel"></select>
        </div>
        <div class="mb-3">
          <label class="form-label">Amount (GHS)</label>
          <input type="number" class="form-control" id="ecashExchangeAmount" step="0.01" min="0.01" />
        </div>
        <div class="small text-muted">Allowed destinations depend on the source channel. Some routes are Admin-only. Applicable exchanges include a 1% fee capped at GHS 10.00; the exact fee and total debit will be shown before confirmation.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="ecashExchangeSubmit">
          <i class="fa fa-check"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const btn = document.getElementById('ecashExchangeSubmit');
  if(!btn) return;
  const originalLabel = btn.innerHTML;
  function setProcessing(isProcessing){
    btn.disabled = isProcessing;
    btn.innerHTML = isProcessing ? '<span class="spinner-border spinner-border-sm me-1"></span> Processing' : originalLabel;
  }
  function sendExchange(drawerId, userId, fromKey, toKey, amount, confirmFee){
    setProcessing(true);
    const xhr = new XMLHttpRequest();
    let completed = false;
    xhr.open('POST', "<?=ROOT?>/admin", true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4 || completed) return;
      completed = true;
      setProcessing(false);
      let res;
      try{
        res = JSON.parse(xhr.responseText);
      }catch(e){
        window.basicAdminSwalAlert('Error', 'Exchange failed. Please try again.', 'error');
        return;
      }
      if(res.confirmation_required){
        window.basicAdminSwalConfirm(res.message || 'A fee applies to this exchange. Continue?', 'Confirm channel exchange').then(function(confirmed){
          if(confirmed){
            sendExchange(drawerId, userId, fromKey, toKey, amount, true);
          }
        });
        return;
      }
      if(!res.success){
        window.basicAdminSwalAlert('Error', res.message || 'Exchange failed.', 'error');
        return;
      }
      window.basicAdminSwalAlert('Success', res.message || 'Exchange completed.', 'success').then(function(){
        const modalEl = document.getElementById('ecashExchangeModal');
        if(window.bootstrap && modalEl){
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        if(typeof window.basicAdminLoadDrawer === 'function'){
          window.basicAdminLoadDrawer(userId);
        }
      });
    };
    xhr.onerror = function(){
      if(completed) return;
      completed = true;
      setProcessing(false);
      window.basicAdminSwalAlert('Error', 'Exchange failed. Please try again.', 'error');
    };
    const csrfInput = document.querySelector('#ecashExchangeModal input[name="csrf"]');
    xhr.send(JSON.stringify({
      action: 'exchange-ecash',
      drawerId: drawerId,
      from_channel: fromKey,
      to_channel: toKey,
      amount: amount,
      csrf: csrfInput ? csrfInput.value : '',
      confirm_fee: confirmFee,
      trans_status: 'Exchange'
    }));
  }
  btn.addEventListener('click', function(){
    const drawerId = parseInt(document.getElementById('ecashExchangeDrawerId').value || '0');
    const userId = parseInt(document.getElementById('ecashExchangeUserId').value || '0');
    const fromKey = String(document.getElementById('ecashFromChannel').value || '').trim();
    const toKey = String(document.getElementById('ecashToChannel').value || '').trim();
    const amount = parseFloat(document.getElementById('ecashExchangeAmount').value || '0');
    if(!drawerId || !userId || !fromKey || !toKey || !amount || amount <= 0){
      window.basicAdminSwalAlert('Error', 'Fill From, To, and Amount.', 'error');
      return;
    }
    sendExchange(drawerId, userId, fromKey, toKey, amount, false);
  });
});
</script>

<!-- Exchange Denominations Modal -->
<div class="modal fade" id="exchangeDenoModal" tabindex="-1" aria-labelledby="exchangeDenoLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="exchangeDenoLabel">
                    <i class="fa fa-exchange-alt me-2"></i> Exchange Denominations
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="exchangeForm" enctype="application/x-www-form-urlencoded" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="drawer_id" id="drawerIdInput">
                    <input type="hidden" name="csrf" id="csrfToken">
                    <div class="mb-3">
                        <label class="form-label fw-bold small mb-1" for="exchangeCurrency">Currency</label>
                        <select class="form-select form-select-sm" name="currency" id="exchangeCurrency" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                            <option value="GHS">GHS cash</option>
                            <option value="CFA">CFA cash</option>
                        </select>
                    </div>
                    
                    <!-- Denominations Received (First) -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label fw-bold small mb-0">ðŸ’° Denominations Received</label>
                            <small class="text-muted ms-auto">What you're receiving</small>
                        </div>
                        <div id="receivedInputs" class="border rounded p-2" style="max-height: 140px; overflow-y: auto; background-color: var(--bs-body-bg);">
                            <div class="received-row mb-2">
                                <div class="row g-1 align-items-end">
                                    <div class="col-5">
                                        <!-- <input type="number" class="form-control form-control-sm received-value" 
                                               placeholder="Amount (GHS)" step="0.01" min="0.01" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);"> -->
                                        <select class="form-select form-control-sm received-value"
                                                 style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                                          <option value="">---Amount (GHS)---</option>
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <input type="number" class="form-control form-control-sm received-count" 
                                               placeholder="Count" step="1" min="1" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                                    </div>
                                    <div class="col-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-received" style="display: none; padding: 0.25rem 0.5rem;">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <small class="text-muted">Total Received: <strong id="receivedTotal" class="text-success">GHS 0.00</strong></small>
                            <button type="button" class="btn btn-sm ms-auto" id="addReceivedBtn" style="background-color: #28a745; color: white; border: none; padding: 0.35rem 0.75rem;">
                                <i class="fa fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>

                    <!-- Denominations Given Out (Second) -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label fw-bold small mb-0">ðŸ’¸ Denominations Given Out</label>
                            <small class="text-muted ms-auto">What you're giving away</small>
                        </div>
                        <div id="availableDenosInfo" class="alert alert-info py-2 px-3 mb-2" style="font-size: 0.85rem; display: none;">
                            <strong>Available Denominations:</strong> <span id="availableDenosText"></span>
                        </div>
                        <div id="givenOutInputs" class="border rounded p-2" style="max-height: 140px; overflow-y: auto; background-color: var(--bs-body-bg);">
                            <div class="given-out-row mb-2">
                                <div class="row g-1 align-items-end">
                                    <div class="col-5">
                                      <!-- <input type="number" class="form-control form-control-sm given-value" 
                                            placeholder="Amount (GHS)" step="0.01" min="0.01" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);"> -->
                                      <select class="form-select form-control-sm given-value"
                                                 style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                                        <option value="">---Amount (GHS)---</option>
                                      </select>
                                    </div>
                                    <div class="col-5">
                                        <input type="number" class="form-control form-control-sm given-count" 
                                               placeholder="Count" step="1" min="1" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                                    </div>
                                    <div class="col-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-given" style="display: none; padding: 0.25rem 0.5rem;">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <small class="text-muted">Total Given: <strong id="givenOutTotal" class="text-danger">GHS 0.00</strong></small>
                            <button type="button" class="btn btn-sm ms-auto" id="addGivenBtn" style="background-color: #dc3545; color: white; border: none; padding: 0.35rem 0.75rem;">
                                <i class="fa fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>

                    <!-- Balance Check -->
                    <div class="alert py-2 px-3 mb-3" id="balanceAlert" role="alert" style="font-size: 0.9rem;">
                        <i class="fa fa-info-circle me-2"></i>
                        <span id="balanceStatus">Enter denominations to check balance</span>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="submitExchange" disabled>
                        <i class="fa fa-check me-1"></i> Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function initExchangeModal() {
        const givenOutInputs = document.getElementById('givenOutInputs');
        const receivedInputs = document.getElementById('receivedInputs');
        const addGivenBtn = document.getElementById('addGivenBtn');
        const addReceivedBtn = document.getElementById('addReceivedBtn');
        const givenOutTotal = document.getElementById('givenOutTotal');
        const receivedTotal = document.getElementById('receivedTotal');
        const balanceAlert = document.getElementById('balanceAlert');
        const balanceStatus = document.getElementById('balanceStatus');
        const exchangeForm = document.getElementById('exchangeForm');
        const submitExchange = document.getElementById('submitExchange');
        const drawerIdInput = document.getElementById('drawerIdInput');
        const exchangeCurrency = document.getElementById('exchangeCurrency');
        const denominationCatalog = {
            GHS: [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1],
            CFA: [10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10]
        };

        if (!givenOutInputs || !receivedInputs) return;

        function currentCurrency() {
            const value = (exchangeCurrency && exchangeCurrency.value ? exchangeCurrency.value : 'GHS').toUpperCase();
            return value === 'CFA' ? 'CFA' : 'GHS';
        }

        function formatMoney(amount) {
            const currency = currentCurrency();
            const decimals = currency === 'CFA' ? 0 : 2;
            return currency + ' ' + Number(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function formatDenomination(value) {
            const currency = currentCurrency();
            const decimals = currency === 'CFA' || Number(value) >= 1 ? 0 : 2;
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function populateDenominationSelect(select) {
            if (!select) return;
            const currency = currentCurrency();
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '---Amount (' + currency + ')---';
            select.appendChild(placeholder);
            (denominationCatalog[currency] || denominationCatalog.GHS).forEach(value => {
                const option = document.createElement('option');
                option.value = Number(value).toFixed(2);
                option.textContent = formatDenomination(value);
                select.appendChild(option);
            });
        }

        function populateAllDenominationSelects() {
            document.querySelectorAll('#exchangeDenoModal .given-value, #exchangeDenoModal .received-value').forEach(populateDenominationSelect);
        }

        function syncUniqueDenominationOptions(container, selectSelector) {
            if (!container) return;

            const selects = Array.from(container.querySelectorAll(selectSelector));
            const selected = new Set();

            selects.forEach(sel => {
                const raw = (sel && typeof sel.value === 'string') ? sel.value.trim() : '';
                const num = parseFloat(raw);
                if (!isNaN(num) && num > 0) {
                    selected.add(num.toFixed(2));
                }
            });

            selects.forEach(sel => {
                const currentRaw = (sel && typeof sel.value === 'string') ? sel.value.trim() : '';
                const currentNum = parseFloat(currentRaw);
                const currentKey = (!isNaN(currentNum) && currentNum > 0) ? currentNum.toFixed(2) : null;

                Array.from(sel.options).forEach((opt, idx) => {
                    if (idx === 0) {
                        opt.disabled = false;
                        opt.hidden = false;
                        return;
                    }

                    const optRaw = (typeof opt.value === 'string' ? opt.value : opt.text).trim();
                    const optNum = parseFloat(optRaw);
                    if (isNaN(optNum) || optNum <= 0) {
                        opt.disabled = false;
                        opt.hidden = false;
                        return;
                    }

                    const optKey = optNum.toFixed(2);
                    const taken = selected.has(optKey) && optKey !== currentKey;
                    opt.disabled = taken;
                    opt.hidden = taken;
                });
            });
        }

        function syncAllExchangeSelects() {
            syncUniqueDenominationOptions(givenOutInputs, '.given-value');
            syncUniqueDenominationOptions(receivedInputs, '.received-value');
        }

        function updateTotals() {
            let givenTotal = 0;
            let receivedTotal_val = 0;

            document.querySelectorAll('.given-out-row').forEach(row => {
                const value = parseFloat(row.querySelector('.given-value').value) || 0;
                const count = parseInt(row.querySelector('.given-count').value) || 0;
                givenTotal += value * count;
            });

            document.querySelectorAll('.received-row').forEach(row => {
                const value = parseFloat(row.querySelector('.received-value').value) || 0;
                const count = parseInt(row.querySelector('.received-count').value) || 0;
                receivedTotal_val += value * count;
            });

            givenOutTotal.textContent = formatMoney(givenTotal);
            receivedTotal.textContent = formatMoney(receivedTotal_val);

            // Check balance
            const diff = Math.abs(givenTotal - receivedTotal_val);
            if (diff < 0.01 && givenTotal > 0) {
                balanceAlert.className = 'alert alert-success py-2 px-3';
                balanceStatus.innerHTML = '<i class="fa fa-check-circle me-2"></i> Denominations balanced ';
                submitExchange.disabled = false;
            } else if (givenTotal === 0 && receivedTotal_val === 0) {
                balanceAlert.className = 'alert alert-info py-2 px-3';
                balanceStatus.innerHTML = '<i class="fa fa-info-circle me-2"></i> Enter denominations to check balance';
                submitExchange.disabled = true;
            } else {
                balanceAlert.className = 'alert alert-warning py-2 px-3';
                balanceStatus.innerHTML = `<i class="fa fa-exclamation-triangle me-2"></i> Difference: ${formatMoney(diff)}`;
                submitExchange.disabled = true;
            }

            syncAllExchangeSelects();
        }

        function updateDeleteButtons() {
            const givenRows = document.querySelectorAll('.given-out-row');
            const receivedRows = document.querySelectorAll('.received-row');
            
            givenRows.forEach(row => {
                const deleteBtn = row.querySelector('.delete-given');
                if (deleteBtn) {
                    deleteBtn.style.display = givenRows.length > 1 ? 'block' : 'none';
                }
            });
            
            receivedRows.forEach(row => {
                const deleteBtn = row.querySelector('.delete-received');
                if (deleteBtn) {
                    deleteBtn.style.display = receivedRows.length > 1 ? 'block' : 'none';
                }
            });
        }

        function attachRowListeners(row) {
            const inputs = row.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', updateTotals);
            });

            const selects = row.querySelectorAll('select');
            selects.forEach(sel => {
                sel.addEventListener('change', updateTotals);
            });

            // Delete button for given
            const deleteGivenBtn = row.querySelector('.delete-given');
            if (deleteGivenBtn) {
                deleteGivenBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    row.remove();
                    updateDeleteButtons();
                    updateTotals();
                });
            }

            // Delete button for received
            const deleteReceivedBtn = row.querySelector('.delete-received');
            if (deleteReceivedBtn) {
                deleteReceivedBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    row.remove();
                    updateDeleteButtons();
                    updateTotals();
                });
            }
        }

        addGivenBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newRow = document.querySelector('.given-out-row').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelectorAll('select').forEach(sel => { sel.selectedIndex = 0; });
            givenOutInputs.appendChild(newRow);
            attachRowListeners(newRow);
            updateDeleteButtons();
            updateTotals();
        });

        addReceivedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newRow = document.querySelector('.received-row').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelectorAll('select').forEach(sel => { sel.selectedIndex = 0; });
            receivedInputs.appendChild(newRow);
            attachRowListeners(newRow);
            updateDeleteButtons();
            updateTotals();
        });

        if (exchangeForm) {
            exchangeForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const givenDenos = [];
                const receivedDenos = [];

                document.querySelectorAll('.given-out-row').forEach(row => {
                    const value = parseFloat(row.querySelector('.given-value').value);
                    const count = parseInt(row.querySelector('.given-count').value);
                    if (value > 0 && count > 0) {
                        givenDenos.push({ denomination: value, count: count });
                    }
                });

                document.querySelectorAll('.received-row').forEach(row => {
                    const value = parseFloat(row.querySelector('.received-value').value);
                    const count = parseInt(row.querySelector('.received-count').value);
                    if (value > 0 && count > 0) {
                        receivedDenos.push({ denomination: value, count: count });
                    }
                });

                if (givenDenos.length === 0 || receivedDenos.length === 0) {
                    window.basicAdminSwalAlert('Error', 'Please enter denominations for both given and received', 'error');
                    return;
                }

                const drawerId = window.currentDrawerId;
                if (!drawerId) {
                    window.basicAdminSwalAlert('Error', 'Cashdrawer ID not found', 'error');
                    return;
                }

                drawerIdInput.value = drawerId;

                const submitLabel = submitExchange ? submitExchange.innerHTML : '';
                if (submitExchange) {
                    submitExchange.disabled = true;
                    submitExchange.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing';
                }

                const body = new URLSearchParams();
                body.set('drawer_id', drawerId);
                body.set('currency', currentCurrency());
                body.set('given_denominations', JSON.stringify(givenDenos));
                body.set('received_denominations', JSON.stringify(receivedDenos));
                body.set('ajax', '1');

                fetch("<?=ROOT?>/admin/operations_accounts/exchange_denominations/" + encodeURIComponent(drawerId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString(),
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data || !data.success) {
                            const message = data && data.message ? data.message : 'Denomination exchange failed.';
                            window.basicAdminSwalAlert('Error', message, 'error');
                            return;
                        }

                        window.basicAdminSwalAlert('Success', data.message || 'Denomination exchange completed successfully.', 'success').then(function() {
                            const modalEl = document.getElementById('exchangeDenoModal');
                            if (window.bootstrap && modalEl) {
                                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                            }
                            resetExchangeRows();
                            if (typeof window.basicAdminLoadDrawer === 'function') {
                                const ctx = window.__cashdrawerCtx || {};
                                const reloadUserId = parseInt(ctx.userId || '0') || parseInt("<?= (int)(get_session()->id ?? 0) ?>", 10);
                                if (reloadUserId) {
                                    window.basicAdminLoadDrawer(reloadUserId);
                                }
                            }
                        });
                    })
                    .catch(() => {
                        window.basicAdminSwalAlert('Error', 'Denomination exchange failed.', 'error');
                    })
                    .finally(() => {
                        if (submitExchange) {
                            submitExchange.innerHTML = submitLabel;
                            updateTotals();
                        }
                    });
            });
        }

        function resetExchangeRows() {
            Array.from(givenOutInputs.querySelectorAll('.given-out-row')).forEach((row, index) => {
                if (index > 0) row.remove();
            });
            Array.from(receivedInputs.querySelectorAll('.received-row')).forEach((row, index) => {
                if (index > 0) row.remove();
            });
            givenOutInputs.querySelectorAll('input').forEach(input => input.value = '');
            receivedInputs.querySelectorAll('input').forEach(input => input.value = '');
            populateAllDenominationSelects();
            updateDeleteButtons();
            updateTotals();
        }

        if (exchangeCurrency) {
            exchangeCurrency.addEventListener('change', function() {
                resetExchangeRows();
                loadAvailableDenominations();
            });
        }

        attachRowListeners(document.querySelector('.given-out-row'));
        attachRowListeners(document.querySelector('.received-row'));
        populateAllDenominationSelects();
        
        function loadAvailableDenominations() {
            const drawerId = window.currentDrawerId;
            if (!drawerId) return;

            const info = document.getElementById('availableDenosInfo');
            const text = document.getElementById('availableDenosText');
            if (info) info.style.display = 'none';

            fetch('<?=ROOT?>/admin/operations_accounts/get_drawer_denominations/' + drawerId + '?currency=' + encodeURIComponent(currentCurrency()))
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.denominations)) {
                        const denoList = data.denominations
                            .filter(d => Number(d.total_count || 0) > 0)
                            .map(d => `${currentCurrency()} ${formatDenomination(d.denomination)} (${parseInt(d.total_count || 0, 10)} units)`)
                            .join(', ');

                        if (text) text.textContent = denoList || 'No available denominations recorded for this currency.';
                        if (info) info.style.display = 'block';
                    }
                })
                .catch(error => console.error('Error fetching denominations:', error));
        }

        // Fetch available denominations when modal opens
        const exchangeModal = document.getElementById('exchangeDenoModal');
        if (exchangeModal) {
            exchangeModal.addEventListener('show.bs.modal', function() {
                resetExchangeRows();
                loadAvailableDenominations();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExchangeModal);
    } else {
        initExchangeModal();
    }
})();
</script>

<style>
.denomination-row {
    padding: 12px;
    background-color: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.denomination-row:not(:last-child) {
    margin-bottom: 10px;
}

#exchangeDenoModal .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

/* Cashdrawer denomination history status badge for Exchange movements */
.transaction-status.status-exchange {
    background-color: rgba(255, 193, 7, 0.15);
    color: #d38b00;
    border: 1px solid rgba(255, 193, 7, 0.4);
}
</style>

<script>
// Minimal hard-wired toggle so Ask Assistant always opens/closes
(function(){
  var btn   = document.getElementById('aiAssistToggleBtn');
  var panel = document.getElementById('aiAssistPanel');
  if (!btn || !panel) return;
  btn.addEventListener('click', function(){
    var isOpen = panel.classList.toggle('is-open');
    panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
  });
})();
</script>

<script>
  document.addEventListener('submit', function(e){
    const form = e.target;
    if(!(form instanceof HTMLFormElement)) return;
    if(form.getAttribute('data-bo-allow-multi-submit') === '1') return;
    if(form.dataset && form.dataset.boSubmitting === '1'){
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    if(form.dataset){
      form.dataset.boSubmitting = '1';
    }
    const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    submitButtons.forEach(btn => {
      try{
        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
      } catch(err){}
    });
  }, true);
</script>

<?php
  $__liveAdminSession = function_exists('get_session') ? get_session() : null;
  $__liveAdminUserId = (int)($__liveAdminSession->id ?? 0);
  $__liveSoundConfig = function_exists('admin_settings_notification_sound_config') ? admin_settings_notification_sound_config() : [];
  $__liveSoundUrls = $__liveSoundConfig['sounds'] ?? [
    ROOT . '/assets/sounds/dragon-studio-new-notification-3-398649.mp3',
    ROOT . '/assets/sounds/dragon-studio-notification-sound-effect-372475.mp3',
    ROOT . '/assets/sounds/universfield-new-notification-040-493469.mp3',
    ROOT . '/assets/sounds/universfield-new-notification-09-352705.mp3',
  ];
  $__liveSoundEnabled = $__liveSoundConfig['enabled'] ?? true;
  $__liveSoundVolume = (int)($__liveSoundConfig['volume'] ?? 90);
  $__liveSoundVolume = max(0, min(100, $__liveSoundVolume)) / 100;
?>
<?php if($__liveAdminUserId > 0):?>
<script>
(function(){
  if(window.tormorLiveNotificationsActive){
    return;
  }

  const liveUrl = <?=json_encode(ROOT . '/admin/live-notifications')?>;
  const userId = <?=json_encode($__liveAdminUserId)?>;
  let liveSoundUrls = <?=json_encode(array_values($__liveSoundUrls))?>;
  let liveSoundEnabled = <?=json_encode((bool)$__liveSoundEnabled)?>;
  let liveSoundVolume = <?=json_encode($__liveSoundVolume)?>;
  const storageKey = 'tormor.live.notifications.' + userId;
  const intervalMs = 5000;
  let initialized = false;
  let lastState = {badges: {}, events: {}};
  let inFlight = false;
  let liveSoundIndex = 0;
  let liveAudioContext = null;
  let pendingLiveSound = false;
  let liveAudioUnlocked = false;
  let liveSoundPrompt = null;

  window.tormorLiveNotificationsActive = true;
  window.tormorLiveNotificationsLast = null;

  try{
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    if(saved && typeof saved === 'object'){
      lastState = {
        badges: saved.badges || {},
        events: saved.events || {}
      };
    }
  }catch(e){}

  function asNumber(value){
    const n = parseInt(value || 0, 10);
    return isNaN(n) ? 0 : n;
  }

  function updateBadge(id, count){
    const safeId = String(id).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    const badges = document.querySelectorAll('[data-notification-badge="' + safeId + '"]');
    badges.forEach(function(badge){
      const n = asNumber(count);
      if(n > 0){
        badge.textContent = String(n);
        badge.classList.remove('d-none');
        badge.style.display = '';
      }else{
        badge.textContent = '';
        badge.classList.add('d-none');
        badge.style.display = 'none';
      }
      try{
        // ensure urgency attribute set according to mapping so styling applies
        const urgency = computeUrgencyForId(id);
        badge.setAttribute('data-notification-urgency', urgency);
        // apply style immediately
        window.tormorApplyPillStyle(badge);
      }catch(e){}
    });
  }

  // compute urgency for a given badge id using server-injected mapping or heuristics
  function computeUrgencyForId(id){
    try{
      const i = (id || '').toString().toLowerCase();
      if(notificationPillPluginMap && typeof notificationPillPluginMap === 'object'){
        for(const cat of ['serious','mild','regular']){
          const list = notificationPillPluginMap[cat] || [];
          if(Array.isArray(list)){
            for(const pid of list){
              if(!pid) continue;
              const p = pid.toString().toLowerCase();
              if(i === p || i.indexOf(p) !== -1){
                return cat;
              }
            }
          }
        }
      }
      // fallback heuristics similar to server
      if(i.indexOf('reversal') !== -1 || i.indexOf('chargeback') !== -1) return 'serious';
      if(i.indexOf('report') !== -1 || i.indexOf('activity') !== -1 || i.indexOf('history') !== -1) return 'regular';
      if(i.indexOf('notification') !== -1 || i.indexOf('message') !== -1) return 'regular';
      return 'regular';
    }catch(e){ return 'regular'; }
  }

  // Notification pill color config from server
  const notificationPillConfig = <?=json_encode(function_exists('admin_settings_notification_pill_color_config') ? admin_settings_notification_pill_color_config() : ['serious'=>'#dc3545','mild'=>'#ffc107','regular'=>'#0d6efd'])?>;
  const notificationPillPluginMap = <?=json_encode(function_exists('admin_settings_notification_pill_plugin_map_config') ? admin_settings_notification_pill_plugin_map_config() : ['serious'=>[],'mild'=>[],'regular'=>[]])?>;

  function _hexBrightness(hex){
    try{
      const r = parseInt(hex.substr(1,2),16);
      const g = parseInt(hex.substr(3,2),16);
      const b = parseInt(hex.substr(5,2),16);
      return (r * 299 + g * 587 + b * 114) / 1000;
    }catch(e){return 255}
  }

  function applyPillAttributes(badge){
    if(!badge) return;
    const urgency = (badge.getAttribute('data-notification-urgency') || 'regular').toString();
    const cfg = String(notificationPillConfig[urgency] || '').trim();
    // ensure base classes
    badge.classList.add('badge','fw-bolder','rounded-pill');
    // remove inline bg color if any
    badge.style.backgroundColor = '';
    badge.style.color = '';
    if(cfg === ''){
      // defaults handled by CSS; ensure fallback bg-warning on badges created without server markup
      // do nothing here â€” server-side initial rendering should provide styles
      return;
    }
    if(/^#[0-9A-Fa-f]{6}$/.test(cfg)){
      badge.style.backgroundColor = cfg;
      const bright = _hexBrightness(cfg);
      badge.style.color = bright < 150 ? '#ffffff' : '#000000';
    } else {
      // treat as class list
      const parts = cfg.split(/\s+/).filter(Boolean);
      parts.forEach(function(c){ badge.classList.add(c); });
    }
  }

  // expose global applicator so other scripts can call it when they create badges
  window.tormorApplyPillStyle = function(elem){
    try{ applyPillAttributes(elem); }catch(e){}
  };

  // apply styles to any badges currently on the page
  (function(){
    try{
      const existing = document.querySelectorAll('[data-notification-badge],[data-notification-urgency]');
      existing.forEach(function(b){ applyPillAttributes(b); });
    }catch(e){}
  })();

  function shouldPlaySound(data){
    if(!initialized) return false;
    const badges = data.badges || {};
    const prevBadges = lastState.badges || {};
    for(const key in badges){
      if(asNumber(badges[key]) > asNumber(prevBadges[key])){
        return true;
      }
    }
    return false;
  }

  function playLiveSound(){
    if(!liveSoundEnabled){
      return;
    }

    if(window.tormorPlayNotificationSound){
      window.tormorPlayNotificationSound('global-live-' + Date.now());
      return;
    }
    if(window.tormorPlayAttentionSound){
      window.tormorPlayAttentionSound('global-live-' + Date.now());
      return;
    }

    if(liveSoundUrls.length){
      try{
        const src = liveSoundUrls[liveSoundIndex % liveSoundUrls.length];
        liveSoundIndex++;
        const audio = new Audio(src);
        audio.preload = 'auto';
        audio.volume = liveSoundVolume;
        const p = audio.play();
        if(p && typeof p.catch === 'function'){
          p.catch(function(){
            pendingLiveSound = true;
            showLiveSoundPrompt();
            playGeneratedLiveTone();
          });
        }
        return;
      }catch(e){}
    }

    if(!playGeneratedLiveTone()){
      pendingLiveSound = true;
      showLiveSoundPrompt();
    }
  }

  function applyLiveSoundConfig(sound){
    if(!sound || typeof sound !== 'object'){
      return;
    }
    liveSoundEnabled = sound.enabled !== false;
    const volume = asNumber(sound.volume);
    liveSoundVolume = Math.max(0, Math.min(100, volume || 90)) / 100;
    if(Array.isArray(sound.sounds)){
      liveSoundUrls = sound.sounds.filter(function(src){
        return typeof src === 'string' && src.length > 0;
      });
    }
  }

  function playGeneratedLiveTone(){
    try{
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if(!AudioCtx) return false;
      liveAudioContext = liveAudioContext || new AudioCtx();
      if(liveAudioContext.state === 'suspended'){
        liveAudioContext.resume();
      }
      if(liveAudioContext.state === 'suspended'){
        showLiveSoundPrompt();
        return false;
      }

      const now = liveAudioContext.currentTime;
      [784, 1046].forEach(function(freq, index){
        const osc = liveAudioContext.createOscillator();
        const gain = liveAudioContext.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, now + (index * 0.12));
        gain.gain.setValueAtTime(0.001, now + (index * 0.12));
        gain.gain.exponentialRampToValueAtTime(Math.max(0.04, liveSoundVolume * 0.18), now + 0.02 + (index * 0.12));
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.2 + (index * 0.12));
        osc.connect(gain);
        gain.connect(liveAudioContext.destination);
        osc.start(now + (index * 0.12));
        osc.stop(now + 0.24 + (index * 0.12));
      });
      pendingLiveSound = false;
      return true;
    }catch(e){
      return false;
    }
  }

  function unlockLiveAudio(){
    liveAudioUnlocked = true;
    hideLiveSoundPrompt();

    try{
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if(AudioCtx){
        liveAudioContext = liveAudioContext || new AudioCtx();
        if(liveAudioContext.state === 'suspended'){
          liveAudioContext.resume();
        }
      }
    }catch(e){}

    if(pendingLiveSound){
      pendingLiveSound = false;
      playLiveSound();
    }
  }

  function showLiveSoundPrompt(){
    if(!liveSoundEnabled || liveAudioUnlocked || liveSoundPrompt){
      return;
    }
    try{
      liveSoundPrompt = document.createElement('button');
      liveSoundPrompt.type = 'button';
      liveSoundPrompt.className = 'btn btn-warning shadow fw-bold';
      liveSoundPrompt.style.position = 'fixed';
      liveSoundPrompt.style.right = '18px';
      liveSoundPrompt.style.bottom = '18px';
      liveSoundPrompt.style.zIndex = '2147483000';
      liveSoundPrompt.style.borderRadius = '999px';
      liveSoundPrompt.style.padding = '10px 16px';
      liveSoundPrompt.innerHTML = '<i class="fa-solid fa-volume-high me-1"></i> Enable sound';
      liveSoundPrompt.addEventListener('click', function(){
        unlockLiveAudio();
        playGeneratedLiveTone();
      });
      document.body.appendChild(liveSoundPrompt);
    }catch(e){}
  }

  function hideLiveSoundPrompt(){
    try{
      if(liveSoundPrompt && liveSoundPrompt.parentNode){
        liveSoundPrompt.parentNode.removeChild(liveSoundPrompt);
      }
    }catch(e){}
    liveSoundPrompt = null;
  }

  function armLiveAudio(){
    if(!liveSoundEnabled || liveAudioUnlocked){
      return;
    }
    try{
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if(AudioCtx){
        liveAudioContext = liveAudioContext || new AudioCtx();
        if(liveAudioContext.state === 'running'){
          liveAudioUnlocked = true;
          hideLiveSoundPrompt();
          return;
        }
      }
    }catch(e){}

    if(liveSoundUrls.length){
      try{
        const audio = new Audio(liveSoundUrls[0]);
        audio.preload = 'auto';
        audio.muted = true;
        audio.volume = 0;
        const p = audio.play();
        if(p && typeof p.then === 'function'){
          p.then(function(){
            audio.pause();
            audio.currentTime = 0;
            liveAudioUnlocked = true;
            hideLiveSoundPrompt();
          }).catch(function(){
            showLiveSoundPrompt();
          });
          return;
        }
      }catch(e){}
    }

    showLiveSoundPrompt();
  }

  async function pollLiveNotifications(){
    if(inFlight) return;
    inFlight = true;
    try{
      const res = await fetch(liveUrl + '?_=' + Date.now(), {
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        credentials: 'same-origin'
      });
      const obj = await res.json();
      window.tormorLiveNotificationsLast = obj;
      if(!obj || !obj.success || !obj.data) return;

      const data = obj.data;
      applyLiveSoundConfig(data.sound);
      const badges = data.badges || {};
      Object.keys(badges).forEach(function(id){
        updateBadge(id, badges[id]);
      });

      if(shouldPlaySound(data)){
        playLiveSound();
      }

      lastState = {
        badges: badges,
        events: data.events || {}
      };
      try{ localStorage.setItem(storageKey, JSON.stringify(lastState)); }catch(e){}
      initialized = true;
      try{ window.dispatchEvent(new CustomEvent('tormor:live-notifications', {detail: data})); }catch(e){}
    }catch(e){
      window.tormorLiveNotificationsLast = {success: false, error: String((e && e.message) || e)};
    }finally{
      inFlight = false;
    }
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', function(){
      armLiveAudio();
      pollLiveNotifications();
    });
  }else{
    armLiveAudio();
    pollLiveNotifications();
  }
  ['click', 'keydown', 'touchstart'].forEach(function(eventName){
    document.addEventListener(eventName, unlockLiveAudio, {passive: true});
  });
  setInterval(pollLiveNotifications, intervalMs);
})();
</script>
<?php endif?>

</body>
</html>
