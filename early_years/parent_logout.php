<?php
require_once '../config/session.php';unset($_SESSION['early_years_family_id'],$_SESSION['early_years_family_label'],$_SESSION['early_years_parent_id'],$_SESSION['early_years_parent_name']);header('Location: ../login.php');exit;
