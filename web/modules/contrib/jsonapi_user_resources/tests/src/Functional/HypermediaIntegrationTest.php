<?php declare(strict_types = 1);

namespace Drupal\Tests\jsonapi_user_resources\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\jsonapi\Functional\ResourceResponseTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Resource User registration.
 *
 * @group jsonapi_user_resources
 * @requires jsonapi_hypermedia
 */
final class HypermediaIntegrationTest extends BrowserTestBase {

  use JsonApiRequestTestTrait;
  use ResourceResponseTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_resources',
    'jsonapi_hypermedia',
    'jsonapi_user_resources',
  ];

  /**
   * Tests the `authenticated-as` link.
   */
  public function testAuthenticatedAsLink() {
    $url = Url::fromRoute('jsonapi.resource_list');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $response = $this->request('GET', $url, $request_options);
    $body = (string) $response->getBody();
    $this->assertEquals(200, $response->getStatusCode(), $body);
    $decoded_document = Json::decode($body);
    $this->assertFalse(isset($decoded_document['links']['authenticated-as']));

    $sut = $this->createUser();
    $this->drupalLogin($sut);

    $response = $this->request('GET', $url, $request_options);
    $body = (string) $response->getBody();
    $this->assertEquals(200, $response->getStatusCode(), $body);
    $decoded_document = Json::decode($body);
    $this->assertTrue(isset($decoded_document['links']['authenticated-as']), var_export($decoded_document, TRUE));
    $link_href = $decoded_document['links']['authenticated-as']['href'];
    $expected_link_href = Url::fromRoute('jsonapi.user--user.individual', ['entity' => $sut->uuid()])->setAbsolute()->toString();
    $this->assertEquals($expected_link_href, $link_href);
  }

}
