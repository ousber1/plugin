<?php
$this->layout('errors/layout', [
    'code' => 419,
    'title' => __('errors.419_title'),
    'text' => __('errors.419_text'),
    'exception' => $exception ?? null,
]);
