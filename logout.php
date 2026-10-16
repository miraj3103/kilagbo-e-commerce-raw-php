<?php
require_once 'config/config.php';

unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);

redirect(baseUrl('login'));
