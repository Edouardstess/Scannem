<?php
/** @var string $message */

echo \App\Core\View::render('errors.error', [
    'status'  => 410,
    'message' => $message ?? '',
]);
