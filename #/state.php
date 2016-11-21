<?php
$API['state'] = [
  'select' => function() {
    $states = [];
    $query = "SELECT code FROM state";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
    mysqli_close($connection);

    while($row = mysqli_fetch_assoc($result)) {
      array_push($states, $row['code']);
    }

    return $states;
  }
];
?>
