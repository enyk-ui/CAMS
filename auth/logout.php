<?php
/**
 * Logout
 */

session_start();
session_destroy();

header('Location: ../index.php?message=Logged out successfully');
exit;

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */