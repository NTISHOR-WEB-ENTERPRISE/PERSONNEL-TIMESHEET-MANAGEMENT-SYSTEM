<?php
session_start();
include "config.php";

if (!isset($_SESSION['supervisor_id'])) {
    header("Location: supervisor_login.php");
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];
$supervisor_name = $_SESSION['fullname'];

/* ==================================
   SUPERVISOR PROFILE PHOTO
================================== */

$stmt = $conn->prepare("
    SELECT profile_photo
    FROM supervisors
    WHERE supervisor_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $supervisor_id
);

$stmt->execute();

$result = $stmt->get_result();

$supervisor_data = $result->fetch_assoc();

$stmt->close();


$profile_photo = "uploads/supervisors/default.png";


if (
    $supervisor_data &&
    !empty($supervisor_data['profile_photo'])
) {

    $photo = basename(
        $supervisor_data['profile_photo']
    );

    $photo_path =
        "uploads/supervisors/" . $photo;

    if (file_exists($photo_path)) {

        $profile_photo = $photo_path;

    }

}

/* =========================================================
   SELECTED MONTH
========================================================= */

$selected_month = $_GET['month'] ?? date('Y-m');

$month_start = $selected_month . '-01';

$month_end = date(
    'Y-m-t',
    strtotime($month_start)
);

/* =========================================================
   SELECTED STAFF
========================================================= */

$selected_staff = $_GET['staff_id'] ?? 'all';

/* =========================================================
   FETCH STAFF UNDER THIS SUPERVISOR
========================================================= */

$staff_list = [];

$stmt = $conn->prepare("
    SELECT
        staff_id,
        fullname,
        department,
        position
    FROM staff
    WHERE supervisor_id = ?
    AND status = 'Active'
    ORDER BY fullname ASC
");

$stmt->bind_param("s", $supervisor_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $staff_list[] = $row;
}

$stmt->close();


/* =========================================================
   GENERATE REPORT
========================================================= */

$show_report = isset($_GET['generate']);

$report_data = [];

$total_staff = 0;
$grand_hours = 0;
$grand_present = 0;
$grand_absent = 0;
$grand_overtime = 0;
$grand_late = 0;
$grand_early = 0;


/* =========================================================
   GET STAFF TO REPORT
========================================================= */

if ($show_report) {

    $staff_query = "
        SELECT
            staff_id,
            fullname,
            department,
            position
        FROM staff
        WHERE supervisor_id = ?
        AND status = 'Active'
    ";

    if ($selected_staff !== 'all') {

        $staff_query .= "
            AND staff_id = ?
        ";

    }

    $staff_query .= "
        ORDER BY fullname ASC
    ";

    $stmt = $conn->prepare($staff_query);

    if ($selected_staff === 'all') {

        $stmt->bind_param(
            "s",
            $supervisor_id
        );

    } else {

        $stmt->bind_param(
            "ss",
            $supervisor_id,
            $selected_staff
        );

    }

    $stmt->execute();

    $staff_result = $stmt->get_result();


    /* =====================================================
       PROCESS EACH STAFF MEMBER
    ===================================================== */

    while ($staff = $staff_result->fetch_assoc()) {

        $staff_id = $staff['staff_id'];

        $hours = 0;
        $present = 0;
        $absent = 0;
        $overtime = 0;
        $late = 0;
        $early = 0;

        /*
         * Expected working days
         */
        $expected_days = 0;


        /* =================================================
           GET STAFF WORK SCHEDULE
        ================================================= */

        $schedule = [];

        $schedule_stmt = $conn->prepare("
            SELECT
                day_name,
                start_time,
                end_time,
                status
            FROM work_schedule
            WHERE staff_id = ?
            AND supervisor_id = ?
        ");

        $schedule_stmt->bind_param(
            "ss",
            $staff_id,
            $supervisor_id
        );

        $schedule_stmt->execute();

        $schedule_result =
            $schedule_stmt->get_result();

        while ($schedule_row =
            $schedule_result->fetch_assoc()
        ) {

            $schedule[
                $schedule_row['day_name']
            ] = $schedule_row;

        }

        $schedule_stmt->close();


        /* =================================================
           GET ATTENDANCE FOR THE MONTH
        ================================================= */

        $attendance = [];

        $attendance_stmt = $conn->prepare("
            SELECT
                date,
                time_in,
                time_out,
                clock_in,
                clock_out,
                status
            FROM attendance
            WHERE staff_id = ?
            AND date BETWEEN ? AND ?
        ");

        $attendance_stmt->bind_param(
            "sss",
            $staff_id,
            $month_start,
            $month_end
        );

        $attendance_stmt->execute();

        $attendance_result =
            $attendance_stmt->get_result();

        while ($attendance_row =
            $attendance_result->fetch_assoc()
        ) {

            $attendance[
                $attendance_row['date']
            ] = $attendance_row;

        }

        $attendance_stmt->close();


        /* =================================================
           LOOP THROUGH EVERY DAY OF MONTH
        ================================================= */

        $current_date =
            new DateTime($month_start);

        $end_date =
            new DateTime($month_end);

        while ($current_date <= $end_date) {

            $date =
                $current_date->format('Y-m-d');

            $day_name =
                $current_date->format('l');


            /*
             * Get schedule for this weekday
             */

            $day_schedule =
                $schedule[$day_name] ?? null;


            /*
             * If there is no schedule,
             * don't count it as an absence.
             */

            if (!$day_schedule) {

                $current_date->modify('+1 day');

                continue;

            }


            /*
             * OFF DAY
             */

            if (
                $day_schedule['status']
                === 'Off Day'
            ) {

                $current_date->modify('+1 day');

                continue;

            }


            /*
             * LEAVE
             */

            if (
                $day_schedule['status']
                === 'Leave'
            ) {

                $current_date->modify('+1 day');

                continue;

            }


            /*
             * This is an expected working day.
             */

            $expected_days++;


            /* =================================================
               CHECK ATTENDANCE
            ================================================= */

            if (isset($attendance[$date])) {

                $record =
                    $attendance[$date];


                $time_in =
                    !empty($record['time_in'])
                    ? $record['time_in']
                    : $record['clock_in'];

                $time_out =
                    !empty($record['time_out'])
                    ? $record['time_out']
                    : $record['clock_out'];


                /*
                 * Present
                 */

                if (
                    strtolower(
                        trim($record['status'] ?? '')
                    ) === 'present'
                ) {

                    $present++;

                }


                /* =================================================
                   CALCULATE HOURS
                ================================================= */

                if (
                    !empty($time_in) &&
                    !empty($time_out)
                ) {

                    $start =
                        strtotime($time_in);

                    $end =
                        strtotime($time_out);

                    if ($end > $start) {

                        $day_hours =
                            ($end - $start) / 3600;

                        $hours += $day_hours;


                        /* =============================================
                           EXPECTED WORKING HOURS
                        ============================================= */

                        $schedule_start =
                            strtotime(
                                $day_schedule['start_time']
                            );

                        $schedule_end =
                            strtotime(
                                $day_schedule['end_time']
                            );

                        $scheduled_hours =
                            (
                                $schedule_end
                                - $schedule_start
                            ) / 3600;


                        /* =============================================
                           OVERTIME
                        ============================================= */

                        if (
                            $day_hours >
                            $scheduled_hours
                        ) {

                            $overtime +=
                                $day_hours
                                - $scheduled_hours;

                        }


                        /* =============================================
                           LATE
                        ============================================= */

                        if (
                            $start >
                            $schedule_start
                        ) {

                            $late++;

                        }


                        /* =============================================
                           EARLY
                        ============================================= */

                        if (
                            $end <
                            $schedule_end
                        ) {

                            $early++;

                        }

                    }

                }

            } else {

                /*
                 * No attendance record
                 * on an expected working day.
                 */

                $absent++;

            }


            $current_date->modify('+1 day');

        }


        /* =================================================
           ATTENDANCE PERCENTAGE
        ================================================= */

        $attendance_percentage = 0;

        if ($expected_days > 0) {

            $attendance_percentage =
                (
                    $present /
                    $expected_days
                ) * 100;

        }


        /* =================================================
           STORE REPORT
        ================================================= */

        $report_data[] = [

            'staff_id' =>
                $staff['staff_id'],

            'fullname' =>
                $staff['fullname'],

            'department' =>
                $staff['department'],

            'position' =>
                $staff['position'],

            'hours' =>
                $hours,

            'present' =>
                $present,

            'absent' =>
                $absent,

            'overtime' =>
                $overtime,

            'late' =>
                $late,

            'early' =>
                $early,

            'attendance_percentage' =>
                $attendance_percentage

        ];


        /* =================================================
           GRAND TOTALS
        ================================================= */

        $total_staff++;

        $grand_hours += $hours;

        $grand_present += $present;

        $grand_absent += $absent;

        $grand_overtime += $overtime;

        $grand_late += $late;

        $grand_early += $early;

    }

    $stmt->close();

}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Staff Monthly Attendance and Timesheet Report
</title>

<link rel="stylesheet"
      href="styles.css">

<style>

/* =========================================================
   REPORT PAGE
========================================================= */

.report-container {

    padding: 25px;

}

.report-filter {

    background: #fff;

    padding: 25px;

    border-radius: 10px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.08);

}

.report-filter h2 {

    margin-top: 0;

}

.filter-grid {

    display: grid;

    grid-template-columns:
        1fr
        1fr
        auto;

    gap: 15px;

    align-items: end;

}

.form-group {

    display: flex;

    flex-direction: column;

}

.form-group label {

    font-weight: 600;

    margin-bottom: 6px;

}

.form-group select,
.form-group input {

    padding: 11px;

    border:
        1px solid #ccc;

    border-radius: 6px;

}

.generate-btn {

    padding:
        11px 20px;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    background: #007bff;

    color: white;

    font-weight: 600;

}


/* =========================================================
   REPORT
========================================================= */

.report-result {

    background: #fff;

    padding: 30px;

    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.08);

}

.report-header {

    text-align: center;

    border-bottom:
        2px solid #222;

    padding-bottom: 20px;

    margin-bottom: 20px;

}

.report-header h2 {

    margin:
        5px 0;

}

.report-header h3 {

    margin:
        5px 0;

}


/* =========================================================
   STAFF INFO
========================================================= */

.report-info {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}

.info-box {

    background:
        #f5f5f5;

    padding: 12px;

    border-radius: 6px;

}

.info-box strong {

    display: block;

    margin-bottom: 5px;

}


/* =========================================================
   TABLE
========================================================= */

.report-table {

    width: 100%;

    border-collapse:
        collapse;

}

.report-table th,
.report-table td {

    border:
        1px solid #ccc;

    padding: 10px;

    text-align: left;

}

.report-table th {

    background:
        #f0f0f0;

    font-size: 13px;

}

.report-table td {

    font-size: 13px;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 10px;

    margin-top: 25px;

}

.summary-card {

    background:
        #f5f5f5;

    padding: 15px;

    text-align: center;

    border-radius: 7px;

}

.summary-card strong {

    display: block;

    font-size: 20px;

}


/* =========================================================
   ACTIONS
========================================================= */

.report-actions {

    margin-top: 25px;

    display: flex;

    gap: 10px;

}

.print-btn,
.pdf-btn,
.excel-btn {

    padding:
        11px 20px;

    border: none;

    border-radius: 6px;

    color: white;

    text-decoration: none;

    cursor: pointer;

}

.print-btn {

    background:
        #333;

}

.pdf-btn {

    background:
        #d9534f;

}

.excel-btn {

    background:
        #198754;

}


/* =========================================================
   PRINT
========================================================= */

@media print {

    body {

        background:
            white;

    }

    .sidebar,
    .page-header,
    .report-filter,
    .report-actions {

        display:
            none !important;

    }

    .main {

        width:
            100%;

        margin:
            0;

        padding:
            0;

    }

    .report-container {

        padding:
            0;

    }

    .report-result {

        box-shadow:
            none;

        padding:
            0;

    }

    .report-table th {

        background:
            #eee !important;

        color:
            #000 !important;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width: 768px) {

    .filter-grid {

        grid-template-columns:
            1fr;

    }

    .report-info {

        grid-template-columns:
            1fr;

    }

    .summary {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .report-result {

        overflow-x:
            auto;

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
            Timesheet System
        </p>

    </div>


    <div class="profile">

        <div class="avatar">

            <img
    src="<?php echo htmlspecialchars($profile_photo); ?>"
    class="profile-small"
    alt="Profile"
    onerror="this.src='uploads/supervisors/default.png';"
>

        </div>

        <h4>

            <?php

            echo htmlspecialchars(
                $supervisor_name
            );

            ?>

        </h4>

        <p>
            Supervisor
        </p>

    </div>


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


        <li class="active">

            <a href="reports.php">
                Reports
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


<div class="page-header">

    <div>

        <h2>
            Staff Monthly Attendance & Timesheet Report
        </h2>

    </div>

    <div id="clock"></div>

</div>


<div class="report-container">


<!-- =====================================================
     FILTER
===================================================== -->

<div class="report-filter">

    <h2>
        Generate Report
    </h2>


    <form method="GET">

        <div class="filter-grid">


            <!-- STAFF -->

            <div class="form-group">

                <label>
                    Staff
                </label>

                <select
                    name="staff_id"
                    required
                >

                    <option value="all">

                        All Staff

                    </option>


                    <?php foreach (
                        $staff_list
                        as $staff
                    ): ?>

                        <option
                            value="<?php
                            echo htmlspecialchars(
                                $staff['staff_id']
                            );
                            ?>"
                            <?php

                            echo
                                $selected_staff
                                === $staff['staff_id']
                                ? 'selected'
                                : '';

                            ?>
                        >

                            <?php

                            echo htmlspecialchars(
                                $staff['fullname']
                            );

                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- MONTH -->

            <div class="form-group">

                <label>
                    Month
                </label>

                <input
                    type="month"
                    name="month"
                    value="<?php
                    echo htmlspecialchars(
                        $selected_month
                    );
                    ?>"
                    required
                >

            </div>


            <!-- BUTTON -->

            <div>

                <input
                    type="hidden"
                    name="generate"
                    value="1"
                >

                <button
                    type="submit"
                    class="generate-btn"
                >

                    Generate Preview

                </button>

            </div>


        </div>

    </form>

</div>


<?php if ($show_report): ?>


<!-- =====================================================
     REPORT PREVIEW
===================================================== -->

<div class="report-result"
     id="printArea">


    <!-- HEADER -->

    <div class="report-header">

        <h2>

            <?php

            echo htmlspecialchars(
                $app['organization_name']
            );

            ?>

        </h2>


        <h3>

            STAFF MONTHLY ATTENDANCE AND TIMESHEET REPORT

        </h3>


        <p>

            <strong>
                Period:
            </strong>

            <?php

            echo date(
                "F Y",
                strtotime($month_start)
            );

            ?>

        </p>

    </div>


    <!-- REPORT INFORMATION -->

    <div class="report-info">


        <div class="info-box">

            <strong>
                Supervisor
            </strong>

            <?php

            echo htmlspecialchars(
                $supervisor_name
            );

            ?>

        </div>


        <div class="info-box">

            <strong>
                Staff Selection
            </strong>

            <?php

            if ($selected_staff === 'all') {

                echo "All Staff";

            } else {

                foreach (
                    $staff_list
                    as $staff
                ) {

                    if (
                        $staff['staff_id']
                        === $selected_staff
                    ) {

                        echo htmlspecialchars(
                            $staff['fullname']
                        );

                        break;

                    }

                }

            }

            ?>

        </div>


        <div class="info-box">

            <strong>
                Report Period
            </strong>

            <?php

            echo date(
                "01 M Y",
                strtotime($month_start)
            );

            ?>

            -

            <?php

            echo date(
                "t M Y",
                strtotime($month_start)
            );

            ?>

        </div>


    </div>


    <?php if (count($report_data) > 0): ?>


    <!-- =================================================
         TABLE
    ================================================== -->

    <table class="report-table">


        <thead>

            <tr>

                <th>
                    Staff ID
                </th>

                <th>
                    Staff Name
                </th>

                <th>
                    Department
                </th>

                <th>
                    Position
                </th>

                <th>
                    Hours
                </th>

                <th>
                    Present
                </th>

                <th>
                    Absent
                </th>

                <th>
                    OT
                </th>

                <th>
                    Late
                </th>

                <th>
                    Early
                </th>

                <th>
                    Attend %
                </th>

            </tr>

        </thead>


        <tbody>


        <?php foreach (
            $report_data
            as $row
        ): ?>


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

                    echo htmlspecialchars(
                        $row['position']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo number_format(
                        $row['hours'],
                        2
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo $row['present'];

                    ?>

                </td>


                <td>

                    <?php

                    echo $row['absent'];

                    ?>

                </td>


                <td>

                    <?php

                    echo number_format(
                        $row['overtime'],
                        2
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo $row['late'];

                    ?>

                </td>


                <td>

                    <?php

                    echo $row['early'];

                    ?>

                </td>


                <td>

                    <?php

                    echo number_format(
                        $row[
                            'attendance_percentage'
                        ],
                        2
                    );

                    ?>%

                </td>


            </tr>


        <?php endforeach; ?>


        </tbody>


    </table>


    <!-- =================================================
         SUMMARY
    ================================================== -->

    <div class="summary">


        <div class="summary-card">

            <strong>

                <?php

                echo $total_staff;

                ?>

            </strong>

            Staff

        </div>


        <div class="summary-card">

            <strong>

                <?php

                echo number_format(
                    $grand_hours,
                    2
                );

                ?>

            </strong>

            Total Hours

        </div>


        <div class="summary-card">

            <strong>

                <?php

                echo $grand_present;

                ?>

            </strong>

            Present

        </div>


        <div class="summary-card">

            <strong>

                <?php

                echo $grand_absent;

                ?>

            </strong>

            Absent

        </div>


        <div class="summary-card">

            <strong>

                <?php

                echo number_format(
                    $grand_overtime,
                    2
                );

                ?>

            </strong>

            Overtime Hours

        </div>


        <div class="summary-card">

            <strong>

                <?php

                echo $grand_late;

                ?>

            </strong>

            Late

        </div>


    </div>


    <!-- =================================================
         PRINT INFORMATION
    ================================================== -->

    <div style="
        margin-top:25px;
        padding-top:15px;
        border-top:1px solid #ccc;
        font-size:13px;
    ">

        <strong>
            Printed By:
        </strong>

        <?php

        echo htmlspecialchars(
            $supervisor_name
        );

        ?>

        |

        <strong>
            Supervisor ID:
        </strong>

        <?php

        echo htmlspecialchars(
            $supervisor_id
        );

        ?>

        |

        <strong>
            Date:
        </strong>

        <?php

        echo date(
            "d M Y"
        );

        ?>

        |

        <strong>
            Time:
        </strong>

        <?php

        echo date(
            "h:i A"
        );

        ?>

    </div>


    <!-- =================================================
         ACTION BUTTONS
    ================================================== -->

    <div class="report-actions">


        <button
            type="button"
            class="print-btn"
            onclick="window.print()"
        >

            🖨 Print Report

        </button>


        <a
            href="staff_monthly_attendance_report_pdf.php?staff_id=<?php echo urlencode($selected_staff); ?>&month=<?php echo urlencode($selected_month); ?>"
            class="pdf-btn"
        >

            📄 Download PDF

        </a>


        <a
            href="staff_monthly_attendance_report_excel.php?staff_id=<?php echo urlencode($selected_staff); ?>&month=<?php echo urlencode($selected_month); ?>"
            class="excel-btn"
        >

            📊 Download Excel

        </a>


    </div>


    <?php else: ?>


        <div class="no-data">

            <h3>
                No Staff Records Found
            </h3>

            <p>
                There are no active staff members
                matching your selection.
            </p>

        </div>


    <?php endif; ?>


</div>


<?php endif; ?>


</div>


</div>


</div>


<script>

function updateClock() {

    const now =
        new Date();

    document.getElementById(
        "clock"
    ).innerHTML =
        '● ' +
        now.toLocaleTimeString(
            'en-GB',
            {
                hour12:false
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