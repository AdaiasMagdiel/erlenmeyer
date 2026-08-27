<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

beforeEach(function () {
    $this->app = new App();

    $this->app->any('/echo', function (Request $req, Response $res) {
        $res->withJson([
            'method'       => $req->getMethod(),
            'raw_body'     => $req->getRawBody(),
            'content_type' => $req->getHeader('content-type'),
            'x_test'       => $req->getHeader('x-test'),
        ]);
    });

    $this->client = new ErlenClient($this->app);
});

// -------------------------------------------------------------------------
// HTTP method shortcuts
// -------------------------------------------------------------------------

test('client put sends PUT request', function () {
    $data = json_decode($this->client->put('/echo')->getBody(), true);
    expect($data['method'])->toBe('PUT');
});

test('client patch sends PATCH request', function () {
    $data = json_decode($this->client->patch('/echo')->getBody(), true);
    expect($data['method'])->toBe('PATCH');
});

test('client delete sends DELETE request', function () {
    $data = json_decode($this->client->delete('/echo')->getBody(), true);
    expect($data['method'])->toBe('DELETE');
});

test('client head sends HEAD request', function () {
    $this->app->head('/ping', function (Request $req, Response $res) {
        $res->withText('pong');
    });
    expect($this->client->head('/ping')->getStatusCode())->toBe(200);
});

test('client options sends OPTIONS request', function () {
    $this->app->options('/ping', function (Request $req, Response $res) {
        $res->setStatusCode(204)->withText('');
    });
    expect($this->client->options('/ping')->getStatusCode())->toBe(204);
});

// -------------------------------------------------------------------------
// Default headers
// -------------------------------------------------------------------------

test('client withHeaders adds headers to all subsequent requests', function () {
    $this->client->withHeaders(['X-Test' => 'hello']);
    $data = json_decode($this->client->get('/echo')->getBody(), true);
    expect($data['x_test'])->toBe('hello');
});

test('client resetHeaders clears all default headers', function () {
    $this->client->withHeaders(['X-Test' => 'hello']);
    $this->client->resetHeaders();
    $data = json_decode($this->client->get('/echo')->getBody(), true);
    expect($data['x_test'])->toBeNull();
});

test('client withHeaders returns self for chaining', function () {
    expect($this->client->withHeaders(['X-A' => '1']))->toBeInstanceOf(ErlenClient::class);
});

test('client resetHeaders returns self for chaining', function () {
    expect($this->client->resetHeaders())->toBeInstanceOf(ErlenClient::class);
});

// -------------------------------------------------------------------------
// showOutput
// -------------------------------------------------------------------------

test('client showOutput returns self for chaining', function () {
    expect($this->client->showOutput(false))->toBeInstanceOf(ErlenClient::class);
});

// -------------------------------------------------------------------------
// Request body options
// -------------------------------------------------------------------------

test('client sends JSON body and sets Content-Type', function () {
    $data = json_decode(
        $this->client->post('/echo', ['json' => ['name' => 'test', 'active' => true]])->getBody(),
        true
    );
    expect($data['content_type'])->toContain('application/json')
        ->and(json_decode($data['raw_body'], true)['name'])->toBe('test');
});

test('client sends form params and sets Content-Type', function () {
    $this->app->post('/form', function (Request $req, Response $res) {
        $res->withJson(['field' => $req->getFormDataParam('field')]);
    });
    $data = json_decode(
        $this->client->post('/form', ['form_params' => ['field' => 'form-value']])->getBody(),
        true
    );
    expect($data['field'])->toBe('form-value');
});

// -------------------------------------------------------------------------
// URI normalisation
// -------------------------------------------------------------------------

test('client strips trailing slash from URI', function () {
    $this->app->get('/path', function (Request $req, Response $res) {
        $res->withText('ok');
    });
    expect($this->client->get('/path/')->getBody())->toBe('ok');
});

test('client preserves query string in URI', function () {
    $this->app->get('/search', function (Request $req, Response $res) {
        $res->withText($req->getQueryParam('q', ''));
    });
    expect($this->client->get('/search?q=hello')->getBody())->toBe('hello');
});

test('client request() with query option appends query params', function () {
    $this->app->get('/search', function (Request $req, Response $res) {
        $res->withText($req->getQueryParam('q', ''));
    });
    $response = $this->client->request('GET', '/search', ['query' => ['q' => 'world']]);
    expect($response->getBody())->toBe('world');
});
