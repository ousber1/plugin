<?php
$this->layout('errors/layout', [
    'code' => 405,
    'title' => __('errors.405_title'),
    'text' => __('errors.405_text'),
    'exception' => $exception ?? null,
]);
