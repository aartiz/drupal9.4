<?php declare(strict_types = 1);

namespace Drupal\Tests\jsonapi_user_resources\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Test\AssertMailTrait;
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
final class PasswordResetTest extends BrowserTestBase {

  use JsonApiRequestTestTrait;
  use ResourceResponseTestTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);
    $this->sut = $this->createUser([], 'sut');
  }

  /**
   * Tests validation for the user password resource.
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
    }
    else {
      $this->sut->activate();
    }
    $this->sut->save();

    $url = Url::fromRoute('jsonapi_user_resources.password_reset');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--password-reset',
        'attributes' => $attributes,
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $body = (string) $response->getBody();
    $this->assertEquals(422, $response->getStatusCode(), $body);
    $decoded_document = Json::decode($body);
    $this->assertTrue(!empty($decoded_document['errors']) && is_array($decoded_document['errors']), $body);
    $this->assertCount(1, $decoded_document['errors'], var_export($decoded_document['errors'], TRUE));
    $this->assertEquals($expected_error_message, $decoded_document['errors'][0]['detail'], $body);
  }

  /**
   * Tests the user password resource.
   *
   * @param array $attributes
   *   The request document values.
   *
   * @dataProvider passwordResetDataProvider
   */
  public function testPasswordReset(array $attributes) {
    $url = Url::fromRoute('jsonapi_user_resources.password_reset');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--password-reset',
        'attributes' => $attributes,
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $body = (string) $response->getBody();
    $this->assertEquals(202, $response->getStatusCode(), $body);
    $decoded_document = Json::decode($body);
    $this->assertEquals('Password reset requested for sut (sut@example.com).', $decoded_document['meta']['message'], $body);
  }

  /**
   * Password reset validation test data provider.
   *
   * @return \Generator
   *   The test data.
   */
  public function validationDataProvider(): \Generator {
    yield [
      [],
      FALSE,
      'Missing name or mail',
    ];
    yield [
      ['name' => 'foo'],
      FALSE,
      'Unrecognized username or email address.',
    ];
    yield [
      ['mail' => 'foo'],
      FALSE,
      'Unrecognized username or email address.',
    ];
    yield [
      ['name' => 'sut'],
      TRUE,
      'The user has not been activated or is blocked.',
    ];
    yield [
      ['mail' => 'sut@example.com'],
      TRUE,
      'The user has not been activated or is blocked.',
    ];
  }

  /**
   * Password reset test data provider.
   *
   * @return \Generator
   *   The test data.
   */
  public function passwordResetDataProvider(): \Generator {
    yield [['name' => 'sut']];
    yield [['mail' => 'sut@example.com']];
  }

}
