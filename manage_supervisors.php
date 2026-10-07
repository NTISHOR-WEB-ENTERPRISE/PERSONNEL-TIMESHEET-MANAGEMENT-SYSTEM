<?php

session_start();

include "config.php";
include "activity_logger.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SESSION['admin_role'] != "Super Admin") {
    die("Access Denied");
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
   SUPERVISOR STATISTICS
============================== */

$total_supervisors = mysqli_fetch_assoc(
    mysqli_query($conn, "
        SELECT COUNT(*) AS total
        FROM supervisors
    ")
)['total'];


$total_active = mysqli_fetch_assoc(
    mysqli_query($conn, "
        SELECT COUNT(*) AS total
        FROM supervisors
        WHERE status = 'Active'
    ")
)['total'];


$total_inactive = mysqli_fetch_assoc(
    mysqli_query($conn, "
        SELECT COUNT(*) AS total
        FROM supervisors
        WHERE status = 'Inactive'
    ")
)['total'];


/* ==============================
   SEARCH
============================== */

$search = "";

$where = "WHERE 1";


if (isset($_GET['search']) && $_GET['search'] != "") {

    $search = mysqli_real_escape_string(
        $conn,
        $_GET['search']
    );

    $where .= " AND (
        fullname LIKE '%$search%'
        OR supervisor_id LIKE '%$search%'
        OR email LIKE '%$search%'
        OR department LIKE '%$search%'
    )";
}


/* ==============================
   STATUS FILTER
============================== */

$status = "";

if (isset($_GET['status']) && $_GET['status'] != "") {

    $status = mysqli_real_escape_string(
        $conn,
        $_GET['status']
    );

    $where .= " AND status = '$status'";
}


/* ==============================
   GET SUPERVISORS
============================== */

$supervisor_query = mysqli_query(
    $conn,
    "
    SELECT
        supervisors.*,

        (
            SELECT COUNT(*)
            FROM staff
            WHERE staff.supervisor_id = supervisors.supervisor_id
        ) AS assigned_staff

    FROM supervisors

    $where

    ORDER BY supervisors.created_at DESC
    "
);

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Supervisors</title>

<link rel="stylesheet" href="styles.css">

<style>

.supervisor-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 25px;

}


.supervisor-stat {

    background: white;

    padding: 22px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);

}


.supervisor-stat h4 {

    margin: 0 0 10px;

    color: #666;

}


.supervisor-stat h1 {

    margin: 0;

    color: #0056b3;

}


.supervisor-table {

    width: 100%;

    border-collapse: collapse;

}


.supervisor-table th,
.supervisor-table td {

    padding: 13px;

    border-bottom: 1px solid #eee;

    text-align: left;

}


.supervisor-table th {

    background: #0056b3;

    color: white;

}


.supervisor-photo {

    width: 45px;

    height: 45px;

    border-radius: 50%;

    object-fit: cover;

}


.status {

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

}


.status.active {

    background: #d1e7dd;

    color: #0f5132;

}


.status.inactive {

    background: #f8d7da;

    color: #842029;

}


.action-btn {

    display: inline-block;

    padding: 7px 11px;

    border-radius: 5px;

    text-decoration: none;

    font-size: 13px;

    margin: 2px;

}


.view-btn {

    background: #0d6efd;

    color: white;

}


.edit-btn {

    background: #ffc107;

    color: #212529;

}


.activate-btn {

    background: #198754;

    color: white;

}


.deactivate-btn {

    background: #dc3545;

    color: white;

}


.add-btn {

    background: #198754;

    color: white;

    padding: 10px 15px;

    border-radius: 6px;

    text-decoration: none;

}


@media(max-width:900px) {

    .supervisor-stats {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- ==============================
     SIDEBAR
============================== -->

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

<li class="active"><a href="manage_supervisors.php">Manage Supervisors</a></li>

<li><a href="manage_staff.php">Manage Staff</a></li>

<li><a href="admin_reports.php">Reports</a></li>

<li><a href="admin_logout.php">Logout</a></li>

</ul>

</div>



<!-- ==============================
     MAIN
============================== -->

<div class="main">


<div class="topbar">

<div>

<h1>
Manage Supervisors
</h1>

<p>
Create, edit and manage supervisors.
</p>

</div>


<div id="clock"></div>

</div>



<!-- ==============================
     STATISTICS
============================== -->

<div class="supervisor-stats">


<div class="supervisor-stat">

<h4>
Total Supervisors
</h4>

<h1>

<?php

echo $total_supervisors;

?>

</h1>

</div>


<div class="supervisor-stat">

<h4>
Active Supervisors
</h4>

<h1>

<?php

echo $total_active;

?>

</h1>

</div>


<div class="supervisor-stat">

<h4>
Inactive Supervisors
</h4>

<h1>

<?php

echo $total_inactive;

?>

</h1>

</div>


</div>



<!-- ==============================
     SUPERVISOR LIST
============================== -->

<div class="history-box">


<div style="
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:20px;
">


<h2>
Supervisors
</h2>


<a
href="add_supervisor.php"
class="add-btn"
>

➕ Add Supervisor

</a>


</div>



<!-- SEARCH -->

<form method="GET"
style="
display:flex;
gap:10px;
margin-bottom:20px;
flex-wrap:wrap;
">


<input

type="text"

name="search"

placeholder="Search supervisor..."

value="<?php

echo htmlspecialchars($search);

?>"

style="
padding:10px;
border:1px solid #ddd;
border-radius:5px;
"


>


<select

name="status"

style="
padding:10px;
border:1px solid #ddd;
border-radius:5px;
"


>

<option value="">

All Status

</option>


<option
value="Active"

<?php

if ($status == "Active") {

    echo "selected";

}

?>

>

Active

</option>


<option
value="Inactive"

<?php

if ($status == "Inactive") {

    echo "selected";

}

?>

>

Inactive

</option>

</select>


<button
type="submit"
class="add-btn"
>

Search

</button>


<a
href="manage_supervisors.php"
class="action-btn"
style="
background:#6c757d;
color:white;
"
>

Reset

</a>


</form>



<!-- TABLE -->

<div style="overflow-x:auto;">


<table class="supervisor-table">


<thead>

<tr>

<th>
Photo
</th>

<th>
Supervisor ID
</th>

<th>
Name
</th>

<th>
Department
</th>

<th>
Email
</th>

<th>
Assigned Staff
</th>

<th>
Status
</th>

<th>
Actions
</th>

</tr>

</thead>


<tbody>


<?php

if (
    mysqli_num_rows(
        $supervisor_query
    ) > 0
):


while (
    $row =
    mysqli_fetch_assoc(
        $supervisor_query
    )
):


$photo =
    !empty($row['profile_photo'])
    ? $row['profile_photo']
    : 'default.png';

?>


<tr>


<td>

<img

src="uploads/supervisors/<?php

echo htmlspecialchars($photo);

?>"

class="supervisor-photo"

onerror="
this.src='uploads/supervisors/default.png';
"

>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['supervisor_id']
);

?>

</td>


<td>

<strong>

<?php

echo htmlspecialchars(
    $row['fullname']
);

?>

</strong>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['department'] ?? 'N/A'
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $row['email'] ?? 'N/A'
);

?>

</td>


<td>

<strong>

<?php

echo $row['assigned_staff'];

?>

</strong>

staff

</td>


<td>


<span class="status

<?php

echo strtolower(
    $row['status']
);

?>

">

<?php

echo htmlspecialchars(
    $row['status']
);

?>

</span>


</td>


<td>


<a

href="view_supervisor.php?supervisor_id=<?php

echo urlencode(
    $row['supervisor_id']
);

?>"

class="action-btn view-btn"

>

View

</a>


<a

href="edit_supervisor.php?supervisor_id=<?php

echo urlencode(
    $row['supervisor_id']
);

?>"

class="action-btn edit-btn"

>

Edit

</a>



<?php

if (
    $row['status']
    == 'Active'
):

?>


<a

href="deactivate_supervisor.php?supervisor_id=<?php

echo urlencode(
    $row['supervisor_id']
);

?>"

class="action-btn deactivate-btn"

onclick="
return confirm(
'Deactivate this supervisor?'
);
"

>

Deactivate

</a>


<?php

else:

?>


<a

href="activate_supervisor.php?supervisor_id=<?php

echo urlencode(
    $row['supervisor_id']
);

?>"

class="action-btn activate-btn"

>

Activate

</a>


<?php

endif;

?>


</td>


</tr>


<?php

endwhile;


else:

?>


<tr>

<td
colspan="8"
style="
text-align:center;
padding:40px;
color:#777;
"
>

No supervisors found.

</td>

</tr>


<?php

endif;

?>


</tbody>

</table>

</div>

</div>

</div>

</div>



<script>

function updateClock(){

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>