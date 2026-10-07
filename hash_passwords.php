<?php

include "config.php";


/* Hash supervisors passwords */

$result = mysqli_query($conn,"
SELECT supervisor_id,password
FROM supervisors
");


while($row=mysqli_fetch_assoc($result)){


    if(
        substr($row['password'],0,4)!='$2y$'
    ){

        $hash = password_hash(
            $row['password'],
            PASSWORD_DEFAULT
        );


        mysqli_query($conn,"
        UPDATE supervisors
        SET password='$hash'
        WHERE supervisor_id='{$row['supervisor_id']}'
        ");

    }

}


echo "Supervisor passwords updated.";

?>