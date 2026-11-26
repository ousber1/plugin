<?php
$this->layout('errors/layout', [
    'code' => 404,
    'title' => __('errors.404_title'),
    'text' => __('errors.404_text'),
    'exception' => $exception ?? null,
]);
