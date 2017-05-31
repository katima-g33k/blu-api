<?php
$API['storage'] = [
  'delete' => function($data) {
    $query = "DELETE FROM storage;";
    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    return OPERATION_SUCCESSFUL;
  },

  'select' => function() {
    $query = "SELECT storage.no,
                     item.id,
                     item.name
              FROM storage
              INNER JOIN item
                ON storage.item = item.id;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    $storage = [];
    while($row = mysqli_fetch_assoc($result)) {
      $item = [
        'id' => $row['id'],
        'name' => $row['name']
      ];

      if (!isset($storage[$row['no']])) {
        $storage[$row['no']] = [];
      }

      array_push($storage[$row['no']], $item);
    }

    return $storage;
  }
];
?>
