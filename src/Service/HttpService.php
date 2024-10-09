<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpService
{
  private HttpClientInterface $client;

  public function __construct(HttpClientInterface $client)
  {
    $this->client = $client;
  }

  public function odooCall(string $url, string $service, string $method, array $params)
  {
    $response     = $this->client->request(
      'POST',
      $url,
      [
        'json' => [
          'jsonrpc' => '2.0',
          'method'  => 'call',
          'params'  => [
            'service' => $service,
            'method'  => $method,
            'args'    => $params,
          ],
          'id'      => time(),
        ]
      ]
    );

    // $statusCode   = $response->getStatusCode();
    // $contentType  = $response->getHeaders()['content-type'][0];
    // $content      = $response->getContent();
    // $contentArray = $response->toArray();
    // dump($statusCode, $contentType, $content, $contentArray);
    // die;

    return $response->toArray()['result'] ?? null;
  }
}
