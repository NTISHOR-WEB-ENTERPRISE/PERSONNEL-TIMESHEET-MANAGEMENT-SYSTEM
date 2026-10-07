<?php

session_start();

include "config.php";
include "activity_logger.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}



$admin_id = $_SESSION['admin_id'];
$fullname = $_SESSION['fullname'];
$admin_role = $_SESSION['admin_role'];

/* ==============================
   ADMIN PROFILE PHOTO
============================== */

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


/* ==============================
   ADMIN PROFILE PHOTO PATH
============================== */

$admin_profile_photo =
    "uploads/admins/default.png";


if (
    $admin_data &&
    !empty($admin_data['profile_photo'])
) {

    $photo =
        basename(
            $admin_data['profile_photo']
        );

    $photo_path =
        "uploads/admins/" . $photo;


    if (file_exists($photo_path)) {

        $admin_profile_photo =
            $photo_path;

    }

}

/* ==============================
   STAFF STATISTICS
============================== */

$total_staff = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) total
    FROM staff
"))['total'];


$total_active = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) total
    FROM staff
    WHERE status='Active'
"))['total'];


$total_inactive = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) total
    FROM staff
    WHERE status='Inactive'
"))['total'];


$total_assigned = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) total
    FROM staff
    WHERE supervisor_id IS NOT NULL
    AND supervisor_id!=''
"))['total'];


/* ==============================
   SEARCH AND FILTERS
============================== */

$search = "";
$department = "";
$status = "";

$where = "WHERE 1";


if(isset($_GET['search']) && $_GET['search']!=""){

    $search = mysqli_real_escape_string(
        $conn,
        $_GET['search']
    );

    $where .= " AND (
        fullname LIKE '%$search%'
        OR
        staff_id LIKE '%$search%'
        OR
        email LIKE '%$search%'
    )";
}


if(isset($_GET['department']) && $_GET['department']!=""){

    $department = mysqli_real_escape_string(
        $conn,
        $_GET['department']
    );

    $where .= " AND department='$department'";
}


if(isset($_GET['status']) && $_GET['status']!=""){

    $status = mysqli_real_escape_string(
        $conn,
        $_GET['status']
    );

    $where .= " AND status='$status'";
}


/* ==============================
   GET STAFF
============================== */

$staff_query = mysqli_query($conn,"

    SELECT
        staff.*,
        supervisors.fullname AS supervisor_name

    FROM staff

    LEFT JOIN supervisors
        ON staff.supervisor_id = supervisors.supervisor_id

    $where

    ORDER BY staff.created_at DESC

");


/* ==============================
   GET DEPARTMENTS
============================== */

$departments = mysqli_query($conn,"

    SELECT DISTINCT department

    FROM staff

    WHERE department IS NOT NULL
    AND department!=''

    ORDER BY department ASC

");

?>

<!DOCTYPE html>

<html>

<head>

<title>Manage Staff</title>

<link rel="stylesheet" href="styles.css">

</head>


<body>


<div class="container">


<!-- =========================
     SIDEBAR
========================= -->

<div class="sidebar">


<div class="logo">

<h2>
<?php echo $app['organization_name']; ?>
</h2>

<p>
Personnel Timesheet System
</p>

</div>


<div class="profile">

<div class="avatar">

<img
    src="<?php echo htmlspecialchars($admin_profile_photo); ?>"
    class="profile-small"
    alt="Admin Profile Photo"
    onerror="this.src='uploads/admins/default.png';"
>

</div>

<h3>
<?php echo $fullname; ?>
</h3>

<p>
<?php echo $admin_role; ?>
</p>

</div>


<ul>


<li>

<a href="admin_dashboard.php">

Dashboard

</a>

</li>


<li><a href="manage_admins.php">Manage Admins</a></li>

<li><a href="manage_supervisors.php">Manage Supervisors</a></li>

<li class="active"><a href="manage_staff.php">Manage Staff</a></li>

<li><a href="admin_reports.php">Reports</a></li>

<li><a href="admin_logout.php">Logout</a></li>

</ul>


</div>

<!-- =========================
     MAIN
========================= -->

<div class="main">


<!-- TOP BAR -->

<div class="topbar">


<div>

<h1>
Manage Staff
</h1>

<p>
Create, edit and manage staff members.
</p>

</div>


<div id="clock"></div>


</div>



<!-- =========================
     STATISTICS
========================= -->

<div class="cards">


<div class="card">

<h4>
Total Staff
</h4>

<h1>
<?php echo $total_staff; ?>
</h1>

</div>


<div class="card">

<h4>
Active
</h4>

<h1>
<?php echo $total_active; ?>
</h1>

</div>


<div class="card">

<h4>
Inactive
</h4>

<h1>
<?php echo $total_inactive; ?>
</h1>

</div>


<div class="card">

<h4>
Assigned to Supervisor
</h4>

<h1>
<?php echo $total_assigned; ?>
</h1>

</div>


</div>



<!-- =========================
     STAFF TABLE
========================= -->

<div class="history-box">


<form method="GET" class="filter-form">


<input

type="text"

name="search"

placeholder="Search Name, Staff ID or Email"

value="<?php echo htmlspecialchars($search); ?>"

>


<select name="department">


<option value="">

All Departments

</option>


<?php

while($dept = mysqli_fetch_assoc($departments)){

?>

<option

value="<?php echo htmlspecialchars($dept['department']); ?>"

<?php

if($department == $dept['department']){

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $dept['department']
);

?>

</option>

<?php

}

?>

</select>



<select name="status">


<option value="">

All Status

</option>


<option

value="Active"

<?php

if($status=="Active"){

    echo "selected";

}

?>

>

Active

</option>


<option

value="Inactive"

<?php

if($status=="Inactive"){

    echo "selected";

}

?>

>

Inactive

</option>


</select>



<button type="submit">

Search

</button>



<a

href="add_staff.php"

class="approve-btn"

>

➕ Add Staff

</a>


</form>



<!-- =========================
     TABLE
========================= -->

<table class="history-table">


<thead>

<tr>

<th>
Photo
</th>

<th>
Staff ID
</th>

<th>
Name
</th>

<th>
Department
</th>

<th>
Position
</th>

<th>
Supervisor
</th>

<th>
Status
</th>

<th>
Action
</th>

</tr>

</thead>



<tbody>


<?php


if(mysqli_num_rows($staff_query) > 0){


while($row = mysqli_fetch_assoc($staff_query)){


?>


<tr>


<!-- PHOTO -->

<td>


<?php

$photo = $row['profile_picture'];

if(empty($photo)){

    $photo = "default.png";

}

?>


<img

src="uploads/staff/<?php echo htmlspecialchars($photo); ?>"

width="50"

height="50"

style="border-radius:50%;object-fit:cover;"

>


</td>



<!-- STAFF ID -->

<td>

<?php

echo htmlspecialchars(
    $row['staff_id']
);

?>

</td>



<!-- NAME -->

<td>

<?php

echo htmlspecialchars(
    $row['fullname']
);

?>

</td>



<!-- DEPARTMENT -->

<td>

<?php

echo htmlspecialchars(
    $row['department']
);

?>

</td>



<!-- POSITION -->

<td>

<?php

echo htmlspecialchars(
    $row['position']
);

?>

</td>



<!-- SUPERVISOR -->

<td>


<?php

if(!empty($row['supervisor_name'])){

    echo htmlspecialchars(
        $row['supervisor_name']
    );

}else{

    echo "<span style='color:#999;'>Not Assigned</span>";

}

?>


</td>



<!-- STATUS -->

<td>


<span

class="status <?php echo strtolower($row['status']); ?>"

>

<?php

echo htmlspecialchars(
    $row['status']
);

?>

</span>


</td>

<!-- ACTIONS -->

<td>


<a href="view_staff.php?staff_id=<?php echo urlencode($row['staff_id']); ?>" class="view-btn">View</a>


<a
href="edit_staff.php?staff_id=<?php echo urlencode($row['staff_id']); ?>"

class="approve-btn"

>

Edit

</a>



<?php

if($row['status']=="Active"){

?>


<a

href="deactivate_staff.php?staff_id=<?php echo $row['staff_id']; ?>"

class="reject-btn"

onclick="return confirm('Deactivate this staff member?')"

>

Deactivate

</a>


<?php

}else{

?>


<a

href="activate_staff.php?staff_id=<?php echo $row['staff_id']; ?>"

class="excel-btn"

>

Activate

</a>


<?php

}

?>

</td>


</tr>


<?php

}


}else{


?>


<tr>


<td colspan="8">

No staff members found.

</td>


</tr>


<?php

}


?>


</tbody>


</table>


</div>


</div>


</div>



<script>


function updateClock(){

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}


updateClock();

setInterval(updateClock,1000);


</script>


</body>

</html>