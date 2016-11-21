<?php
$API['subject'] = [
  'select' => function() {
    $subjects = [];
    $query = "SELECT subject.id,
                     subject.name,
                     category.id AS category_id,
                     category.name AS category_name
              FROM subject
              INNER JOIN category
                ON subject.category = category.id";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);

    while($row = mysqli_fetch_assoc($result)) {
      $subject = [
        'id' => $row['id'],
        'name' => $row['name'],
        'category' => [
          'id' => $row['category_id'],
          'name' => $row['category_name']
        ]
      ];

      array_push($subjects, $subject);
    };

    mysqli_close($connection);
    return $subjects;
  }
];
?>
