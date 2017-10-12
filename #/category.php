<?php
// Public API functions
$getCategory = function() {
  $categories = [];
  $query = "SELECT id, name FROM category ORDER BY name ASC;";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $name);
  
  while (mysqli_stmt_fetch($statement)) {
    array_push($categories, [
      'id' => $id,
      'name' => $name,
      'subject' => getSubjects($id)
    ]);
  }


  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $categories;
};

// Private ressource functions
function getSubjects($category) {
  $subjects = [];
  $query = "SELECT id, name FROM subject WHERE category=? ORDER BY name ASC;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $category);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $name);

  while(mysqli_stmt_fetch($statement)) {    
    array_push($subjects, [
      'id' => $id,
      'name' => $name
    ]);
  };

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $subjects;
}
?>
