<?php
$this->layout('errors/layout', [
    'code' => 403,
    'title' => __('errors.403_title'),
    'text' => ($message ?? '') !== '' ? $message : __('errors.403_text'),
    'exception' => $exception ?? null,
]);
