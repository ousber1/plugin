<?php
$this->layout('errors/layout', [
    'code' => 429,
    'title' => __('errors.429_title'),
    'text' => ($message ?? '') !== '' ? $message : __('errors.429_text'),
    'exception' => $exception ?? null,
]);
