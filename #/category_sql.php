<?php
function executeQuery($function, $data) {
  switch ($function) {
    case 'select':
      return select();
  }
}

function select() {
  $categories = [];
  $query = "SELECT id, name FROM category ORDER BY name ASC;";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  while($row = mysqli_fetch_assoc($result)) {
    $category = [
      'id' => $row['id'],
      'name' => $row['name'],
      'subject' => getSubjects($row['id'])
    ];

    array_push($categories, $category);
  };

  return $categories;
}

function getSubjects($category) {
  $subjects = [];
  $query = "SELECT id, name FROM subject WHERE category = $category ORDER BY name ASC;";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  while($row = mysqli_fetch_assoc($result)) {
    $subject = [
      'id' => $row['id'],
      'name' => $row['name']
    ];

    array_push($subjects, $subject);
  };

  return $subjects;
}
?>
