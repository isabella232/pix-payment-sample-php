<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__  . '/vendor/autoload.php';

$app = AppFactory::create();

MercadoPago\SDK::setAccessToken($_ENV["MERCADO_PAGO_SAMPLE_ACCESS_TOKEN"]);

// serve html
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write(file_get_contents("../client/index.html"));
    return $response;
});

// process pix payment
$app->post('/process_payment', function (Request $request, Response $response, $args) {
    $contents = json_decode(file_get_contents('php://input'), true);
    $parsed_request = $request->withParsedBody($contents);
    $parsed_body = $parsed_request->getParsedBody();

    $payment = new MercadoPago\Payment();
    $payment->transaction_amount = $parsed_body["transactionAmount"];
    $payment->description = $parsed_body["description"];
    $payment->payment_method_id = "pix";
    $payment->payer = array(
        "email" => $parsed_body["payer"]["email"],
        "first_name" => $parsed_body["payer"]["firstName"],
        "last_name" => $parsed_body["payer"]["lastName"],
        "identification" => array(
            "type" => $parsed_body["payer"]["identification"]["type"],
            "number" => $parsed_body["payer"]["identification"]["number"]
        )
    );

    $payment->save();

    $response_fields = array(
        'id' => $payment->id,
        'status' => $payment->status,
        'detail' => $payment->status_detail,
        'qrCodeBase64' => $payment->point_of_interaction->transaction_data->qr_code_base64,
        'qrCode' => $payment->point_of_interaction->transaction_data->qr_code
    );

    $response_body = json_encode($response_fields);

    $response->getBody()->write($response_body);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// serve static files
$app->get('/{filetype}/{filename}', function (Request $request, Response $response, $args) {
    switch ($args['filetype']) {
        case 'css':
            $fileFolderPath = __DIR__ . '/../client/css/';
            $mimeType = 'text/css';
            break;

        case 'js':
            $fileFolderPath = __DIR__ . '/../client/js/';
            $mimeType = 'application/javascript';
            break;

        case 'img':
            $fileFolderPath = __DIR__ . '/../client/img/';
            $mimeType = 'image/png';
            break;

        default:
            $fileFolderPath = '';
            $mimeType = '';
    }

    $filePath = $fileFolderPath . $args['filename'];

    if (!file_exists($filePath)) {
        return $response->withStatus(404, 'File not found');
    }

    $newResponse = $response->withHeader('Content-Type', $mimeType . '; charset=UTF-8');
    $newResponse->getBody()->write(file_get_contents($filePath));

    return $newResponse;
});

$app->run();