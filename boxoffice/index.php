<?php

require '../vendor/autoload.php';

$config = json_decode(file_get_contents("config/config.json"), true);
$app = new \Slim\App([ 'settings' => $config ]);

require '../dependencies.php';

// Routes
// =============================================================
$app->get('/events', Actions\ListEventsAction::class);

$app->get('/events/{id}', Actions\GetEventAction::class);

$app->get('/blocks/{id}', Actions\GetBlockAction::class);

$app->get('/reservations', Actions\ListReservationsAction::class);

$app->post('/reservations', Actions\CreateReservationAction::class);

$app->put('/reservations/{id}', Actions\ChangeReductionForReservationAction::class);

$app->delete('/reservations/{id}', Actions\DeleteReservationAction::class);

$app->post('/boxoffice-purchases', Actions\CreateBoxofficePurchaseAction::class);
// =============================================================

$app->run();