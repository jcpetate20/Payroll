<?php

session_start();

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";

/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Administrator Only
|--------------------------------------------------------------------------
*/

if ($_SESSION["Role"] !== "Administrator") {
    http_response_code(403);

    die('
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied | Payroll System</title>

            <style>
                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    background: #090b0d;
                    color: #F4EFE8;
                    font-family: Arial, sans-serif;
                }

                .denied-box {
                    width: 100%;
                    max-width: 500px;
                    padding: 40px;
                    text-align: center;
                    background: #111417;
                    border: 1px solid #282d31;
                    border-radius: 16px;
                    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
                }

                .denied-icon {
                    width: 64px;
                    height: 64px;
                    margin: 0 auto 20px;
                    display: grid;
                    place-items: center;
                    border-radius: 16px;
                    background: rgba(212, 125, 133, 0.10);
                    border: 1px solid rgba(212, 125, 133, 0.25);
                    color: #D47D85;
                }

                .denied-icon svg {
                    width: 32px;
                    height: 32px;
                    fill: none;
                    stroke: currentColor;
                    stroke-width: 1.7;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                }

                h2 {
                    margin: 0 0 12px;
                    color: #F4EFE8;
                    font-size: 26px;
                }

                p {
                    margin: 0 0 25px;
                    color: #9CA3A8;
                    line-height: 1.6;
                    font-size: 14px;
                }

                a {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    padding: 11px 18px;
                    border-radius: 8px;
                    background: #641C28;
                    border: 1px solid rgba(255, 255, 255, 0.05);
                    color: #F4EFE8;
                    text-decoration: none;
                    font-weight: 600;
                    transition: background 0.2s ease;
                }

                a:hover {
                    background: #7A2635;
                }

                a svg {
                    width: 17px;
                    height: 17px;
                    fill: none;
                    stroke: currentColor;
                    stroke-width: 1.8;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                }
            </style>
        </head>

        <body>

            <div class="denied-box">

                <div class="denied-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                        <path d="M7 11V8a5 5 0 0 1 10 0v3"></path>
                        <circle cx="12" cy="16" r="1"></circle>
                    </svg>
                </div>

                <h2>Access Denied</h2>

                <p>
                    Only administrators can access database backups.
                </p>

                <a href="dashboard.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 12H5"></path>
                        <path d="M12 19l-7-7 7-7"></path>
                    </svg>
                    Return to Dashboard
                </a>

            </div>

        </body>
        </html>
    ');
}

$user_id = (int) $_SESSION["User_ID"];

/*
|--------------------------------------------------------------------------
| Secure Backup Directory
|--------------------------------------------------------------------------
|
| Project:
| XAMPP\htdocs\payroll_system
|
| Backup:
| XAMPP\payroll_backups
|
|--------------------------------------------------------------------------
*/

$backup_dir =
    dirname(__DIR__, 2) .
    DIRECTORY_SEPARATOR .
    "payroll_backups";

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Create Backup Folder
|--------------------------------------------------------------------------
*/

if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        $error = "Unable to create the secure backup folder.";
    }
}

/*
|--------------------------------------------------------------------------
| Create Database Backup
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */

    $csrf = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf)) {

        $error =
            "Invalid security token. Please refresh the page and try again.";

    } elseif (!is_dir($backup_dir)) {

        $error =
            "Secure backup folder does not exist.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Backup Filename
        |--------------------------------------------------------------------------
        */

        $filename =
            "payroll_system_backup_" .
            date("Y-m-d_H-i-s") .
            ".sql";

        $filepath =
            $backup_dir .
            DIRECTORY_SEPARATOR .
            $filename;

        $handle = fopen($filepath, "w");

        if (!$handle) {

            $error =
                "Unable to create the backup file.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Backup Header
            |--------------------------------------------------------------------------
            */

            fwrite(
                $handle,
                "-- Payroll System Database Backup\n" .
                "-- Generated: " .
                date("Y-m-d H:i:s") .
                "\n" .
                "-- Database: " .
                $database .
                "\n\n"
            );

            fwrite(
                $handle,
                "SET FOREIGN_KEY_CHECKS=0;\n\n"
            );

            /*
            |--------------------------------------------------------------------------
            | Get All Tables
            |--------------------------------------------------------------------------
            */

            $tables_result = $conn->query("SHOW TABLES");

            if (!$tables_result) {

                fclose($handle);
                @unlink($filepath);

                $error =
                    "Unable to read database tables.";

            } else {

                while ($table_row = $tables_result->fetch_array()) {

                    $table_name = $table_row[0];

                    /*
                    |--------------------------------------------------------------------------
                    | Table Header
                    |--------------------------------------------------------------------------
                    */

                    fwrite(
                        $handle,
                        "-- --------------------------------------------------------\n" .
                        "-- Table: `" .
                        $table_name .
                        "`\n" .
                        "-- --------------------------------------------------------\n\n"
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Drop Existing Table
                    |--------------------------------------------------------------------------
                    */

                    fwrite(
                        $handle,
                        "DROP TABLE IF EXISTS `" .
                        $table_name .
                        "`;\n\n"
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Create Table Statement
                    |--------------------------------------------------------------------------
                    */

                    $create_result = $conn->query(
                        "SHOW CREATE TABLE `" .
                        $table_name .
                        "`"
                    );

                    if ($create_result) {

                        $create_row =
                            $create_result->fetch_assoc();

                        $create_sql =
                            $create_row["Create Table"];

                        fwrite(
                            $handle,
                            $create_sql .
                            ";\n\n"
                        );

                        $create_result->free();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Export Table Data
                    |--------------------------------------------------------------------------
                    */

                    $data_result = $conn->query(
                        "SELECT * FROM `" .
                        $table_name .
                        "`"
                    );

                    if (
                        $data_result &&
                        $data_result->num_rows > 0
                    ) {

                        while (
                            $data_row =
                            $data_result->fetch_assoc()
                        ) {

                            $columns = [];
                            $values = [];

                            foreach (
                                $data_row as $column => $value
                            ) {

                                $columns[] =
                                    "`" .
                                    $column .
                                    "`";

                                if ($value === null) {

                                    $values[] = "NULL";

                                } else {

                                    $values[] =
                                        "'" .
                                        $conn->real_escape_string(
                                            $value
                                        ) .
                                        "'";
                                }
                            }

                            $insert_sql =
                                "INSERT INTO `" .
                                $table_name .
                                "` (" .
                                implode(
                                    ", ",
                                    $columns
                                ) .
                                ") VALUES (" .
                                implode(
                                    ", ",
                                    $values
                                ) .
                                ");\n";

                            fwrite(
                                $handle,
                                $insert_sql
                            );
                        }

                        fwrite(
                            $handle,
                            "\n"
                        );

                        $data_result->free();
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Re-enable Foreign Keys
                |--------------------------------------------------------------------------
                */

                fwrite(
                    $handle,
                    "SET FOREIGN_KEY_CHECKS=1;\n"
                );

                fclose($handle);

                /*
                |--------------------------------------------------------------------------
                | Verify Backup
                |--------------------------------------------------------------------------
                */

                if (
                    file_exists($filepath) &&
                    filesize($filepath) > 0
                ) {

                    write_audit_log(
                        $conn,
                        $user_id,
                        "DATABASE_BACKUP",
                        "Created secure database backup: " .
                        $filename
                    );

                    $message =
                        "Database backup created successfully: " .
                        $filename;

                } else {

                    @unlink($filepath);

                    $error =
                        "Backup creation failed. The backup file is empty or missing.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Existing Backups
|--------------------------------------------------------------------------
*/

$backups = [];

if (is_dir($backup_dir)) {

    $files = scandir($backup_dir);

    foreach ($files as $file) {

        if (
            $file !== "." &&
            $file !== ".." &&
            strtolower(
                pathinfo(
                    $file,
                    PATHINFO_EXTENSION
                )
            ) === "sql"
        ) {

            $filepath =
                $backup_dir .
                DIRECTORY_SEPARATOR .
                $file;

            if (is_file($filepath)) {

                $backups[] = [
                    "name" => $file,
                    "size" => filesize($filepath),
                    "created" => filemtime($filepath)
                ];
            }
        }
    }

    usort(
        $backups,
        function ($a, $b) {
            return $b["created"] <=> $a["created"];
        }
    );
}

/*
|--------------------------------------------------------------------------
| Format File Size
|--------------------------------------------------------------------------
*/

function format_file_size($bytes)
{
    if ($bytes >= 1024 * 1024) {

        return number_format(
            $bytes / (1024 * 1024),
            2
        ) . " MB";
    }

    if ($bytes >= 1024) {

        return number_format(
            $bytes / 1024,
            2
        ) . " KB";
    }

    return $bytes . " bytes";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Database Backup | Payroll System
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --bg: #090b0d;
            --panel: #111417;
            --panel-light: #15191c;

            --border: #282d31;
            --border-light: #343a3f;

            --maroon: #641C28;
            --maroon-dark: #4A0E18;
            --maroon-light: #7A2635;

            --gold: #C9A66B;
            --gold-soft: rgba(201, 166, 107, 0.12);
            --gold-border: rgba(201, 166, 107, 0.25);

            --text: #F4EFE8;
            --muted: #9CA3A8;
            --muted-light: #C4C8CA;

            --green: #79B89A;
            --green-soft: rgba(121, 184, 154, 0.10);
            --green-border: rgba(121, 184, 154, 0.25);

            --red: #D47D85;
            --red-soft: rgba(212, 125, 133, 0.10);
            --red-border: rgba(212, 125, 133, 0.25);
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        /* =========================
           HEADER
        ========================= */

        .topbar {
            min-height: 76px;
            padding: 16px 40px;
            background: #0c0f11;
            border-bottom: 1px solid var(--border);

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            border-radius: 13px;

            display: grid;
            place-items: center;

            color: var(--gold);
            background: var(--gold-soft);
            border: 1px solid var(--gold-border);
        }

        .brand-icon svg {
            width: 24px;
            height: 24px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .brand-subtitle {
            font-size: 12px;
            color: var(--muted);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 11px 16px;

            border-radius: 8px;

            background: var(--maroon);
            color: var(--text);

            border: 1px solid rgba(255, 255, 255, 0.05);

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            transition: background 0.2s ease;
        }

        .back-button:hover {
            background: var(--maroon-light);
        }

        .back-button svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           PAGE
        ========================= */

        .container {
            width: 92%;
            max-width: 1050px;

            margin: 42px auto 60px;
        }

        .page-heading {
            margin-bottom: 24px;
        }

        .page-heading h1 {
            margin: 0 0 7px;

            font-size: 28px;
            color: var(--text);
        }

        .page-heading p {
            margin: 0;

            color: var(--muted);

            font-size: 14px;
            line-height: 1.6;
        }

        /* =========================
           STATUS MESSAGES
        ========================= */

        .message,
        .error {
            display: flex;
            align-items: flex-start;
            gap: 11px;

            padding: 14px 15px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-size: 14px;
            line-height: 1.5;
        }

        .message {
            background: var(--green-soft);
            color: #a4d0b8;
            border: 1px solid var(--green-border);
        }

        .error {
            background: var(--red-soft);
            color: #e7a4aa;
            border: 1px solid var(--red-border);
        }

        .message svg,
        .error svg {
            width: 19px;
            height: 19px;
            min-width: 19px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           CARDS
        ========================= */

        .card {
            background: var(--panel);

            border: 1px solid var(--border);
            border-radius: 16px;

            padding: 28px;
            margin-bottom: 22px;

            box-shadow:
                0 18px 45px rgba(0, 0, 0, 0.20);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 13px;

            padding-bottom: 20px;
            margin-bottom: 20px;

            border-bottom: 1px solid var(--border);
        }

        .card-icon {
            width: 44px;
            height: 44px;

            flex-shrink: 0;

            border-radius: 12px;

            display: grid;
            place-items: center;

            background: var(--gold-soft);
            border: 1px solid var(--gold-border);

            color: var(--gold);
        }

        .card-icon svg {
            width: 23px;
            height: 23px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .card-title-area {
            min-width: 0;
        }

        .card h2 {
            margin: 0;

            color: var(--text);
            font-size: 18px;
        }

        .card-subtitle {
            margin: 5px 0 0;

            color: var(--muted);

            font-size: 13px;
            line-height: 1.5;
        }

        /* =========================
           BACKUP INFORMATION
        ========================= */

        .backup-info {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 12px;

            margin-bottom: 20px;
        }

        .info-box {
            padding: 14px;

            background: var(--panel-light);

            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .info-label {
            display: block;

            margin-bottom: 5px;

            color: var(--muted);

            font-size: 11px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .info-value {
            color: var(--muted-light);

            font-size: 13px;

            word-break: break-word;
        }

        /* =========================
           CREATE BACKUP
        ========================= */

        .backup-description {
            margin: 0 0 20px;

            color: var(--muted);

            font-size: 14px;
            line-height: 1.65;
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-bottom: 20px;
            padding: 12px 13px;

            border-radius: 8px;

            background: rgba(201, 166, 107, 0.07);

            border:
                1px solid
                rgba(201, 166, 107, 0.15);

            color: var(--muted);

            font-size: 12px;
            line-height: 1.5;
        }

        .security-note svg {
            width: 18px;
            height: 18px;
            min-width: 18px;

            color: var(--gold);

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;
            gap: 9px;

            min-height: 44px;

            padding: 11px 19px;

            border: 1px solid transparent;
            border-radius: 8px;

            background: var(--maroon);
            color: var(--text);

            font-family: inherit;
            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .btn:hover {
            background: var(--maroon-light);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn svg {
            width: 18px;
            height: 18px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           BACKUP COUNT
        ========================= */

        .backup-count {
            margin-left: auto;

            padding: 7px 11px;

            border-radius: 7px;

            background: var(--gold-soft);

            border: 1px solid var(--gold-border);

            color: var(--gold);

            font-size: 12px;
            font-weight: 700;

            white-space: nowrap;
        }

        /* =========================
           TABLE
        ========================= */

        .table-wrapper {
            overflow-x: auto;

            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 650px;
        }

        th,
        td {
            padding: 14px 15px;

            text-align: left;

            border-bottom: 1px solid var(--border);
        }

        th {
            background: #0d1012;

            color: var(--muted);

            font-size: 11px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        td {
            color: var(--muted-light);

            font-size: 13px;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: background 0.15s ease;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.025);
        }

        .file-name {
            color: var(--text);

            font-weight: 600;

            font-family:
                Consolas,
                "Courier New",
                monospace;

            font-size: 12px;
        }

        .file-size {
            color: var(--gold);

            font-weight: 600;
        }

        .date-value {
            color: var(--muted);
        }

        /* =========================
           EMPTY STATE
        ========================= */

        .empty {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 18px;

            border-radius: 10px;

            background: var(--panel-light);

            border: 1px solid var(--border);

            color: var(--muted);

            font-size: 13px;
        }

        .empty svg {
            width: 21px;
            height: 21px;
            min-width: 21px;

            color: var(--gold);

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           FOOTER BACK
        ========================= */

        .bottom-back {
            display: inline-flex;

            align-items: center;
            gap: 8px;

            color: var(--muted);

            text-decoration: none;

            font-size: 13px;
            font-weight: 600;

            transition: color 0.2s ease;
        }

        .bottom-back:hover {
            color: var(--gold);
        }

        .bottom-back svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            .topbar {
                padding: 15px 20px;

                flex-direction: column;
                align-items: stretch;
            }

            .back-button {
                justify-content: center;
            }

            .container {
                width: 94%;
                margin-top: 28px;
            }

            .page-heading h1 {
                font-size: 24px;
            }

            .card {
                padding: 21px;
                border-radius: 13px;
            }

            .backup-info {
                grid-template-columns: 1fr;
            }

            .card-header {
                align-items: flex-start;
            }

            .backup-count {
                margin-left: 0;
            }

            .table-wrapper {
                border-radius: 8px;
            }
        }

    </style>

</head>

<body>

    <!-- =========================
         TOP HEADER
    ========================= -->

    <header class="topbar">

        <div class="brand">

            <div class="brand-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <ellipse
                        cx="12"
                        cy="5"
                        rx="8"
                        ry="3"
                    />

                    <path
                        d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5"
                    />

                    <path
                        d="M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"
                    />
                </svg>

            </div>

            <div class="brand-text">

                <div class="brand-title">
                    Payroll System
                </div>

                <div class="brand-subtitle">
                    Database Administration
                </div>

            </div>

        </div>

        <a
            href="dashboard.php"
            class="back-button"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M19 12H5"></path>
                <path d="M12 19l-7-7 7-7"></path>
            </svg>

            Dashboard

        </a>

    </header>


    <!-- =========================
         MAIN CONTENT
    ========================= -->

    <main class="container">

        <div class="page-heading">

            <h1>
                Database Backup
            </h1>

            <p>
                Create and manage secure backups of the payroll system database.
            </p>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if ($message): ?>

            <div class="message">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path d="m8 12 2.5 2.5L16 9"></path>
                </svg>

                <span>
                    <?= htmlspecialchars($message) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if ($error): ?>

            <div class="error">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path d="M12 8v4"></path>

                    <path d="M12 16h.01"></path>
                </svg>

                <span>
                    <?= htmlspecialchars($error) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- =========================
             CREATE BACKUP CARD
        ========================= -->

        <section class="card">

            <div class="card-header">

                <div class="card-icon">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M12 3v12"></path>

                        <path d="m7 10 5 5 5-5"></path>

                        <path d="M5 21h14"></path>

                        <path d="M5 17v4"></path>

                        <path d="M19 17v4"></path>
                    </svg>

                </div>

                <div class="card-title-area">

                    <h2>
                        Create Database Backup
                    </h2>

                    <p class="card-subtitle">
                        Export the current database structure and data into a SQL file.
                    </p>

                </div>

            </div>


            <div class="backup-info">

                <div class="info-box">

                    <span class="info-label">
                        Database
                    </span>

                    <span class="info-value">
                        <?= htmlspecialchars($database) ?>
                    </span>

                </div>


                <div class="info-box">

                    <span class="info-label">
                        Backup Format
                    </span>

                    <span class="info-value">
                        SQL Database Export
                    </span>

                </div>

            </div>


            <p class="backup-description">

                This backup contains the database tables, table structures,
                relationships, and current records. The generated SQL file
                is stored in the secure payroll backup directory.

            </p>


            <div class="security-note">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <rect
                        x="5"
                        y="10"
                        width="14"
                        height="10"
                        rx="2"
                    />

                    <path
                        d="M8 10V7a4 4 0 0 1 8 0v3"
                    />
                </svg>

                <span>
                    Database backups are restricted to administrators
                    and each successful backup is recorded in the system audit log.
                </span>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()) ?>"
                >

                <button
                    type="submit"
                    class="btn"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M12 3v12"></path>

                        <path d="m7 10 5 5 5-5"></path>

                        <path d="M5 21h14"></path>
                    </svg>

                    Create Backup

                </button>

            </form>

        </section>


        <!-- =========================
             EXISTING BACKUPS
        ========================= -->

        <section class="card">

            <div class="card-header">

                <div class="card-icon">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"
                        />

                        <path d="M3 9h18"></path>
                    </svg>

                </div>


                <div class="card-title-area">

                    <h2>
                        Existing Backups
                    </h2>

                    <p class="card-subtitle">
                        Previously generated SQL database backups.
                    </p>

                </div>


                <div class="backup-count">

                    <?= count($backups) ?>

                    <?= count($backups) === 1 ? "Backup" : "Backups" ?>

                </div>

            </div>


            <?php if (empty($backups)): ?>

                <div class="empty">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"
                        />

                        <path d="M8 13h8"></path>
                    </svg>

                    <span>
                        No database backups have been created yet.
                    </span>

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Backup File
                                </th>

                                <th>
                                    Size
                                </th>

                                <th>
                                    Created
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($backups as $backup): ?>

                                <tr>

                                    <td>

                                        <span class="file-name">

                                            <?= htmlspecialchars(
                                                $backup["name"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="file-size">

                                            <?= format_file_size(
                                                $backup["size"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="date-value">

                                            <?= date(
                                                "Y-m-d H:i:s",
                                                $backup["created"]
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>


        <!-- =========================
             BACK TO DASHBOARD
        ========================= -->

        <a
            href="dashboard.php"
            class="bottom-back"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M19 12H5"></path>

                <path d="M12 19l-7-7 7-7"></path>
            </svg>

            Back to Dashboard

        </a>

    </main>

</body>

</html>