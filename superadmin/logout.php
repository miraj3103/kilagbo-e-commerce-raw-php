<?php
require_once '../config/config.php';

unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_picture']);

redirect(adminUrl('login'));
