<?php

/* ========================================================= LOG GENERAL ACTIVITY ========================================================= */

function logActivity(
    $conn,
    $user_id,
    $user_type,
    $action_type,
    $description = ""
) {

    if (!$conn) {
        return;
    }

    if (empty($description)) {

        $description =
            "Performed " . $action_type;

    }

    $activity_type = $action_type;


    $stmt = $conn->prepare("
        INSERT INTO activity_logs
        (
            user_id,
            user_type,
            activity_type,
            activity_description,
            action_type
        )
        VALUES
        (?, ?, ?, ?, ?)
    ");


    if (!$stmt) {
        return;
    }


    $stmt->bind_param(
        "sssss",
        $user_id,
        $user_type,
        $activity_type,
        $description,
        $action_type
    );


    $stmt->execute();

    $stmt->close();

}

/* ========================================================= LOGIN ========================================================= */

function logLogin(
    $conn,
    $user_id,
    $user_type
) {

    if (!$conn) {
        return;
    }

    /* ----------------------------------------- RECORD LOGIN IN login_logs ----------------------------------------- */

    $stmt = $conn->prepare("
        INSERT INTO login_logs
        (
            user_id,
            user_type
        )
        VALUES
        (?, ?)
    ");

    if ($stmt) {

        $stmt->bind_param(
            "ss",
            $user_id,
            $user_type
        );

        $stmt->execute();

        $stmt->close();

    }

    /* ----------------------------------------- ALSO RECORD LOGIN IN activity_logs ----------------------------------------- */

    logActivity(
        $conn,
        $user_id,
        $user_type,
        "Login",
        $user_type .
        " logged into the system."
    );

}

/* ========================================================= LOGOUT ========================================================= */

function logLogout(
    $conn,
    $user_id,
    $user_type
) {

    if (!$conn) {
        return;
    }

    /* ----------------------------------------- RECORD LOGOUT IN activity_logs ----------------------------------------- */

    logActivity(
        $conn,
        $user_id,
        $user_type,
        "Logout",
        $user_type .
        " logged out of the system."
    );

}

/* ========================================================= PAGE VISIT ========================================================= */

function logPageVisit(
    $conn,
    $user_id,
    $user_type
) {

    if (!$conn) {
        return;
    }

    $page = basename(
        $_SERVER['PHP_SELF']
    );


    logActivity(
        $conn,
        $user_id,
        $user_type,
        "Page Visit",
        "Visited " . $page
    );

}

?>