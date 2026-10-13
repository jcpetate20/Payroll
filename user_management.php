<?php

session_start();

require_once "db.php";
require_once "session_security.php";

/*
|--------------------------------------------------------------------------
| Check if user is logged in
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

    die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Access Denied</title>
            <style>
                body {
                    margin: 0;
                    background: #090b0d;
                    color: #F4EFE8;
                    font-family: Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }

                .access-box {
                    width: 90%;
                    max-width: 480px;
                    background: #111417;
                    border: 1px solid #282d31;
                    border-radius: 14px;
                    padding: 35px;
                    text-align: center;
                }

                .access-icon {
                    width: 52px;
                    height: 52px;
                    margin: 0 auto 18px;
                    border-radius: 14px;
                    background: rgba(212,125,133,.10);
                    color: #D47D85;
                    display: grid;
                    place-items: center;
                    border: 1px solid rgba(212,125,133,.22);
                }

                .access-icon svg {
                    width: 27px;
                    height: 27px;
                    fill: none;
                    stroke: currentColor;
                    stroke-width: 1.7;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                }

                h2 {
                    margin: 0 0 10px;
                }

                p {
                    color: #9CA3A8;
                    line-height: 1.6;
                }

                a {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    margin-top: 12px;
                    padding: 11px 18px;
                    background: #641C28;
                    color: white;
                    text-decoration: none;
                    border-radius: 9px;
                    font-weight: 600;
                }

                a:hover {
                    background: #7A2635;
                }
            </style>
        </head>

        <body>

            <div class='access-box'>

                <div class='access-icon'>
                    <svg viewBox='0 0 24 24' aria-hidden='true'>
                        <circle cx='12' cy='12' r='9'/>
                        <path d='M12 8v4'/>
                        <path d='M12 16h.01'/>
                    </svg>
                </div>

                <h2>Access Denied</h2>

                <p>
                    Only administrators can access User Management.
                </p>

                <a href='dashboard.php'>
                    <svg
                        viewBox='0 0 24 24'
                        width='17'
                        height='17'
                        fill='none'
                        stroke='currentColor'
                        stroke-width='1.8'
                        stroke-linecap='round'
                        stroke-linejoin='round'
                    >
                        <path d='M19 12H5'/>
                        <path d='M12 19l-7-7 7-7'/>
                    </svg>

                    Return to Dashboard
                </a>

            </div>

        </body>
        </html>
    ");

}

/*
|--------------------------------------------------------------------------
| Get all users
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.User_ID,
        u.Username,
        u.Full_Name,
        u.Role,
        u.Status,
        u.Last_Login,
        u.Created_At,
        e.Emp_ID,
        e.First_Name,
        e.Last_Name
    FROM users u
    LEFT JOIN employee e
        ON u.Emp_ID = e.Emp_ID
    ORDER BY u.User_ID DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Failed to prepare user query.");
}

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>User Management</title>

    <style>

        :root {
            --bg: #090b0d;
            --panel: #111417;
            --panel-light: #15191c;
            --border: #282d31;

            --maroon: #641C28;
            --maroon-hover: #7A2635;

            --gold: #C9A66B;

            --text: #F4EFE8;
            --muted: #9CA3A8;
            --muted-light: #C4C8CA;

            --green: #79B89A;
            --red: #D47D85;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            width: 100%;

            min-height: 76px;

            padding: 16px 4%;

            background: #0c0f11;

            border-bottom: 1px solid var(--border);

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;

            border-radius: 13px;

            background: rgba(201,166,107,.12);

            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid rgba(201,166,107,.22);
        }

        .brand-icon svg {
            width: 25px;
            height: 25px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-title {
            margin: 0;

            color: var(--text);

            font-size: 17px;
            font-weight: 700;
        }

        .brand-subtitle {
            margin: 3px 0 0;

            color: var(--muted);

            font-size: 12px;
        }

        .header-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-buttons a {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: white;

            text-decoration: none;

            padding: 10px 15px;

            border-radius: 8px;

            background: var(--maroon);

            border: 1px solid rgba(255,255,255,.05);

            font-size: 13px;
            font-weight: 600;

            transition: .2s ease;
        }

        .header-buttons a:hover {
            background: var(--maroon-hover);
            transform: translateY(-1px);
        }

        .header-buttons svg {
            width: 16px;
            height: 16px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           CONTAINER
        ========================= */

        .container {
            width: 94%;
            max-width: 1450px;

            margin: 35px auto 50px;
        }

        /* =========================
           PAGE TITLE
        ========================= */

        .page-title {
            background: var(--panel);

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;
        }

        .title-top {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }

        .title-content {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .page-icon {
            width: 46px;
            height: 46px;

            border-radius: 13px;

            background: rgba(201,166,107,.10);

            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid rgba(201,166,107,.20);
        }

        .page-icon svg {
            width: 24px;
            height: 24px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .page-title h2 {
            margin: 0 0 6px;

            color: var(--text);

            font-size: 21px;
            font-weight: 650;
        }

        .page-title p {
            margin: 0;

            color: var(--muted);

            font-size: 13px;
        }

        .add-user-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            padding: 11px 17px;

            background: var(--maroon);

            color: white;

            text-decoration: none;

            border-radius: 9px;

            border: 1px solid rgba(255,255,255,.05);

            font-size: 13px;
            font-weight: 600;

            white-space: nowrap;

            transition: .2s ease;
        }

        .add-user-button:hover {
            background: var(--maroon-hover);
            transform: translateY(-1px);
        }

        .add-user-button svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           SUCCESS MESSAGE
        ========================= */

        .success-message {
            display: flex;
            align-items: center;
            gap: 10px;

            background: rgba(121,184,154,.10);

            color: var(--green);

            border: 1px solid rgba(121,184,154,.22);

            padding: 13px 16px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-size: 13px;
            font-weight: 600;
        }

        .success-message svg {
            width: 18px;
            height: 18px;

            flex-shrink: 0;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           TABLE CONTAINER
        ========================= */

        .table-container {
            background: var(--panel);

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 20px;

            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 17px;
        }

        .table-header h3 {
            margin: 0;

            color: var(--text);

            font-size: 17px;
            font-weight: 650;
        }

        .table-header p {
            margin: 4px 0 0;

            color: var(--muted);

            font-size: 12px;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1200px;
        }

        th {
            background: var(--panel-light);

            color: var(--muted-light);

            padding: 14px 12px;

            text-align: left;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .45px;

            white-space: nowrap;

            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px 12px;

            border-bottom: 1px solid var(--border);

            font-size: 13px;

            color: var(--text);

            white-space: nowrap;

            vertical-align: middle;
        }

        tbody tr {
            transition: .15s ease;
        }

        tbody tr:hover {
            background: rgba(100,28,40,.08);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .user-id {
            color: var(--gold);
            font-weight: 700;
        }

        .username {
            color: var(--text);
            font-weight: 600;
        }

        .full-name {
            color: var(--text);
        }

        .role {
            color: var(--gold);
            font-weight: 600;
        }

        .employee-name {
            color: var(--muted-light);
        }

        .not-linked {
            color: var(--muted);

            font-style: italic;
        }

        /* =========================
           STATUS
        ========================= */

        .status-active,
        .status-inactive {
            display: inline-flex;

            align-items: center;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;
        }

        .status-active {
            background: rgba(121,184,154,.10);

            color: var(--green);

            border: 1px solid rgba(121,184,154,.22);
        }

        .status-inactive {
            background: rgba(212,125,133,.10);

            color: var(--red);

            border: 1px solid rgba(212,125,133,.22);
        }

        /* =========================
           EDIT BUTTON
        ========================= */

        .edit-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            padding: 8px 12px;

            background: rgba(201,166,107,.10);

            color: var(--gold);

            text-decoration: none;

            border: 1px solid rgba(201,166,107,.22);

            border-radius: 8px;

            font-size: 12px;

            font-weight: 600;

            transition: .2s ease;
        }

        .edit-button:hover {
            background: rgba(201,166,107,.17);

            border-color: rgba(201,166,107,.35);
        }

        .edit-button svg {
            width: 15px;
            height: 15px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           EMPTY
        ========================= */

        .empty {
            text-align: center;

            padding: 55px 30px;

            color: var(--muted);
        }

        .empty-icon {
            width: 48px;
            height: 48px;

            margin: 0 auto 14px;

            border-radius: 13px;

            background: rgba(201,166,107,.08);

            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid rgba(201,166,107,.15);
        }

        .empty-icon svg {
            width: 24px;
            height: 24px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .empty h3 {
            margin: 0 0 7px;

            color: var(--text);

            font-size: 16px;
        }

        .empty p {
            margin: 0;

            font-size: 13px;
        }

        /* =========================
           BACK BUTTON
        ========================= */

        .back-button {
            display: inline-flex;

            align-items: center;

            gap: 8px;

            margin-top: 20px;

            background: transparent;

            color: var(--muted-light);

            text-decoration: none;

            padding: 10px 14px;

            border: 1px solid var(--border);

            border-radius: 8px;

            font-size: 13px;

            transition: .2s ease;
        }

        .back-button:hover {
            color: var(--text);

            border-color: #3a4045;

            background: var(--panel-light);
        }

        .back-button svg {
            width: 16px;
            height: 16px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 850px) {

            .header {
                padding: 15px 4%;

                flex-direction: column;

                align-items: stretch;
            }

            .header-buttons a {
                width: 100%;
                justify-content: center;
            }

            .title-top {
                flex-direction: column;

                align-items: stretch;
            }

            .add-user-button {
                width: 100%;
            }

            .container {
                width: 92%;

                margin-top: 25px;
            }

        }

        @media (max-width: 500px) {

            .page-title,
            .table-container {
                padding: 18px;
            }

            .page-title h2 {
                font-size: 19px;
            }

            .brand-subtitle {
                display: none;
            }

            .brand-title {
                font-size: 15px;
            }

            .page-icon {
                width: 42px;
                height: 42px;
            }

            .page-icon svg {
                width: 22px;
                height: 22px;
            }

        }

    </style>

</head>

<body>

<!-- HEADER -->

<div class="header">

    <div class="brand-section">

        <div class="brand-icon">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-3 4v2"/>

                <circle
                    cx="9.5"
                    cy="7"
                    r="4"
                />

                <path d="M17 11a4 4 0 1 0 0-8"/>

                <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
            </svg>

        </div>

        <div>

            <h1 class="brand-title">
                Payroll System
            </h1>

            <p class="brand-subtitle">
                User account administration
            </p>

        </div>

    </div>


    <div class="header-buttons">

        <a href="dashboard.php">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M3 10.5 12 3l9 7.5"/>
                <path d="M5 9.5V21h14V9.5"/>
                <path d="M9 21v-6h6v6"/>
            </svg>

            Dashboard

        </a>

    </div>

</div>


<div class="container">

    <!-- PAGE TITLE -->

    <div class="page-title">

        <div class="title-top">

            <div class="title-content">

                <div class="page-icon">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>

                        <circle
                            cx="9.5"
                            cy="7"
                            r="4"
                        />

                        <path d="M17 11a4 4 0 1 0 0-8"/>

                        <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
                    </svg>

                </div>

                <div>

                    <h2>
                        User Management
                    </h2>

                    <p>
                        Manage system accounts, roles, employee links, and access status.
                    </p>

                </div>

            </div>


            <a
                href="add_user.php"
                class="add-user-button"
            >

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M12 5v14"/>
                    <path d="M5 12h14"/>
                </svg>

                Add New User

            </a>

        </div>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if (isset($_GET["added"]) && $_GET["added"] === "1"): ?>

        <div class="success-message">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M20 6 9 17l-5-5"/>
            </svg>

            User account successfully created.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>

        <div class="success-message">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M20 6 9 17l-5-5"/>
            </svg>

            User account successfully updated.

        </div>

    <?php endif; ?>


    <!-- USER TABLE -->

    <div class="table-container">

        <div class="table-header">

            <div>

                <h3>
                    System Users
                </h3>

                <p>
                    Accounts currently registered in the payroll system.
                </p>

            </div>

        </div>


        <?php if ($result->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            User ID
                        </th>

                        <th>
                            Username
                        </th>

                        <th>
                            Full Name
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Employee
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Last Login
                        </th>

                        <th>
                            Created At
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td class="user-id">

                                <?php

                                echo htmlspecialchars(
                                    $row["User_ID"]
                                );

                                ?>

                            </td>


                            <td class="username">

                                <?php

                                echo htmlspecialchars(
                                    $row["Username"]
                                );

                                ?>

                            </td>


                            <td class="full-name">

                                <?php

                                echo htmlspecialchars(
                                    $row["Full_Name"]
                                );

                                ?>

                            </td>


                            <td class="role">

                                <?php

                                echo htmlspecialchars(
                                    $row["Role"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                if (!empty($row["Emp_ID"])) {

                                    echo "<span class='employee-name'>";

                                    echo htmlspecialchars(
                                        $row["First_Name"] .
                                        " " .
                                        $row["Last_Name"]
                                    );

                                    echo "</span>";

                                } else {

                                    echo "<span class='not-linked'>Not Linked</span>";

                                }

                                ?>

                            </td>


                            <td>

                                <?php if (
                                    $row["Status"] === "Active"
                                ): ?>

                                    <span class="status-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php

                                if (!empty($row["Last_Login"])) {

                                    echo htmlspecialchars(
                                        date(
                                            "M d, Y h:i A",
                                            strtotime(
                                                $row["Last_Login"]
                                            )
                                        )
                                    );

                                } else {

                                    echo "<span class='not-linked'>Never</span>";

                                }

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $row["Created_At"]
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <a
                                    href="edit_user.php?id=<?php echo (int)$row["User_ID"]; ?>"
                                    class="edit-button"
                                >

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                                    </svg>

                                    Edit

                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>

                        <circle
                            cx="9.5"
                            cy="7"
                            r="4"
                        />

                        <path d="M17 11a4 4 0 1 0 0-8"/>

                        <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
                    </svg>

                </div>

                <h3>
                    No Users Found
                </h3>

                <p>
                    There are currently no system users.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <a
        href="dashboard.php"
        class="back-button"
    >

        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M19 12H5"/>
            <path d="M12 19l-7-7 7-7"/>
        </svg>

        Back to Dashboard

    </a>

</div>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>