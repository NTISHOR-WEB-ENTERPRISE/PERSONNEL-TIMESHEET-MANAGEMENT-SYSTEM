<?php
session_start();
include "config.php";

/* ============================== ADMIN AUTHENTICATION ============================== */

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");
}

$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* ============================== ADMIN PROFILE PHOTO ============================== */
$admin_id = $_SESSION['admin_id'];

$stmt = $conn->prepare("
    SELECT
        fullname,
        profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $admin_id
);

$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$stmt->close();

/* ============================== ADMIN PROFILE PHOTO PATH ============================== */

$admin_profile_photo = "uploads/admins/default.png";

if (
    $admin_data &&
    !empty($admin_data['profile_photo'])
) {

    $photo = basename($admin_data['profile_photo']);
    $photo_path = "uploads/admins/" . $photo;

    if (file_exists($photo_path)) {

        $admin_profile_photo = $photo_path;
    }
}

/* ============================== FILTERS ============================== */

$month = isset($_GET['month']) && $_GET['month'] != ''
    ? $_GET['month']
    : date('Y-m');

$staff_id = isset($_GET['staff_id'])
    ? trim($_GET['staff_id'])
    : '';

/* ============================== MONTH DATES ============================== */

$start_date = $month . '-01';
$end_date = date(
    'Y-m-t',
    strtotime($start_date)
);

/* ============================== GET STAFF ============================== */

$staff_list = [];

$sql = "
    SELECT
        staff_id,
        fullname,
        department
    FROM staff
    ORDER BY fullname ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $staff_list[] = $row;
    }
}

/* ============================== STAFF CONDITION ============================== */

$staff_condition = "";

if ($staff_id != '') {

    $safe_staff_id = mysqli_real_escape_string(
        $conn,
        $staff_id
    );

    $staff_condition = "
        AND a.staff_id = '$safe_staff_id'
    ";
}

/* ============================== MONTHLY ATTENDANCE STATUS CALCULATED AUTOMATICALLY ============================== */

$attendance_data = [];

$sql = "
    SELECT
        a.staff_id,
        s.fullname,
        s.department,
        a.date,
        a.time_in,
        a.time_out,
        a.clock_in,
        a.clock_out,

        CASE
            WHEN a.clock_in IS NULL
                THEN 'Absent'

            WHEN a.clock_in > '08:00:00'
                THEN 'Late'

            ELSE 'Present'
        END AS calculated_status

    FROM attendance a

    INNER JOIN staff s
        ON s.staff_id = a.staff_id

    WHERE a.date BETWEEN '$start_date'
    AND '$end_date'

    $staff_condition

    ORDER BY a.date ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance_data[] = $row;
    }
}

/* ============================== MONTHLY HOURS CALCULATED FROM ATTENDANCE ============================== */

$hours_data = [];
$hours_staff_condition = "";

if ($staff_id != '') {

    $hours_staff_condition = "
        AND a.staff_id = '$safe_staff_id'
    ";

}

$sql = "
    SELECT
        a.staff_id,
        s.fullname,
        s.department,

        SUM(
            CASE
                WHEN a.clock_in IS NOT NULL
                     AND a.clock_out IS NOT NULL
                     AND a.clock_out > a.clock_in
                THEN
                    TIME_TO_SEC(
                        TIMEDIFF(
                            a.clock_out,
                            a.clock_in
                        )
                    ) / 3600
                ELSE 0
            END
        ) AS total_hours,

        SUM(
            CASE
                WHEN DAYOFWEEK(a.date) IN (1, 7)
                     AND a.clock_in IS NOT NULL
                     AND a.clock_out IS NOT NULL
                     AND a.clock_out > a.clock_in
                THEN
                    TIME_TO_SEC(
                        TIMEDIFF(
                            a.clock_out,
                            a.clock_in
                        )
                    ) / 3600

                WHEN a.clock_in IS NOT NULL
                     AND a.clock_out IS NOT NULL
                     AND a.clock_out > '17:00:00'
                THEN
                    TIME_TO_SEC(
                        TIMEDIFF(
                            a.clock_out,
                            '17:00:00'
                        )
                    ) / 3600

                ELSE 0
            END
        ) AS total_overtime

    FROM attendance a

    INNER JOIN staff s
        ON s.staff_id = a.staff_id

    WHERE a.date BETWEEN '$start_date'
    AND '$end_date'

    $hours_staff_condition

    GROUP BY
        a.staff_id,
        s.fullname,
        s.department

    ORDER BY s.fullname ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hours_data[] = $row;
    }
}

/* ============================== TOTAL STATISTICS ============================== */

$total_present = 0;
$total_late = 0;
$total_absent = 0;
$total_hours = 0;
$total_overtime = 0;

/* ============================== COUNT PRESENT AND LATE ============================== */

foreach ($attendance_data as $row) {

    $status = strtolower(
        trim($row['calculated_status'] ?? '')
    );

    if ($status === 'present') {

        $total_present++;

    }
    elseif ($status === 'late') {

        $total_late++;

    }

}

/* ============================== CALCULATE EXPECTED WORKING DAYS ============================== */

$working_days = 0;

$current_date = strtotime($start_date);
$last_date = strtotime($end_date);

/* Do not count future dates in the current month as absent. */

$today = strtotime(date('Y-m-d'));

if ($last_date > $today) {
    $last_date = $today;
}

while ($current_date <= $last_date) {

    $day_of_week = date('N', $current_date);

    /*
       Monday = 1
       Tuesday = 2
       Wednesday = 3
       Thursday = 4
       Friday = 5
       Saturday = 6
       Sunday = 7
    */

    if ($day_of_week <= 5) {

        $working_days++;

    }

    $current_date = strtotime(
        '+1 day',
        $current_date
    );

}


/* ============================== CALCULATE ABSENCE ============================== */

$total_absent = 0;

/* ============================== GET STAFF COUNT ============================== */

if ($staff_id != '') {
    $staff_count = 1;

} else {
    $staff_count = 0;
    $staff_count_sql = "
        SELECT COUNT(*) AS total
        FROM staff
    ";

    $staff_count_result =
        mysqli_query(
            $conn,
            $staff_count_sql
        );

    if ($staff_count_result) {

        $staff_count_row =
            mysqli_fetch_assoc(
                $staff_count_result
            );

        $staff_count =
            (int)$staff_count_row['total'];
    }
}

/* ============================== EXPECTED ATTENDANCE ============================== */

$expected_attendance =
    $working_days * $staff_count;

$days_attended =
    $total_present + $total_late;

$total_absent =
    max(
        0,
        $expected_attendance - $days_attended
    );

/* ============================== TOTAL HOURS ============================== */

foreach ($hours_data as $row) {

    $total_hours +=
        (float)$row['total_hours'];

    $total_overtime +=
        (float)($row['total_overtime'] ?? 0);

}
/* ============================== WORKING DAYS & ATTENDANCE % ============================== */

$attendance_percentage = 0;

if ($expected_attendance > 0) {
    $attendance_percentage =
        (
            $days_attended /
            $expected_attendance
        ) * 100;

}

if ($attendance_percentage > 100) {
    $attendance_percentage = 100;
}

/* ============================== MONTH NAME ============================== */

$month_name = date(
    'F Y',
    strtotime($start_date)
);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monthly Staff Report</title>
<link rel="stylesheet" href="styles.css">

<style>
.report-container {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
}

/* ================================= REPORT FILTER AREA ================================= */

.filter-box {
    display: flex;
    align-items: flex-end;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 30px;
    padding: 18px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    position: relative;
    z-index: 50;
}

.filter-box > div:not(.staff-search-wrapper) {
    min-width: 150px;
}

.filter-box label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
}

.filter-box input[type="month"] {
    height: 42px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #fff;
    font-size: 14px;
    color: #333;
    outline: none;
    box-sizing: border-box;
}

.filter-box input[type="month"]:focus {
    border-color: #0056b3;
    box-shadow:
        0 0 0 3px
        rgba(0, 86, 179, 0.10);
}

.filter-box .btn {
    height: 42px;
    padding: 0 20px;
    border: none;
    border-radius: 7px;
    background: #0056b3;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
}

.filter-box .btn:hover {
    background: #003d80;
    transform: translateY(-1px);
}

.filter-box label {
    font-weight: bold;
}

.filter-box input,
.filter-box select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    background: #0056b3;
    color: white;
    cursor: pointer;
}

.btn:hover {
    background: #003d80;
}

.stats {
    display: grid;
    grid-template-columns:
    repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: #f5f7fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.stat-card h3 {
    margin: 0;
    font-size: 25px;
    color: #0056b3;
}

.stat-card p {
    margin: 5px 0 0;
    color: #666;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: left;
}

th {
    background: #0056b3;
    color: white;
}

tr:nth-child(even) {
    background: #f8f9fa;
}

.print-btn {
    float: right;
    margin-bottom: 15px;
}

@media(max-width:900px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width:600px) {

    .stats {
        grid-template-columns: 1fr;
    }

}

@media print {

    .sidebar,
    .topbar,
    .filter-box,
    .print-btn {
        display: none !important;
    }

    .main {
        margin: 0;
        width: 100%;
    }

    .report-container {
        box-shadow: none;
    }
}

/* ================================= SEARCHABLE STAFF SELECTOR ================================= */

.staff-search-wrapper {
    position: relative;
    min-width: 320px;
}

.staff-search-wrapper label {
    display: block;
    margin-bottom: 6px;
}

.staff-search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.staff-search-box input[type="text"] {
    width: 100%;
    padding: 11px 40px 11px 13px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
}

.staff-search-box input[type="text"]:focus {
    border-color: #0056b3;
    box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.10);
}

.clear-staff {
    position: absolute;
    right: 8px;
    width: 25px;
    height: 25px;
    border: none;
    border-radius: 50%;
    background: #e9ecef;
    color: #555;
    font-size: 18px;
    line-height: 20px;
    cursor: pointer;
    display: none;
}

.clear-staff:hover {
    background: #d6d9dc;
}

.staff-results {
    position: absolute;
    top: 72px;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 7px;
    box-shadow:
        0 5px 15px rgba(0,0,0,0.12);
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.staff-option {
    padding: 12px 14px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background 0.15s ease;
}

.staff-option:last-child {
    border-bottom: none;
}

.staff-option:hover {
    background: #f0f6ff;
}

.staff-option strong {
    color: #222;
    font-size: 14px;
    font-weight: 600;
}

.staff-option span {
    color: #0056b3;
    font-size: 13px;
    font-weight: 700;
    margin-left: 5px;
}

.staff-option.all-staff {
    background: #f8f9fa;
}

.staff-option.all-staff strong {
    color: #0056b3;
}

.no-staff-result {
    padding: 15px;
    text-align: center;
    color: #777;
    font-size: 14px;
}

@media(max-width:600px) {

    .staff-search-wrapper {
        width: 100%;
        min-width: 100%;
    }

}
</style>
</head>

<body>

<div class="container">

<!-- SIDEBAR -->

<div class="sidebar">

<div class="logo">

<h2>
<?php
echo htmlspecialchars(
    $app['organization_name']
);
?>
</h2>

<p>Personnel Timesheet System</p>

</div>

<div class="profile">

<div class="avatar">
<img src="<?php echo htmlspecialchars($admin_profile_photo); ?>" class="profile-small" alt="Profile" onerror="this.onerror=null; this.src='uploads/admins/default.png';">
</div>

<h3>
<?php
echo htmlspecialchars($fullname);
?>
</h3>

<p>
<?php
echo htmlspecialchars($admin_role);
?>
</p>

</div>

    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_admins.php">Manage Admins</a></li>
        <li><a href="manage_supervisors.php">Manage Supervisors</a></li>
        <li><a href="manage_staff.php">Manage Staff</a></li>
        <li class="active"><a href="admin_reports.php">Reports</a></li>
        <li><a href="admin_logout.php">Logout</a></li>
    </ul>

</div>

<!-- MAIN -->
<div class="main">

<div class="topbar">

<div>
<h1>Monthly Staff Report</h1>
<p>Monthly attendance and working-hours summary</p>
</div>

<div id="clock"></div>

</div>

<div class="report-container">

<!-- FILTER -->
<form method="GET"
class="filter-box">

<div>
<label>Month:</label>
<br>
<input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" required>
</div>

<div class="staff-search-wrapper">
    <label>Search Staff:</label>
    <br>
    <div class="staff-search-box">
        <input type="text" id="staffSearch" placeholder="Search name or Staff ID..." autocomplete="off">
        <input type="hidden" name="staff_id" id="selectedStaffId" value="<?php echo htmlspecialchars($staff_id); ?>">
        <button type="button" class="clear-staff" id="clearStaff" title="Clear staff"> × </button>
    </div>

    <div id="staffResults" class="staff-results">

        <div class="staff-option all-staff" data-id="">
            <strong>All Staff</strong>
            <span>View all staff</span>
        </div>

        <?php foreach ($staff_list as $staff): ?>

            <div class="staff-option" data-id="<?php echo htmlspecialchars($staff['staff_id']); ?>" data-search="<?php
                    echo htmlspecialchars(
                        strtolower(
                            $staff['fullname']
                            . ' '
                            . $staff['staff_id']
                        )
                    );
                ?>"
            >

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $staff['fullname']
                    );
                    ?>
                </strong>

                <span>
                    (STID
                    <?php
                    echo htmlspecialchars(
                        $staff['staff_id']
                    );
                    ?>)
                </span>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<div style="align-self:end;">
<button type="submit" class="btn">Generate Report</button>
</div>

</form>

<!-- REPORT TITLE -->
<h2>Monthly Report — <?php echo htmlspecialchars($month_name); ?></h2>

<!-- STATISTICS -->
<div class="stats">

<div class="stat-card">

<h3>
<?php
echo $total_present;
?>
</h3>

<p>Days Present</p>

</div>

<div class="stat-card">
    <h3><?php echo $total_late; ?></h3>
    <p>Days Late</p>
</div>

<div class="stat-card">
<h3><?php echo $total_absent; ?></h3>
<p>Days Absent</p>
</div>

<div class="stat-card">

<h3>
<?php
echo number_format(
    $total_hours,
    2
);
?>
</h3>

<p>Total Hours</p>

</div>

<div class="stat-card">

<h3>
<?php
echo number_format(
    $total_overtime,
    2
);
?>
</h3>

<p>Overtime Hours</p>

</div>

</div>

<div style="margin-bottom:20px;">

<strong>Attendance Percentage:</strong>

<?php
echo number_format(
    $attendance_percentage,
    2
);
?>%
</div>

<!-- PRINT -->
<button onclick="window.print()" class="btn print-btn">🖨 Print Report</button>

<div style="clear:both;"></div>

<!-- ATTENDANCE TABLE -->

<h3>Daily Attendance</h3>

<div class="table-container">

<table>

<thead>
<tr>
<th>Date</th>
<th>Staff ID</th>
<th>Staff Name</th>
<th>Department</th>
<th>Time In</th>
<th>Time Out</th>
<th>Status</th>
</tr>
</thead>


<tbody>
<?php if (count($attendance_data) > 0): ?>
<?php foreach ($attendance_data as $row): ?>

<tr>

<td>
<?php
echo htmlspecialchars(
    date(
        'd M Y',
        strtotime($row['date'])
    )
);

?>

</td>

<td>
<?php
echo htmlspecialchars(
    $row['staff_id']
);
?>
</td>

<td>
<?php
echo htmlspecialchars(
    $row['fullname']
);
?>
</td>

<td>
<?php
echo htmlspecialchars(
    $row['department']
);
?>
</td>

<td>
<?php
$time_in =
    $row['time_in']
    ?: $row['clock_in'];

echo $time_in
    ? htmlspecialchars($time_in)
    : '-';

?>
</td>

<td>
<?php
$time_out =
    $row['time_out']
    ?: $row['clock_out'];

echo $time_out
    ? htmlspecialchars($time_out)
    : '-';
?>
</td>

<td>
<?php
echo htmlspecialchars(
    $row['calculated_status']
);
?>
</td>

</tr>
<?php endforeach; ?>
<?php else: ?>

<tr>
<td colspan="7" style="text-align:center;">No attendance records found for this month.</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>
<br>

<!-- HOURS TABLE -->
<h3>Monthly Working Hours</h3>

<div class="table-container">

<table>

<thead>
<tr>
<th>Staff ID</th>
<th>Staff Name</th>
<th>Department</th>
<th>Total Hours</th>
<th>Overtime Hours</th>
</tr>
</thead>

<tbody>
<?php if (count($hours_data) > 0): ?>
<?php foreach ($hours_data as $row): ?>

<tr>

<td>
<?php
echo htmlspecialchars(
    $row['staff_id']
);
?>

</td>

<td>
<?php
echo htmlspecialchars(
    $row['fullname']
);
?>
</td>

<td>
<?php
echo htmlspecialchars(
    $row['department']
);
?>
</td>

<td>
<?php
echo number_format(
    (float)$row['total_hours'],
    2
);
?>
</td>

<td>
<?php
echo number_format(
    (float)$row['total_overtime'],
    2
);
?>
</td>

</tr>

<?php endforeach; ?>
<?php else: ?>

<tr>
<td colspan="5" style="text-align:center;">No timesheet records found for this month.</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script>
function updateClock() {
    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString(
            'en-GB',
            {
                hour12: false
            }
        );
}
updateClock();
setInterval(
    updateClock,
    1000
);

/* ================================= STAFF SEARCH ================================= */

const staffSearch =
    document.getElementById("staffSearch");

const staffResults =
    document.getElementById("staffResults");

const selectedStaffId =
    document.getElementById("selectedStaffId");

const clearStaff =
    document.getElementById("clearStaff");

const staffOptions =
    document.querySelectorAll(
        ".staff-option"
    );

/* ================================= SHOW STAFF RESULTS ================================= */
function showStaffResults() {
    staffResults.style.display = "block";

}

/* ================================= HIDE STAFF RESULTS ================================= */
function hideStaffResults() {
    setTimeout(function() {
        staffResults.style.display = "none";
    }, 150);
}

/* ================================= SEARCH STAFF ================================= */
staffSearch.addEventListener(
    "input",
    function() {
        const search =
            this.value
                .toLowerCase()
                .trim();

        let found = false;

        staffOptions.forEach(
            function(option) {

                const searchText =
                    option.getAttribute(
                        "data-search"
                    ) || "";

                if (
                    option.classList.contains(
                        "all-staff"
                    )
                ) {

                    option.style.display =
                        "block";
                    return;
                }

                if (
                    search === "" ||
                    searchText.includes(search)
                ) {

                    option.style.display =
                        "block";
                    found = true;

                } else {
                    option.style.display =
                        "none";
                }
            }
        );

        staffResults.style.display =
            "block";

        let noResult =
            document.getElementById(
                "noStaffResult"
            );

        if (!found && search !== "") {

            if (!noResult) {

                noResult =
                    document.createElement(
                        "div"
                    );

                noResult.id =
                    "noStaffResult";

                noResult.className =
                    "no-staff-result";

                noResult.textContent =
                    "No staff found.";

                staffResults.appendChild(
                    noResult
                );
            }
        } else {
            if (noResult) {
                noResult.remove();
            }
        }

        if (search !== "") {

            clearStaff.style.display =
                "block";

        } else {

            clearStaff.style.display =
                "none";
        }
    }
);

/* ================================= SELECT STAFF ================================= */

staffOptions.forEach(
    function(option) {

        option.addEventListener(
            "click",
            function() {

                const id =
                    this.getAttribute(
                        "data-id"
                    );

                selectedStaffId.value =
                    id;

                if (
                    this.classList.contains(
                        "all-staff"
                    )
                ) {

                    staffSearch.value =
                        "";

                } else {

                    const name =
                        this.querySelector(
                            "strong"
                        ).textContent.trim();

                    const staffId =
                        this.querySelector(
                            "span"
                        ).textContent.trim();

                    staffSearch.value =
                        name + " " + staffId;
                }

                staffResults.style.display =
                    "none";

                if (id !== "") {

                    clearStaff.style.display =
                        "block";
                } else {
                    clearStaff.style.display =
                        "none";
                }
            }
        );
    }
);

/* ================================= FOCUS SEARCH ================================= */

staffSearch.addEventListener(
    "focus",
    function() {
        showStaffResults();
    }
);

/* ================================= CLEAR STAFF ================================= */

clearStaff.addEventListener(
    "click",
    function() {

        staffSearch.value = "";
        selectedStaffId.value = "";
        clearStaff.style.display =
            "none";
        staffResults.style.display =
            "block";
        staffSearch.focus();

        staffOptions.forEach(
            function(option) {

                option.style.display =
                    "block";

            }
        );

        const noResult =
            document.getElementById(
                "noStaffResult"
            );

        if (noResult) {

            noResult.remove();
        }
    }
);

/* ================================= CLOSE DROPDOWN WHEN CLICKING OUTSIDE ================================= */

document.addEventListener(
    "click",
    function(event) {

        const wrapper =
            document.querySelector(
                ".staff-search-wrapper"
            );

        if (
            wrapper &&
            !wrapper.contains(event.target)
        ) {

            staffResults.style.display =
                "none";
        }
    }
);

/* ================================= RESTORE SELECTED STAFF AFTER LOAD ================================= */

if (selectedStaffId.value !== "") {

    staffOptions.forEach(
        function(option) {

            if (
                option.getAttribute(
                    "data-id"
                ) === selectedStaffId.value
            ) {

                const name =
                    option.querySelector(
                        "strong"
                    ).textContent.trim();

                const staffId =
                    option.querySelector(
                        "span"
                    ).textContent.trim();

                staffSearch.value =
                    name + " " + staffId;

                clearStaff.style.display =
                    "block";
            }
        }
    );
}

/* ================================= CLOCK ================================= */

function updateClock() {

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString(
            'en-GB',
            {
                hour12: false
            }
        );
}
updateClock();
setInterval(
    updateClock,
    1000
);
</script>

</body>
</html>