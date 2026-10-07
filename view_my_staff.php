<?php
session_start();
include "config.php";

/* =========================================================
   CHECK SUPERVISOR LOGIN
========================================================= */
if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];


/* =========================================================
   FETCH SUPERVISOR INFORMATION
========================================================= */
$stmt = $conn->prepare("
    SELECT *
    FROM supervisors
    WHERE supervisor_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Supervisor not found.");
}

$supervisor = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   SUPERVISOR PROFILE PHOTO
========================================================= */

if (
    !empty($supervisor['profile_photo']) &&
    file_exists(
        "uploads/supervisors/" .
        $supervisor['profile_photo']
    )
) {

    $profile_photo =
        "uploads/supervisors/" .
        $supervisor['profile_photo'];

} else {

    $profile_photo =
        "uploads/supervisors/default.png";
}


/* =========================================================
   SEARCH
========================================================= */

$search = trim(
    $_GET['search'] ?? ''
);


/* =========================================================
   PAGINATION
========================================================= */

$records_per_page = 10;

$page = isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$offset =
    ($page - 1) *
    $records_per_page;


/* =========================================================
   COUNT STAFF
========================================================= */

if ($search !== '') {

    $search_term =
        "%" . $search . "%";

    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM staff
        WHERE supervisor_id = ?
        AND (
            staff_id LIKE ?
            OR fullname LIKE ?
            OR email LIKE ?
            OR department LIKE ?
            OR position LIKE ?
        )
    ");

    if (!$count_stmt) {
        die(
            "Count query failed: " .
            $conn->error
        );
    }

    $count_stmt->bind_param(
        "ssssss",
        $supervisor_id,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term
    );

} else {

    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM staff
        WHERE supervisor_id = ?
    ");

    if (!$count_stmt) {
        die(
            "Count query failed: " .
            $conn->error
        );
    }

    $count_stmt->bind_param(
        "s",
        $supervisor_id
    );
}


$count_stmt->execute();

$count_result =
    $count_stmt->get_result();

$count_row =
    $count_result->fetch_assoc();

$total_staff =
    (int)$count_row['total'];

$count_stmt->close();


$total_pages =
    max(
        1,
        ceil(
            $total_staff /
            $records_per_page
        )
    );


/* =========================================================
   FETCH STAFF
========================================================= */

if ($search !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            staff_id,
            fullname,
            email,
            phone,
            department,
            position,
            status,
            created_at,
            profile_picture,
            supervisor_id
        FROM staff
        WHERE supervisor_id = ?
        AND (
            staff_id LIKE ?
            OR fullname LIKE ?
            OR email LIKE ?
            OR department LIKE ?
            OR position LIKE ?
        )
        ORDER BY fullname ASC
        LIMIT ? OFFSET ?
    ");

    if (!$stmt) {
        die(
            "Staff query failed: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "ssssssii",
        $supervisor_id,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $records_per_page,
        $offset
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            id,
            staff_id,
            fullname,
            email,
            phone,
            department,
            position,
            status,
            created_at,
            profile_picture,
            supervisor_id
        FROM staff
        WHERE supervisor_id = ?
        ORDER BY fullname ASC
        LIMIT ? OFFSET ?
    ");

    if (!$stmt) {
        die(
            "Staff query failed: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "sii",
        $supervisor_id,
        $records_per_page,
        $offset
    );
}


$stmt->execute();

$staff_result =
    $stmt->get_result();


/* =========================================================
   STAFF PROFILE PICTURE FUNCTION
========================================================= */

function getStaffPhoto($profile_picture)
{
    if (
        !empty($profile_picture) &&
        file_exists(
            "uploads/staff/" .
            $profile_picture
        )
    ) {

        return
            "uploads/staff/" .
            $profile_picture;
    }

    return "uploads/staff/default.png";
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
    My Staff | PTSMS
</title>

<link
    rel="stylesheet"
    href="styles.css"
>


<style>

/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:25px;
}

.page-header h2 {

    margin:0;
}

.page-header p {

    margin:5px 0 0;

    color:#777;
}


/* =========================================================
   SUMMARY
========================================================= */

.staff-summary {

    display:flex;

    gap:20px;

    margin-bottom:20px;
}

.summary-card {

    background:#fff;

    padding:20px;

    border-radius:10px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

    min-width:180px;
}

.summary-card .number {

    font-size:28px;

    font-weight:bold;

    color:#0d6efd;
}

.summary-card .label {

    color:#777;

    margin-top:5px;
}


/* =========================================================
   SEARCH
========================================================= */

.search-box {

    background:#fff;

    padding:20px;

    border-radius:10px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

    margin-bottom:20px;
}

.search-form {

    display:flex;

    gap:10px;
}

.search-input {

    flex:1;

    padding:12px 15px;

    border:1px solid #ddd;

    border-radius:6px;

    font-size:14px;

    outline:none;
}

.search-input:focus {

    border-color:#0d6efd;
}

.search-btn {

    background:#0d6efd;

    color:#fff;

    border:none;

    padding:12px 22px;

    border-radius:6px;

    cursor:pointer;

    font-weight:bold;
}

.search-btn:hover {

    background:#0b5ed7;
}

.clear-btn {

    background:#6c757d;

    color:#fff;

    text-decoration:none;

    padding:12px 20px;

    border-radius:6px;

    display:flex;

    align-items:center;
}


/* =========================================================
   TABLE CARD
========================================================= */

.staff-table-card {

    background:#fff;

    border-radius:10px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.07);

    overflow:hidden;
}

.table-header {

    padding:20px;

    border-bottom:1px solid #eee;
}

.table-header h3 {

    margin:0;
}

.table-wrapper {

    overflow-x:auto;
}


/* =========================================================
   TABLE
========================================================= */

.staff-table {

    width:100%;

    border-collapse:collapse;
}

.staff-table th {

    background:#f8f9fa;

    padding:14px;

    text-align:left;

    font-size:13px;

    color:#555;

    white-space:nowrap;
}

.staff-table td {

    padding:14px;

    border-top:1px solid #eee;

    vertical-align:middle;
}

.staff-table tr:hover {

    background:#fafafa;
}


/* =========================================================
   STAFF INFORMATION
========================================================= */

.staff-info {

    display:flex;

    align-items:center;

    gap:12px;
}

.staff-photo {

    width:45px;

    height:45px;

    border-radius:50%;

    object-fit:cover;

    border:2px solid #eee;
}

.staff-name {

    font-weight:bold;

    color:#222;
}

.staff-id {

    font-size:12px;

    color:#777;

    margin-top:3px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:inline-block;

    padding:5px 11px;

    border-radius:20px;

    font-size:12px;

    font-weight:bold;

    text-transform:capitalize;
}

.status.active {

    background:#d1e7dd;

    color:#0f5132;
}

.status.inactive {

    background:#f8d7da;

    color:#842029;
}


/* =========================================================
   VIEW BUTTON
========================================================= */

.view-btn {

    display:inline-block;

    padding:8px 14px;

    background:#0d6efd;

    color:#fff;

    text-decoration:none;

    border-radius:5px;

    font-size:13px;

    font-weight:bold;
}

.view-btn:hover {

    background:#0b5ed7;
}


/* =========================================================
   NO STAFF
========================================================= */

.no-staff {

    text-align:center;

    padding:50px 20px;

    color:#777;
}

.no-staff-icon {

    font-size:45px;

    margin-bottom:10px;
}


/* =========================================================
   PAGINATION
========================================================= */

.pagination {

    display:flex;

    justify-content:center;

    gap:6px;

    padding:20px;
}

.pagination a {

    text-decoration:none;

    padding:8px 13px;

    border:1px solid #ddd;

    border-radius:5px;

    color:#333;

    background:#fff;
}

.pagination a:hover {

    background:#f0f0f0;
}

.pagination a.active {

    background:#0d6efd;

    color:#fff;

    border-color:#0d6efd;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:700px) {

    .page-header {

        flex-direction:column;

        align-items:flex-start;

        gap:10px;
    }

    .staff-summary {

        flex-direction:column;
    }

    .search-form {

        flex-direction:column;
    }

    .search-btn,
    .clear-btn {

        justify-content:center;

        text-align:center;
    }

}

</style>

</head>


<body>

<div class="container">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <div class="logo">

        <h2>

            <?php
            echo htmlspecialchars(
                $app['organization_name']
            );
            ?>

        </h2>

        <p>
            Personnel Timesheet System
        </p>

    </div>


    <!-- PROFILE -->

    <div class="profile">

        <div class="avatar">

            <img
                src="<?php
                    echo htmlspecialchars(
                        $profile_photo
                    );
                ?>"
                class="profile-small"
                alt="Profile Photo"
                onerror="
                    this.src=
                    'uploads/supervisors/default.png';
                "
            >

        </div>


        <h3>

            <?php
            echo htmlspecialchars(
                $supervisor['fullname']
            );
            ?>

        </h3>


        <p>
            Supervisor
        </p>

    </div>


    <!-- MENU -->

    <ul>

        <li>

            <a href="supervisor_dashboard.php">
                Dashboard
            </a>

        </li>


        <li>

            <a href="approvals.php">
                Approvals
            </a>

        </li>


        <li>

            <a href="task_report_approvals.php">
                Task Reports
            </a>

        </li>

        <li>

            <a href="supervisor_timesheet_approvals.php">
                Approve Timesheet
            </a>

        </li>

        <li class="active">

            <a href="view_my_staff.php">
                My Staff
            </a>

        </li>


        <li>

            <a href="reports.php">
                Reports
            </a>

        </li>


        <li>

            <a href="supervisor_profile.php">
                My Profile
            </a>

        </li>


        <li>

            <a href="supervisor_logout.php">
                Logout
            </a>

        </li>

    </ul>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <div>

            <h1>
                My Staff
            </h1>

            <p>
                View and search staff assigned to you
            </p>

        </div>


        <div id="clock"></div>

    </div>


    <!-- =================================================
         HEADER
    ================================================= -->

    <div class="page-header">

        <div>

            <h2>
                Staff Directory
            </h2>

            <p>
                Staff members currently assigned to you
            </p>

        </div>

    </div>


    <!-- =================================================
         SUMMARY
    ================================================= -->

    <div class="staff-summary">

        <div class="summary-card">

            <div class="number">

                <?php
                echo $total_staff;
                ?>

            </div>

            <div class="label">
                Total Staff
            </div>

        </div>

    </div>


    <!-- =================================================
         SEARCH
    ================================================= -->

    <div class="search-box">

        <form
            method="GET"
            action="view_my_staff.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by Staff ID, name, email, department or position..."
                value="<?php
                    echo htmlspecialchars(
                        $search
                    );
                ?>"
            >


            <button
                type="submit"
                class="search-btn"
            >

                🔍 Search

            </button>


            <?php if ($search !== '') { ?>

                <a
                    href="view_my_staff.php"
                    class="clear-btn"
                >
                    Clear
                </a>

            <?php } ?>

        </form>

    </div>


    <!-- =================================================
         STAFF TABLE
    ================================================= -->

    <div class="staff-table-card">


        <div class="table-header">

            <h3>

                <?php

                if ($search !== '') {

                    echo "Search Results";

                } else {

                    echo "My Staff";

                }

                ?>

            </h3>

        </div>


        <?php if ($staff_result->num_rows > 0) { ?>


            <div class="table-wrapper">

                <table class="staff-table">


                    <thead>

                        <tr>
                            <th>Staff</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    while (
                        $row =
                        $staff_result->fetch_assoc()
                    ) {

                        $staff_photo =
                            getStaffPhoto(
                                $row['profile_picture']
                            );

                    ?>


                        <tr>


                            <!-- STAFF -->

                            <td>

                                <div class="staff-info">


                                    <img
                                        src="<?php
                                            echo htmlspecialchars(
                                                $staff_photo
                                            );
                                        ?>"
                                        class="staff-photo"
                                        alt="Staff Photo"
                                        onerror="
                                            this.src=
                                            'uploads/staff/default.png';
                                        "
                                    >


                                    <div>

                                        <div class="staff-name">

                                            <?php
                                            echo htmlspecialchars(
                                                $row['fullname']
                                            );
                                            ?>

                                        </div>


                                        <div class="staff-id">

                                            ID:
                                            <?php
                                            echo htmlspecialchars(
                                                $row['staff_id']
                                            );
                                            ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['email']
                                    ?? 'Not provided'
                                );

                                ?>

                            </td>


                            <!-- DEPARTMENT -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['department']
                                    ?? 'Not assigned'
                                );

                                ?>

                            </td>


                            <!-- POSITION -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row['position']
                                    ?? 'Not provided'
                                );

                                ?>

                            </td>


                            <!-- VIEW -->

                            <td>
    <?php
    echo htmlspecialchars(
        $row['phone'] ?? 'Not provided'
    );
    ?>
</td>

                            <!-- STATUS -->

                            <td>

                                <?php

                                $status =
                                    strtolower(
                                        $row['status']
                                        ?? 'active'
                                    );

                                ?>


                                <span
                                    class="status
                                    <?php
                                    echo
                                        $status === 'active'
                                        ? 'active'
                                        : 'inactive';
                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $row['status']
                                        ?? 'Active'
                                    );

                                    ?>

                                </span>

                            </td>


                             </tr>


                    <?php } ?>


                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 PAGINATION
            ================================================= -->

            <?php

            if ($total_pages > 1) {

            ?>

                <div class="pagination">


                    <?php if ($page > 1) { ?>

                        <a
                            href="?search=<?php
                                echo urlencode($search);
                            ?>&page=<?php
                                echo $page - 1;
                            ?>"
                        >

                            ← Previous

                        </a>

                    <?php } ?>


                    <?php

                    for (
                        $i = 1;
                        $i <= $total_pages;
                        $i++
                    ) {

                    ?>

                        <a
                            href="?search=<?php
                                echo urlencode($search);
                            ?>&page=<?php
                                echo $i;
                            ?>"
                            class="<?php
                                echo
                                    $i == $page
                                    ? 'active'
                                    : '';
                            ?>"
                        >

                            <?php
                            echo $i;
                            ?>

                        </a>

                    <?php } ?>


                    <?php if ($page < $total_pages) { ?>

                        <a
                            href="?search=<?php
                                echo urlencode($search);
                            ?>&page=<?php
                                echo $page + 1;
                            ?>"
                        >

                            Next →

                        </a>

                    <?php } ?>


                </div>

            <?php } ?>


        <?php } else { ?>


            <!-- =================================================
                 NO STAFF
            ================================================= -->

            <div class="no-staff">


                <div class="no-staff-icon">
                    👥
                </div>


                <?php if ($search !== '') { ?>


                    <h3>
                        No Staff Found
                    </h3>


                    <p>

                        No staff member matching

                        "<strong>

                            <?php
                            echo htmlspecialchars(
                                $search
                            );
                            ?>

                        </strong>"

                        was found under your supervision.

                    </p>


                    <a
                        href="view_my_staff.php"
                        class="view-btn"
                    >

                        View All My Staff

                    </a>


                <?php } else { ?>


                    <h3>
                        No Staff Assigned
                    </h3>


                    <p>

                        There are currently no staff
                        members assigned to you.

                    </p>


                <?php } ?>


            </div>


        <?php } ?>


    </div>


</div>


</div>


<!-- =====================================================
     CLOCK
===================================================== -->

<script>

function updateClock() {

    const clock =
        document.getElementById("clock");

    if (clock) {

        clock.innerHTML =
            new Date().toLocaleTimeString();

    }

}

updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>

<?php

$stmt->close();

?>