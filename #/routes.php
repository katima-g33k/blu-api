<?php
require_once '#/category.php';
require_once '#/employee.php';
require_once '#/item.php';
require_once '#/member.php';
require_once '#/reservation.php';
require_once '#/state.php';
require_once '#/statistics.php';
require_once '#/storage.php';
require_once '#/transaction.php';

$routes = [
  'DELETE' => [
    [ 'url' => '/employee/:id', 'fn' => $employeeDelete ],
    [ 'url' => '/item/:id', 'fn' => $itemDelete ],
    [ 'url' => '/member/:no', 'fn' => $deleteMember ],
    [ 'url' => '/member/comment/:id', 'fn' => $memberCommentDelete ],
    [ 'url' => '/member/copy/:id', 'fn' => $memberDeleteCopy ],
    [ 'url' => '/reservation', 'fn' => $reservationDelete ],
    [ 'url' => '/storage', 'fn' => $storageDelete ],
    [ 'url' => '/transaction', 'fn' => $transactionDelete ],
  ],
  'GET' => [
    [ 'url' => '/category', 'fn' => $getCategory ],
    [ 'url' => '/employee', 'fn' => $employeeList ],
    [ 'url' => '/item', 'fn' => $itemList ],
    [ 'url' => '/item/:id', 'fn' => $getItem ],
    [ 'url' => '/item/exists/:ean13', 'fn' => $itemExists ],
    [ 'url' => '/item/merge', 'fn' => $itemMerge ],
    [ 'url' => '/item/name/:ean13', 'fn' => $getItemName ],
    [ 'url' => '/member', 'fn' => $memberSearch ],
    [ 'url' => '/member/:no', 'fn' => $getMember ],
    [ 'url' => '/member/:no/name', 'fn' => $memberName ],
    [ 'url' => '/member/:no/pay', 'fn' => $memberPay ],    
    [ 'url' => '/member/:no/renew', 'fn' => $memberRenew ],
    [ 'url' => '/member/duplicates', 'fn' => $memberDuplicates ],
    [ 'url' => '/member/exists', 'fn' => $memberExists ],
    [ 'url' => '/member/merge', 'fn' => $memberMerge ],
    [ 'url' => '/reservation', 'fn' => $reservationList ],
    [ 'url' => '/state', 'fn' => $stateList ],
    [ 'url' => '/statistics/interval', 'fn' => $intervalStatistics ],
    [ 'url' => '/storage', 'fn' => $storageList ],
  ],
  'POST' => [
    [ 'url' => '/employee', 'fn' => $employeeInsert ],
    [ 'url' => '/employee/:id', 'fn' => $employeeUpdate ],    
    [ 'url' => '/employee/login', 'fn' => $employeeLogin ],
    [ 'url' => '/item', 'fn' => $itemInsert ],
    [ 'url' => '/item/:id', 'fn' => $itemUpdate ],
    [ 'url' => '/item/:id/status', 'fn' => $itemUpdateStatus ],
    [ 'url' => '/item/:id/storage', 'fn' => $itemUpdateStorage ],    
    [ 'url' => '/member', 'fn' => $memberInsert ],
    [ 'url' => '/member/:no', 'fn' => $memberUpdate ],
    [ 'url' => '/member/:no/comment', 'fn' => $memberCommentInsert ],
    [ 'url' => '/member/:no/copy', 'fn' => $memberInsertCopy ],
    [ 'url' => '/member/comment/:id', 'fn' => $memberCommentUpdate ],
    [ 'url' => '/member/copy/:id', 'fn' => $memberUpdateCopy ],
    [ 'url' => '/reservation', 'fn' => $reservationInsert ],
    [ 'url' => '/transaction', 'fn' => $transactionInsert ],
  ],
];
?>