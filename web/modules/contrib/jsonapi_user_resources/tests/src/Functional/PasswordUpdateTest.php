<?php declare(strict_types = 1);

namespace Drupal\Tests\jsonapi_user_resources\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\jsonapi\Functional\ResourceResponseTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Resource User registration.
 *
 * @group jsonapi_user_resources
 */
final class PasswordUpdateTest extends BrowserTestBase {

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
    'jsonapi_user_resources',
  ];

  /**
   * The test user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $sut;

  /**
   * Password update url.
   *
   * @var string
   */
  private $passwordUpdateUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);
    $this->sut = $this->createUser([], 'sut');
    $this->passwordUpdateUrl = Url::fromRoute('jsonapi_user_resources.password_update', ['user' => $this->sut->uuid()]);
  }

  /**
   * Tests validation for the password update resource.
   *
   * @param array $attributes
   *   The request document values.
   * @param bool $blocked
   *   If the user is blocked.
   * @param string $expected_error_message
   *   The expected error message.
   *
   * @dataProvider validationDataProvider
   */
  public function testValidation(array $attributes, bool $blocked, string $expected_error_message) {
    if ($blocked) {
      $this->sut->block();
      $this->sut->setLastAccessTime(time());
    }
    else {
      $this->sut->activate();
    }
    $this->sut->save();

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => $attributes,
      ],
    ];
    $response = $this->request('PATCH', $this->passwordUpdateUrl, $request_options);
    $body = (string) $response->getBody();
    $this->assertEquals(422, $response->getStatusCode(), $body);
    $decoded_document = Json::decode($body);
    $this->assertTrue(!empty($decoded_document['errors']) && is_array($decoded_document['errors']), $body);
    $this->assertCount(1, $decoded_document['errors'], var_export($decoded_document['errors'], TRUE));
    $this->assertEquals($expected_error_message, $decoded_document['errors'][0]['detail'], $body);
  }

  /**
   * Tests the password update resource.
   */
  public function testPasswordUpdate() {
    $request_options = [];
    $this->sut->block()->save();
    $timestamp = time();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'pass' => user_password(),
          'hash' => user_pass_rehash($this->sut, $timestamp),
          'timestamp' => $timestamp,
        ],
      ],
    ];
    $this->assertTrue($this->sut->isBlocked());
    $previous_password = $this->sut->getPassword();
    $response = $this->request('PATCH', $this->passwordUpdateUrl, $request_options);
    $body = (string) $response->getBody();
    $this->assertEquals(200, $response->getStatusCode(), $body);
    $this->sut = $this->reloadEntity($this->sut);
    $this->assertTrue($this->sut->isActive());
    $this->assertNotEqual($this->sut->getPassword(), $previous_password);
  }

  /**
   * Password update validation test data provider.
   *
   * @return \Generator
   *   The test data.
   */
  public function validationDataProvider(): \Generator {
    yield [
      [],
      FALSE,
      'Missing required property "timestamp".',
    ];
    yield [
      ['timestamp' => 1234],
      FALSE,
      'Missing required property "hash".',
    ];
    yield [
      ['timestamp' => 1234, 'hash' => 'foo'],
      FALSE,
      'Missing required property "pass".',
    ];
    yield [
      ['timestamp' => 1234, 'hash' => 'foo', 'pass' => 'foo'],
      FALSE,
      'The password reset information is no longer valid.',
    ];
    yield [
      ['timestamp' => 1234, 'hash' => 'foo', 'pass' => 'foo'],
      TRUE,
      'The password reset information is no longer valid.',
    ];
  }

  /**
   * Reloads the entity after clearing the static cache.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to reload.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The reloaded entity.
   */
  protected function reloadEntity(EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage($entity->getEntityTypeId());
    $storage->resetCache([$entity->id()]);
    return $storage->load($entity->id());
  }

}
