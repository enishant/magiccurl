<?php

/**
 * Minimal router used by PHP's built-in web server during integration tests.
 * Each path below exercises a specific MagicCurl behaviour.
 */

header_remove('X-Powered-By');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

switch ($path) {
    case '/echo':
        header('Content-Type: application/json');
        echo json_encode([
            'method' => $_SERVER['REQUEST_METHOD'],
            'query' => $_GET,
            'body' => file_get_contents('php://input'),
            'headers' => getallheaders(),
        ]);
        break;

    case '/plain':
        header('Content-Type: text/plain');
        echo 'just some plain text';
        break;

    case '/status':
        $code = isset($_GET['code']) ? (int) $_GET['code'] : 200;
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        break;

    case '/slow':
        usleep(1500000); // 1.5s, exceeds the 1s timeout used in tests
        echo 'done';
        break;

    case '/json-scalar':
        header('Content-Type: application/json');
        echo 'true';
        break;

    default:
        http_response_code(404);
        echo 'not found';
        break;
}
