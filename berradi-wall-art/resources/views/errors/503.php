<?php
$this->layout('errors/layout', [
    'code' => 503,
    'title' => __('errors.503_title'),
    'text' => __('errors.503_text'),
    'exception' => $exception ?? null,
]);
