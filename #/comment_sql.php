<?php
function executeQuery($function, $data) {
  switch ($function) {
    case 'insert':
      return insert($data['member'], $data['comment']);
    case 'update':
      return update($data['id'], $data['comment']);
    case 'delete':
      return delete($data['id']);
  }
}

function insert($comment) {
  $query = "INSERT INTO comment(member, comment, updated_at) VALUES ($member, '$comment', CURRENT_TIMESTAMP)";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return [ 'id' => $id ];
}

function update($id, $comment) {
  $query = "UPDATE comment SET comment='$comment', updated_at=CURRENT_TIMESTAMP WHERE id=$id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  return [ 'code' => 200, 'message' => 'UPDATE_SUCCESSFUL' ];
}

function delete($id) {
  $query = "DELETE FROM comment WHERE id=$id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  return [ 'code' => 200, 'message' => 'DELETE_SUCCESSFUL' ];
}

?>
