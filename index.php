<?php
require_once __DIR__ . '/bootstrap.php';

if (Auth::check()) {
    header('Location: login.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}