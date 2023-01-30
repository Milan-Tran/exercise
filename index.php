<?php

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Leadinfo\Excercise\config\DB;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

// Instantiate App
$app = AppFactory::create();

//Load ENV
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// Add routes
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Welcome');
    return $response;
});

// localhost:8081/api
// -> all companies in all tables
// localhost:8081/api?sort[name]=ASC
// -> all companies in all tables sorted by name
// localhost:8081/api?filter[country]=NL&sort[city]=ASC
// -> companies filtered by country sorted by city
// localhost:8081/api?filter[name]=Connell&sort[city]=ASC
// -> companies which have Connell (tip: mysql LIKE) in the name sorted by city
// output format needs to be JSON array with individual records

$app->get('/api', function (Request $request, Response $response) {
    try {
        $params = $request->getQueryParams();
        $country = $params['filter']['country'] ?? null;
        $name = $params['filter']['name'] ?? null;
        $sorts = $params['sort'] ?? [];

        $connection = (new DB())->connect();

        $allTables = $connection->query("SELECT data_table FROM company group by data_table");
        $allTablesResult = $allTables->fetchAll(PDO::FETCH_COLUMN);

        $data = [];

        foreach ($allTablesResult as $index => $table) {
            if ($country && ! str_contains($table, strtolower($country))) {
                continue;
            }
            $sql = "SELECT company.id, " . $table . ".* FROM company INNER JOIN " . $table . " ON 
                company.data_unique_id = " . $table . ".unique_id " . ($name ? "WHERE " . $table . ".name LIKE '%" . $name . "%'" : "");

            $result = $connection->query($sql);
            $result = $result->fetchAll(PDO::FETCH_ASSOC);
            $data = array_merge($data, $result);
        }

        foreach ($sorts as $sortKey => $direction) {
            $sort_col = [];
            foreach ($data as $key => $item) {
                $sort_col[$key] = $item[$sortKey];
            }

            if ($direction === 'asc') {
                array_multisort($sort_col, SORT_ASC, $data);
            } else {
                array_multisort($sort_col, SORT_DESC, $data);
            }
        }

        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(
            json_encode([
                "message" => $e->getMessage(),
            ])
        );

        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

$app->run();
