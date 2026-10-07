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

$fullname = $_SESSION['fullname'];
$current_admin = $_SESSION['admin_id'];

/* =====================================================
   GET CURRENT ADMIN PROFILE PHOTO
===================================================== */

$admin_profile_photo = "uploads/admins/default.png";

$stmt = $conn->prepare("
    SELECT fullname, profile_photo
    FROM admins
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $current_admin);
$stmt->execute();

$profile_result = $stmt->get_result();
$admin_data = $profile_result->fetch_assoc();

$stmt->close();

if ($admin_data) {

    if (!empty($admin_data['fullname'])) {
        $fullname = $admin_data['fullname'];
    }

    if (!empty($admin_data['profile_photo'])) {

        $photo = basename($admin_data['profile_photo']);
        $photo_path = "uploads/admins/" . $photo;

        if (file_exists($photo_path)) {
            $admin_profile_photo = $photo_path;
        }
    }
}


/* =====================================================
   GET ADMIN TO EDIT
===================================================== */

if (!isset($_GET['id'])) {
    header("Location: manage_admins.php");
    exit();
}

$id = (int)$_GET['id'];

$query = mysqli_query($conn, "
    SELECT *
    FROM admins
    WHERE id='$id'
");

if (mysqli_num_rows($query) == 0) {
    die("Administrator not found.");
}

$admin = mysqli_fetch_assoc($query);

$message = "";
$error = "";


/* =====================================================
   UPDATE ADMIN
===================================================== */

if (isset($_POST['update'])) {

    $admin_id      = $_POST['admin_id'];
    $name          = $_POST['fullname'];
    $email         = $_POST['email'];
    $phone         = $_POST['phone'];
    $address       = $_POST['address'];
    $gender        = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $position      = $_POST['position'];
    $role          = $_POST['role'];
    $status        = $_POST['status'];

    /* Keep existing photo */
    $photo = $admin['profile_photo'];


    /* =================================================
       PROFILE PHOTO
    ================================================= */

    if (!empty($_FILES['profile_photo']['name'])) {

        $extension = strtolower(
            pathinfo(
                $_FILES['profile_photo']['name'],
                PATHINFO_EXTENSION
            )
        );

        $allowed = array("jpg", "jpeg", "png");

        if (in_array($extension, $allowed)) {

            $photo = time() . "_" . basename(
                $_FILES['profile_photo']['name']
            );

            move_uploaded_file(
                $_FILES['profile_photo']['tmp_name'],
                "uploads/admins/" . $photo
            );

        } else {

            $error = "Invalid profile photo format. Only JPG, JPEG and PNG are allowed.";
        }
    }


    /* =================================================
       UPDATE DATABASE
    ================================================= */

    if ($error == "") {

        mysqli_query($conn, "

            UPDATE admins SET

                admin_id='$admin_id',
                fullname='$name',
                email='$email',
                phone='$phone',
                address='$address',
                gender='$gender',
                date_of_birth='$date_of_birth',
                position='$position',
                role='$role',
                status='$status',
                profile_photo='$photo'

            WHERE id='$id'

        ");


        /* =================================================
           ACTIVITY LOG
        ================================================= */

        logActivity(
            $conn,
            $current_admin,
            "Admin",
            "Edit Admin",
            "Updated administrator " . $name
        );


        $message = "Administrator updated successfully.";


        /* Reload updated administrator */

        $query = mysqli_query($conn, "
            SELECT *
            FROM admins
            WHERE id='$id'
        ");

        $admin = mysqli_fetch_assoc($query);
    }
}

?>

<!DOCTYPE html>
<html>

<head>

<title>Edit Administrator</title>

<link rel="stylesheet" href="styles.css">

<style>

/* =====================================================
   EDIT ADMIN PAGE
===================================================== */

.edit-admin-page {
    max-width: 1200px;
    margin: 0 auto;
}


/* =====================================================
   PAGE HEADER
===================================================== */

.edit-page-header {
    margin-bottom: 25px;
}

.edit-page-header h1 {
    margin-bottom: 5px;
}

.edit-page-header p {
    color: #777;
    margin: 0;
}


/* =====================================================
   MAIN EDIT CARD
===================================================== */

.admin-edit-card {
    background: #fff;
    border-radius: 14px;
    padding: 30px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.08);
}


/* =====================================================
   PROFILE + INFORMATION LAYOUT
===================================================== */

.profile-information-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 35px;
    align-items: start;
}


/* =====================================================
   PROFILE PANEL
===================================================== */

.admin-profile-panel {
    background: #f7f9fc;
    border: 1px solid #e5e8ed;
    border-radius: 14px;
    padding: 25px;
    text-align: center;
}

.admin-profile-panel img {
    width: 145px;
    height: 145px;
    object-fit: cover;
    border-radius: 50%;
    border: 5px solid #fff;
    box-shadow: 0 3px 12px rgba(0,0,0,0.15);
}

.admin-profile-panel h2 {
    margin: 18px 0 5px;
    font-size: 20px;
}

.admin-profile-panel .admin-id {
    color: #777;
    font-size: 14px;
    margin-bottom: 18px;
}

.admin-profile-panel .role-badge {
    display: inline-block;
    padding: 7px 15px;
    border-radius: 20px;
    background: #e8f0ff;
    color: #2457c5;
    font-size: 13px;
    font-weight: 600;
}

.photo-upload-box {
    margin-top: 22px;
    text-align: left;
}

.photo-upload-box label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}


/* =====================================================
   SECTION
===================================================== */

.form-section {
    margin-bottom: 30px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    margin-bottom: 20px;
    border-bottom: 1px solid #e5e8ed;
}

.section-heading h2 {
    margin: 0;
    font-size: 18px;
}

.section-heading span {
    font-size: 20px;
}


/* =====================================================
   FORM GRID
===================================================== */

.edit-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.edit-form-group {
    display: flex;
    flex-direction: column;
}

.edit-form-group.full-width {
    grid-column: 1 / -1;
}

.edit-form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.edit-form-group input,
.edit-form-group select,
.edit-form-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #d9dde3;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: 0.2s;
}

.edit-form-group input:focus,
.edit-form-group select:focus,
.edit-form-group textarea:focus {
    border-color: #2864d7;
    box-shadow: 0 0 0 3px rgba(40,100,215,0.08);
}

.edit-form-group textarea {
    resize: vertical;
}

.readonly-field {
    background: #f4f5f7 !important;
    color: #777;
    cursor: not-allowed;
}


/* =====================================================
   ACCOUNT INFO
===================================================== */

.account-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.account-info-box {
    background: #f7f9fc;
    border: 1px solid #e5e8ed;
    border-radius: 10px;
    padding: 15px;
}

.account-info-box small {
    display: block;
    color: #777;
    margin-bottom: 5px;
}

.account-info-box strong {
    font-size: 14px;
}


/* =====================================================
   STATUS
===================================================== */

.status-active {
    color: #18864b;
    font-weight: 700;
}

.status-inactive {
    color: #c0392b;
    font-weight: 700;
}


/* =====================================================
   BUTTONS
===================================================== */

.edit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 25px;
    margin-top: 25px;
    border-top: 1px solid #e5e8ed;
}

.edit-actions button,
.edit-actions a {
    border: none;
    border-radius: 8px;
    padding: 12px 22px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 900px) {

    .profile-information-layout {
        grid-template-columns: 1fr;
    }

    .account-info-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 650px) {

    .edit-form-grid {
        grid-template-columns: 1fr;
    }

    .account-info-grid {
        grid-template-columns: 1fr;
    }

    .admin-edit-card {
        padding: 20px;
    }

    .edit-actions {
        flex-direction: column;
    }

    .edit-actions button,
    .edit-actions a {
        width: 100%;
        text-align: center;
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
            <?php echo htmlspecialchars($app['organization_name']); ?>
        </h2>

        <p>Personnel Timesheet System</p>

    </div>


    <div class="profile">

        <div class="avatar">

            <img
                src="<?php echo htmlspecialchars($admin_profile_photo); ?>"
                class="profile-small"
                alt="Administrator Profile Photo"
                onerror="this.onerror=null; this.src='uploads/admins/default.png';"
            >

        </div>

        <h3>
            <?php echo htmlspecialchars($fullname); ?>
        </h3>

        <p>Super Administrator</p>

    </div>


    <ul>

        <li>
            <a href="admin_dashboard.php">
                Dashboard
            </a>
        </li>

        <li class="active">
            <a href="manage_admins.php">
                Manage Admins
            </a>
        </li>

        <li>
            <a href="manage_supervisors.php">
                Manage Supervisors
            </a>
        </li>

        <li>
            <a href="manage_staff.php">
                Manage Staff
            </a>
        </li>

        <li>
            <a href="reports.php">
                Reports
            </a>
        </li>

        <li>
            <a href="admin_logout.php">
                Logout
            </a>
        </li>

    </ul>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>Edit Administrator</h1>

            <p>Manage administrator profile and account information</p>

        </div>

        <div id="clock"></div>

    </div>


    <!-- =================================================
         PAGE CONTENT
    ================================================= -->

    <div class="edit-admin-page">


        <!-- SUCCESS -->

        <?php if ($message != "") { ?>

            <div class="success-box">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php } ?>


        <!-- ERROR -->

        <?php if ($error != "") { ?>

            <div class="error-box">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php } ?>


        <!-- =================================================
             EDIT CARD
        ================================================= -->

        <div class="admin-edit-card">

            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <!-- =================================================
                     PROFILE + PERSONAL INFORMATION
                ================================================= -->

                <div class="profile-information-layout">


                    <!-- ================================
                         PROFILE PANEL
                    ================================= -->

                    <div class="admin-profile-panel">

                        <img
                            src="uploads/admins/<?php echo htmlspecialchars($admin['profile_photo']); ?>"
                            alt="Administrator Profile Photo"
                            onerror="this.onerror=null; this.src='uploads/admins/default.png';"
                        >

                        <h2>
                            <?php echo htmlspecialchars($admin['fullname']); ?>
                        </h2>

                        <div class="admin-id">
                            <?php echo htmlspecialchars($admin['admin_id']); ?>
                        </div>

                        <div class="role-badge">
                            <?php echo htmlspecialchars($admin['role']); ?>
                        </div>


                        <div class="photo-upload-box">

                            <label>
                                Change Profile Photo
                            </label>

                            <input
                                type="file"
                                name="profile_photo"
                                accept=".jpg,.jpeg,.png"
                            >

                        </div>

                    </div>


                    <!-- ================================
                         PERSONAL + CONTACT
                    ================================= -->

                    <div>


                        <!-- PERSONAL INFORMATION -->

                        <div class="form-section">

                            <div class="section-heading">

                                <span>👤</span>

                                <h2>
                                    Personal Information
                                </h2>

                            </div>


                            <div class="edit-form-grid">


                                <div class="edit-form-group">

                                    <label>
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        name="fullname"
                                        value="<?php echo htmlspecialchars($admin['fullname']); ?>"
                                        required
                                    >

                                </div>


                                <div class="edit-form-group">

                                    <label>
                                        Admin ID
                                    </label>

                                    <input
                                        type="text"
                                        name="admin_id"
                                        value="<?php echo htmlspecialchars($admin['admin_id']); ?>"
                                        required
                                    >

                                </div>


                                <div class="edit-form-group">

                                    <label>
                                        Gender
                                    </label>

                                    <select name="gender">

                                        <option
                                            value="Male"
                                            <?php
                                            if ($admin['gender'] == "Male") {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Male
                                        </option>

                                        <option
                                            value="Female"
                                            <?php
                                            if ($admin['gender'] == "Female") {
                                                echo "selected";
                                            }
                                            ?>
                                        >
                                            Female
                                        </option>

                                    </select>

                                </div>


                                <div class="edit-form-group">

                                    <label>
                                        Date of Birth
                                    </label>

                                    <input
                                        type="date"
                                        name="date_of_birth"
                                        value="<?php echo htmlspecialchars($admin['date_of_birth']); ?>"
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- CONTACT INFORMATION -->

                        <div class="form-section">

                            <div class="section-heading">

                                <span>📞</span>

                                <h2>
                                    Contact Information
                                </h2>

                            </div>


                            <div class="edit-form-grid">


                                <div class="edit-form-group">

                                    <label>
                                        Email
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        value="<?php echo htmlspecialchars($admin['email']); ?>"
                                        required
                                    >

                                </div>


                                <div class="edit-form-group">

                                    <label>
                                        Phone
                                    </label>

                                    <input
                                        type="text"
                                        name="phone"
                                        value="<?php echo htmlspecialchars($admin['phone']); ?>"
                                    >

                                </div>


                                <div class="edit-form-group full-width">

                                    <label>
                                        Address
                                    </label>

                                    <textarea
                                        name="address"
                                        rows="3"
                                    ><?php echo htmlspecialchars($admin['address']); ?></textarea>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     EMPLOYMENT & ACCOUNT
                ================================================= -->

                <div class="form-section" style="margin-top:35px;">

                    <div class="section-heading">

                        <span>💼</span>

                        <h2>
                            Employment & Account Information
                        </h2>

                    </div>


                    <div class="edit-form-grid">


                        <div class="edit-form-group">

                            <label>
                                Position
                            </label>

                            <input
                                type="text"
                                name="position"
                                value="<?php echo htmlspecialchars($admin['position']); ?>"
                            >

                        </div>


                        <div class="edit-form-group">

                            <label>
                                Role
                            </label>

                            <select name="role">

                                <option
                                    value="Admin"
                                    <?php
                                    if ($admin['role'] == "Admin") {
                                        echo "selected";
                                    }
                                    ?>
                                >
                                    Admin
                                </option>

                                <option
                                    value="Super Admin"
                                    <?php
                                    if ($admin['role'] == "Super Admin") {
                                        echo "selected";
                                    }
                                    ?>
                                >
                                    Super Admin
                                </option>

                            </select>

                        </div>


                        <div class="edit-form-group">

                            <label>
                                Status
                            </label>

                            <select name="status">

                                <option
                                    value="Active"
                                    <?php
                                    if ($admin['status'] == "Active") {
                                        echo "selected";
                                    }
                                    ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="Inactive"
                                    <?php
                                    if ($admin['status'] == "Inactive") {
                                        echo "selected";
                                    }
                                    ?>
                                >
                                    Inactive
                                </option>

                            </select>

                        </div>


                        <div class="edit-form-group">

                            <label>
                                Created At
                            </label>

                            <input
                                type="text"
                                class="readonly-field"
                                value="<?php
                                    if (!empty($admin['created_at'])) {
                                        echo htmlspecialchars(
                                            date(
                                                'F d, Y • h:i A',
                                                strtotime($admin['created_at'])
                                            )
                                        );
                                    } else {
                                        echo "Not Available";
                                    }
                                ?>"
                                readonly
                            >

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     ACCOUNT SUMMARY
                ================================================= -->

                <div class="form-section">

                    <div class="section-heading">

                        <span>ℹ️</span>

                        <h2>
                            Account Summary
                        </h2>

                    </div>


                    <div class="account-info-grid">


                        <div class="account-info-box">

                            <small>
                                Administrator ID
                            </small>

                            <strong>
                                <?php echo htmlspecialchars($admin['admin_id']); ?>
                            </strong>

                        </div>


                        <div class="account-info-box">

                            <small>
                                Account Role
                            </small>

                            <strong>
                                <?php echo htmlspecialchars($admin['role']); ?>
                            </strong>

                        </div>


                        <div class="account-info-box">

                            <small>
                                Account Status
                            </small>

                            <strong class="<?php
                                echo ($admin['status'] == 'Active')
                                    ? 'status-active'
                                    : 'status-inactive';
                            ?>">
                                <?php echo htmlspecialchars($admin['status']); ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     ACTIONS
                ================================================= -->

                <div class="edit-actions">

                    <button
                        type="reset"
                        class="excel-btn"
                    >
                        ↺ Reset
                    </button>


                    <a
                        href="view_admin.php?id=<?php echo urlencode($admin['id']); ?>"
                        class="print-btn"
                    >
                        ✕ Cancel
                    </a>


                    <button
                        type="submit"
                        name="update"
                        class="approve-btn"
                    >
                        💾 Save Changes
                    </button>

                </div>


            </form>

        </div>

    </div>

</div>

</div>


<!-- =====================================================
     CLOCK
===================================================== -->

<script>

function updateClock() {

    document.getElementById("clock").innerHTML =
        new Date().toLocaleTimeString();

}

updateClock();

setInterval(updateClock, 1000);

</script>

</body>

</html>