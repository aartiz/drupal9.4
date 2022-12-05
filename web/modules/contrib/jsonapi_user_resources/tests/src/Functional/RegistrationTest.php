<?php declare(strict_types = 1);

namespace Drupal\Tests\jsonapi_user_resources\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\jsonapi\Functional\ResourceResponseTestTrait;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Resource User registration.
 *
 * @group jsonapi_user_resources
 */
final class RegistrationTest extends BrowserTestBase {

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
    'jsonapi_user_resources_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);
  }

  /**
   * Tests user registration with JSON:API.
   *
   * @dataProvider dataRegistrationProvider
   */
  public function testRegistration(string $register_setting, bool $verify_mail) {
    $config = $this->config('user.settings');
    $config->set('register', $register_setting);
    $config->set('verify_mail', $verify_mail);
    $config->save();

    $url = Url::fromRoute('jsonapi_user_resources.registration');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Example.User',
          'mail' => 'Example.User@example.com',
          'pass' => 'Password123!',
        ],
      ],
    ];
    if ($verify_mail) {
      unset($request_options[RequestOptions::JSON]['data']['attributes']['pass']);
    }

    $response = $this->request('POST', $url, $request_options);
    if ($register_setting === UserInterface::REGISTER_ADMINISTRATORS_ONLY) {
      $this->assertSame(403, $response->getStatusCode(), $response->getBody());
    }
    else {
      $this->assertSame(201, $response->getStatusCode(), $response->getBody());

      if ($register_setting === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) {
        $this->assertCount(2, $this->drupalGetMails());
        $this->assertMailString('body', 'Your application for an account is', 2);
        $this->assertMailString('body', 'Example.User has applied for an account', 2);
      }
      elseif ($verify_mail) {
        $this->assertCount(1, $this->drupalGetMails());
        $this->assertMailString('body', 'You may now log in by clicking this link', 1);
      }
      else {
        $this->assertCount(0, $this->drupalGetMails());
      }
    }

  }

  /**
   * Tests registration validations.
   */
  public function testRegistrationValidation() {
    $config = $this->config('user.settings');
    $config->set('register', UserInterface::REGISTER_VISITORS);
    $config->set('verify_mail', FALSE);
    $config->save();

    $url = Url::fromRoute('jsonapi_user_resources.registration');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    $document = Json::decode((string) $response->getBody());
    $this->assertEquals([
      [
        'title' => 'Unprocessable Entity',
        'status' => '422',
        'detail' => 'No password provided.',
        'links' => [
          'via' => [
            'href' => $url->setAbsolute(TRUE)->toString(),
          ],
        ],
      ],
    ], $document['errors'], var_export($document, TRUE));

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'pass' => 'Password123!',
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    $document = Json::decode((string) $response->getBody());
    $this->assertEquals([
      [
        'title' => 'Unprocessable Entity',
        'status' => '422',
        'detail' => 'name: You must enter a username.',
        'source' => [
          'pointer' => '/data/attributes/name',
        ],
      ],
      [
        'title' => 'Unprocessable Entity',
        'status' => '422',
        'detail' => 'name: This value should not be null.',
        'source' => [
          'pointer' => '/data/attributes/name',
        ],
      ],
      [
        'title' => 'Unprocessable Entity',
        'status' => '422',
        'detail' => 'mail: Email field is required.',
        'source' => [
          'pointer' => '/data/attributes/mail',
        ],
      ],
    ], $document['errors'], var_export($document, TRUE));

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => ' Example.User',
          'mail' => 'Example.User@example.com',
          'pass' => 'Password123!',
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    $document = Json::decode((string) $response->getBody());
    $this->assertEquals([
      [
        'title' => 'Unprocessable Entity',
        'status' => '422',
        'detail' => 'name: The username cannot begin with a space.',
        'source' => [
          'pointer' => '/data/attributes/name',
        ],
      ],
    ], $document['errors'], var_export($document, TRUE));

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Example.User',
          'mail' => 'Example.User@example.com',
          'pass' => 'Password123!',
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode(), $response->getBody());

    $config->set('verify_mail', TRUE);
    $config->save();

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Example.User2',
          'mail' => 'Example.User2@example.com',
          'pass' => 'Password123!',
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    $document = Json::decode((string) $response->getBody());
    $this->assertEquals([
      [
        'title' => 'Unprocessable Entity',
        'status' => '422',
        'detail' => 'A Password cannot be specified. It will be generated on login.',
        'links' => [
          'via' => [
            'href' => $url->setAbsolute(TRUE)->toString(),
          ],
        ],
      ],
    ], $document['errors'], var_export($document, TRUE));

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Example.User2',
          'mail' => 'Example.User2@example.com',
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode(), $response->getBody());
  }

  /**
   * Tests the registration event.
   */
  public function testRegistrationEvent() {
    $config = $this->config('user.settings');
    $config->set('register', UserInterface::REGISTER_VISITORS);
    $config->set('verify_mail', FALSE);
    $config->save();

    $url = Url::fromRoute('jsonapi_user_resources.registration');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'event_test',
          'mail' => 'blockedEmail@example.com',
          'pass' => 'Password123!',
        ],
        'meta' => [
          'validation' => 'FAIL',
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(400, $response->getStatusCode(), $response->getBody());

    // @codingStandardsIgnoreStart
    /* Blocked on https://www.drupal.org/project/jsonapi_resources/issues/3105792
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Example.User1',
          'mail' => 'Example.User1@example.com',
          'pass' => 'Password123!',
        ],
        'meta' => [
          'validation' => 'PASS'
        ]
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode(), $response->getBody());

    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'user--user',
        'attributes' => [
          'name' => 'Example.User2',
          'mail' => 'Example.User2@example.com',
          'pass' => 'Password123!',
        ],
        'meta' => [
          'validation' => 'FAIL'
        ]
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(400, $response->getStatusCode(), $response->getBody());
    */
    // @codingStandardsIgnoreEnd
  }

  /**
   * Data provider for the registration tests.
   */
  public function dataRegistrationProvider(): \Generator {
    yield [
      UserInterface::REGISTER_ADMINISTRATORS_ONLY,
      FALSE,
    ];
    yield [
      UserInterface::REGISTER_ADMINISTRATORS_ONLY,
      TRUE,
    ];
    yield [
      UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL,
      FALSE,
    ];
    yield [
      UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL,
      TRUE,
    ];
    yield [
      UserInterface::REGISTER_VISITORS,
      FALSE,
    ];
    yield [
      UserInterface::REGISTER_VISITORS,
      TRUE,
    ];
  }

}
