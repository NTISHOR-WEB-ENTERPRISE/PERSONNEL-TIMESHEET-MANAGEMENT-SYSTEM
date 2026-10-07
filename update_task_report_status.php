<?php

session_start();

include "config.php";
include "notification_function.php";


if(!isset($_SESSION['supervisor_id'])){

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];



if(isset($_GET['id']) && isset($_GET['action'])){


    $report_id = $_GET['id'];
    $action = $_GET['action'];



    // GET REPORT OWNER

    $query = mysqli_query($conn,"
    SELECT staff_id
    FROM task_reports
    WHERE id='$report_id'
    ");


    $report = mysqli_fetch_assoc($query);


    if(!$report){

        die("Report not found");

    }


    $staff_id = $report['staff_id'];



    // APPROVE

    if($action=="approve"){


        mysqli_query($conn,"
        UPDATE task_reports

        SET

        status='Approved',

        approved_by='$supervisor_id',

        approved_at=NOW(),

        reviewed_at=NOW()

        WHERE id='$report_id'

        ");



        notifyStaff(
    $conn,
    $staff_id,
    "Your task report has been approved."
);



    }





    // REJECT

    if($action=="reject"){



        mysqli_query($conn,"
        UPDATE task_reports

        SET

        status='Rejected',

        approved_by='$supervisor_id',

        reviewed_at=NOW()

        WHERE id='$report_id'

        ");



        notifyStaff(
    $conn,
    $staff_id,
    "Your task report has been rejected."
);


    }



}



header("Location: task_report_approvals.php");

exit();


?>