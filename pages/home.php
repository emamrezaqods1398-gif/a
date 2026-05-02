<?php
/**
 * SATA Messenger - Home/Landing Page
 * Redirects to login or chat based on session
 */

$auth = new UserAuth();

if ($auth->verifySession()) {
    header('Location: chat');
    exit;
} else {
    header('Location: login');
    exit;
}
