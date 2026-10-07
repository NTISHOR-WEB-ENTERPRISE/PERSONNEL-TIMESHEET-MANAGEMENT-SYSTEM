<?php

session_start();

include "config.php";


if(!isset($_SESSION['supervisor_id'])){

    header("Location: supervisor_login.php");
    exit();

}


$supervisor_id = $_SESSION['supervisor_id'];



$query = mysqli_query($conn,"
SELECT profile_photo
FROM supervisors
WHERE supervisor_id='$supervisor_id'
");


$supervisor = mysqli_fetch_assoc($query);



if(isset($_POST['upload'])){


    if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['name'] != ""){


        $file = $_FILES['profile_photo'];


        $filename = time()."_".$file['name'];


        $target = "uploads/supervisors/".$filename;



        $extension = strtolower(
            pathinfo($filename, PATHINFO_EXTENSION)
        );


        $allowed = ['jpg','jpeg','png','webp'];



        if(in_array($extension,$allowed)){


            move_uploaded_file(
                $file['tmp_name'],
                $target
            );



            mysqli_query($conn,"
            UPDATE supervisors
            SET profile_photo='$filename'
            WHERE supervisor_id='$supervisor_id'
            ");



            header("Location: supervisor_profile.php");

            exit();


        }
        else{

            $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";

        }


    }
    else{

        $error = "Please select an image.";

    }


}


?>


<!DOCTYPE html>
<html>

<head>

<title>Change Profile Photo</title>

<link rel="stylesheet" href="styles.css">

</head>


<body>


<div class="container">


<div class="main">


<div class="topbar">

<h1>
Change Profile Photo
</h1>

</div>




<div class="details-card">



<?php

if(isset($error)){

echo "<p style='color:red;'>$error</p>";

}

?>



<div class="profile-image-section">


<img

src="uploads/supervisors/<?php echo $supervisor['profile_photo']; ?>"

class="profile-image"

>



</div>



<form method="POST" enctype="multipart/form-data">


<div class="form-group">


<label>
Select New Photo
</label>


<input

type="file"

name="profile_photo"

accept="image/*"

required>


</div>




<button

type="submit"

name="upload"

class="approve-btn">

📷 Upload Photo

</button>



<a

href="supervisor_profile.php"

class="back-btn">

Cancel

</a>



</form>


</div>


</div>


</div>


</body>

</html>