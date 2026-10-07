<?php


/* =====================================================
   CREATE STAFF NOTIFICATION
===================================================== */

function notifyStaff($conn, $staff_id, $message){

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            staff_id,
            message,
            is_read,
            status
        )
        VALUES
        (
            ?,
            ?,
            0,
            'Unread'
        )
    ");

    $stmt->bind_param("ss", $staff_id, $message);

    return $stmt->execute();
}



/* =====================================================
   CREATE SUPERVISOR NOTIFICATION
===================================================== */

function notifySupervisor($conn, $supervisor_id, $message){

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            supervisor_id,
            message,
            is_read,
            status
        )
        VALUES
        (
            ?,
            ?,
            0,
            'Unread'
        )
    ");

    $stmt->bind_param("ss", $supervisor_id, $message);

    return $stmt->execute();
}



/* =====================================================
   CREATE ADMIN NOTIFICATION
===================================================== */

function notifyAdmin($conn, $admin_id, $message){

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            admin_id,
            message,
            is_read,
            status
        )
        VALUES
        (
            ?,
            ?,
            0,
            'Unread'
        )
    ");

    $stmt->bind_param("ss", $admin_id, $message);

    return $stmt->execute();
}



/* =====================================================
   GET STAFF NOTIFICATIONS
===================================================== */

function getStaffNotifications($conn, $staff_id, $limit = 20){

    $limit = intval($limit);

    $stmt = $conn->prepare("
        SELECT *
        FROM notifications
        WHERE staff_id = ?
        ORDER BY created_at DESC
        LIMIT $limit
    ");

    $stmt->bind_param("s", $staff_id);

    $stmt->execute();

    return $stmt->get_result();
}



/* =====================================================
   GET SUPERVISOR NOTIFICATIONS
===================================================== */

function getSupervisorNotifications($conn, $supervisor_id, $limit = 20){

    $limit = intval($limit);

    $stmt = $conn->prepare("
        SELECT *
        FROM notifications
        WHERE supervisor_id = ?
        ORDER BY created_at DESC
        LIMIT $limit
    ");

    $stmt->bind_param("s", $supervisor_id);

    $stmt->execute();

    return $stmt->get_result();
}



/* =====================================================
   COUNT STAFF UNREAD NOTIFICATIONS
===================================================== */

function countStaffNotifications($conn, $staff_id){

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE staff_id = ?
        AND is_read = 0
    ");

    $stmt->bind_param("s", $staff_id);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return $row['total'];
}



/* =====================================================
   COUNT SUPERVISOR UNREAD NOTIFICATIONS
===================================================== */

function countSupervisorNotifications($conn, $supervisor_id){

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE supervisor_id = ?
        AND is_read = 0
    ");

    $stmt->bind_param("s", $supervisor_id);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return $row['total'];
}



/* =====================================================
   MARK ONE NOTIFICATION AS READ
===================================================== */

function markNotificationRead($conn, $notification_id){

    $notification_id = intval($notification_id);

    return mysqli_query($conn, "
        UPDATE notifications
        SET
            is_read = 1,
            status = 'Read'
        WHERE id = '$notification_id'
    ");
}



/* =====================================================
   MARK ONE SUPERVISOR NOTIFICATION AS READ
===================================================== */

function markSupervisorNotificationRead(
    $conn,
    $notification_id,
    $supervisor_id
){

    $stmt = $conn->prepare("
        UPDATE notifications
        SET
            is_read = 1,
            status = 'Read'
        WHERE id = ?
        AND supervisor_id = ?
    ");

    $stmt->bind_param(
        "is",
        $notification_id,
        $supervisor_id
    );

    return $stmt->execute();
}



/* =====================================================
   MARK ALL STAFF NOTIFICATIONS AS READ
===================================================== */

function markAllStaffNotificationsRead($conn, $staff_id){

    $stmt = $conn->prepare("
        UPDATE notifications
        SET
            is_read = 1,
            status = 'Read'
        WHERE staff_id = ?
        AND is_read = 0
    ");

    $stmt->bind_param("s", $staff_id);

    return $stmt->execute();
}



/* =====================================================
   MARK ALL SUPERVISOR NOTIFICATIONS AS READ
===================================================== */

function markAllSupervisorNotificationsRead(
    $conn,
    $supervisor_id
){

    $stmt = $conn->prepare("
        UPDATE notifications
        SET
            is_read = 1,
            status = 'Read'
        WHERE supervisor_id = ?
        AND is_read = 0
    ");

    $stmt->bind_param("s", $supervisor_id);

    return $stmt->execute();
}

?>