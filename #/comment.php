<?php
$API['comment'] = [
  'delete' => function($data) {
    $id = $data['id'];
    $query = "DELETE FROM comment WHERE id=$id;";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },

  'insert' => function($data) {
    $member = $data['member'];
    $comment = str_replace("'", "''", $data['comment']);
    $query = "INSERT INTO comment(member, comment, updated_at) VALUES ($member, '$comment', CURRENT_TIMESTAMP)";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
    $id = mysqli_insert_id($connection);

    mysqli_close($connection);
    return [ 'id' => $id ];
  },

  'update' => function($data) {
    $id = $data['id'];
    $comment = str_replace("'", "''", $data['comment']);
    $query = "UPDATE comment
              SET comment='$comment', updated_at=CURRENT_TIMESTAMP
              WHERE id=$id;";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  }
];
?>
