<?php
$this->layout('errors/layout', [
    'code' => 500,
    'title' => __('errors.500_title'),
    'text' => __('errors.500_text'),
    'exception' => $exception ?? null,
]);
