<?php
require_once '../config/config.php';

if (isAdminLoggedIn()) {
    redirect(adminUrl('dashboard'));
} else {
    redirect(adminUrl('login'));
}
