<?php
include "config.php";


/* Add notification */
function addNotification($conn, $receiver_id, $receiver_type, $message)
{

    if($receiver_type == "staff"){

        $stmt = $conn->prepare(
            "INSERT INTO notifications 
            (staff_id, message, is_read, status)
            VALUES (?, ?, 0, 'Unread')"
        );

    }
    elseif($receiver_type == "supervisor"){

        $stmt = $conn->prepare(
            "INSERT INTO notifications 
            (supervisor_id, message, is_read, status)
            VALUES (?, ?, 0, 'Unread')"
        );

    }
    elseif($receiver_type == "admin"){

        $stmt = $conn->prepare(
            "INSERT INTO notifications 
            (admin_id, message, is_read, status)
            VALUES (?, ?, 0, 'Unread')"
        );

    }


    $stmt->bind_param("ss", $receiver_id, $message);

    return $stmt->execute();

}



/* Get staff notifications */
function getStaffNotifications($conn, $staff_id, $limit = 20)
{

    $stmt = $conn->prepare(
        "SELECT * FROM notifications
         WHERE staff_id = ?
         ORDER BY created_at DESC
         LIMIT ?"
    );


    $stmt->bind_param("si", $staff_id, $limit);

    $stmt->execute();

    return $stmt->get_result();

}



/* Count unread notifications */
function countUnreadNotifications($conn, $staff_id)
{

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS unread 
         FROM notifications
         WHERE staff_id = ?
         AND is_read = 0"
    );


    $stmt->bind_param("s",$staff_id);

    $stmt->execute();


    $result = $stmt->get_result()->fetch_assoc();


    return $result['unread'];

}



/* Mark one notification as read */
function markAsRead($conn,$notification_id)
{

    $stmt = $conn->prepare(
        "UPDATE notifications 
         SET is_read = 1,
         status='Read'
         WHERE id=?"
    );


    $stmt->bind_param("i",$notification_id);


    return $stmt->execute();

}



/* Mark all staff notifications as read */
function markAllNotificationsRead($conn,$staff_id)
{

    $stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1,
         status='Read'
         WHERE staff_id=?"
    );


    $stmt->bind_param("s",$staff_id);


    return $stmt->execute();

}

?>