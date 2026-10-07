<?php
session_start();
include "config.php";

if(!isset($_SESSION['supervisor_id'])){
    header("Location: supervisor_login.php");
    exit();
}

$fullname = $_SESSION['fullname'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Staff Monthly Report</title>
<link rel="stylesheet" href="styles.css">
</head>

<body>

<div class="container">

    <!-- Sidebar -->

    <div class="sidebar">

        <div class="logo">
            <h2><?php echo $app['organization_name']; ?></h2>
            <p>Personnel Timesheet System</p>
        </div>

        <div class="profile">

            <div class="avatar">
                <?php echo strtoupper(substr($fullname,0,1)); ?>
            </div>

            <h3><?php echo $fullname; ?></h3>
            <p>Supervisor</p>

        </div>

        <ul>
            <li><a href="supervisor_dashboard.php">Dashboard</a></li>
            <li><a href="approvals.php">Approvals</a></li>
            <li class="active"><a href="reports.php">Reports</a></li>
            <li><a href="supervisor_logout.php">Logout</a></li>
        </ul>

    </div>

    <!-- Main -->

    <div class="main">
        <div class="topbar">
            <h1>Staff Monthly Report</h1>
        </div>

        <div class="report-box">
            <h2>Monthly Attendance & Timesheet Report</h2>
            <form method="GET">
                <label>Month</label>
                <select name="month">

                    <?php

                    for($m=1;$m<=12;$m++){

                        echo "<option value='$m'>".

                        date('F',mktime(0,0,0,$m,1))

                        ."</option>";

                    }

                    ?>

                </select>

                <label>Year</label>

                <select name="year">

                    <?php

                    $currentYear=date("Y");

                    for($y=$currentYear-5;$y<=$currentYear+5;$y++){

                        echo "<option value='$y'>$y</option>";

                    }

                    ?>

                </select>

                <div class="divider"></div>

               

                <div class="buttons">

                    <button
                    class="excel-btn"
                    formaction="monthly_report_excel.php">

                    📊 Excel

                    </button>

                    <button
                    class="pdf-btn"
                    formaction="monthly_report_pdf.php">

                    📄 PDF

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>